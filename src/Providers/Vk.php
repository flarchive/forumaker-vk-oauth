<?php

namespace forumaker\Vk\Providers;

use Flarum\Forum\Auth\Registration;
use FoF\OAuth\Provider;
use League\OAuth2\Client\Provider\AbstractProvider;
use forumaker\Vk\OAuth2\VkProvider;
use forumaker\Vk\OAuth2\VkResourceOwner;

class Vk extends Provider
{
    public function name(): string
    {
        return 'vk';
    }

    public function link(): string
    {
        return 'https://id.vk.ru/about/business/go/accounts';
    }

    public function fields(): array
    {
        return [
            'client_id' => 'required',
            'client_secret' => 'required',
        ];
    }

    public function provider(string $redirectUri): AbstractProvider
    {
        return new VkProvider([
            'clientId' => $this->getSetting('client_id'),
            'clientSecret' => $this->getSetting('client_secret'),
            'redirectUri' => $redirectUri,
        ]);
    }

    public function pkceEnabled(): bool
    {
        return true;
    }

    public function suggestions(Registration $registration, mixed $user, string $token): void
    {
        if (! $user instanceof VkResourceOwner) {
            return;
        }

        if ($user->getEmail()) {
            $registration->provideTrustedEmail($user->getEmail());
        }

        if ($user->getScreenName()) {
            $registration->suggestUsername($this->sanitizeUsername($user->getScreenName()));
        } elseif ($user->getFullName()) {
            $registration->suggestUsername($this->sanitizeUsername($user->getFullName()));
        }

        if ($user->getFullName()) {
            $registration->suggest('nickname', $user->getFullName());
        }

        $this->provideAvatar($registration, $user->getAvatarUrl());
    }

    protected function sanitizeUsername(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^\pL\pN_\-]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return mb_substr($value !== '' ? $value : 'vk-user', 0, 30);
    }
}
