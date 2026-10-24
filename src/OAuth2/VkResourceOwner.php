<?php

namespace forumaker\Vk\OAuth2;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class VkResourceOwner implements ResourceOwnerInterface
{
    public function __construct(protected array $response = [])
    {
    }

    public function getId(): ?string
    {
        $id = $this->response['user_id'] ?? $this->response['id'] ?? null;

        return $id !== null ? (string) $id : null;
    }

    public function getFirstName(): ?string
    {
        return $this->response['first_name'] ?? null;
    }

    public function getLastName(): ?string
    {
        return $this->response['last_name'] ?? null;
    }

    public function getFullName(): ?string
    {
        $full = trim(($this->getFirstName() ?? '') . ' ' . ($this->getLastName() ?? ''));

        return $full !== '' ? $full : null;
    }

    public function getScreenName(): ?string
    {
        return $this->response['screen_name'] ?? null;
    }

    public function getEmail(): ?string
    {
        return $this->response['email'] ?? null;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->response['avatar']
            ?? $this->response['photo_big']
            ?? $this->response['photo_medium']
            ?? null;
    }

    public function toArray(): array
    {
        return $this->response;
    }
}
