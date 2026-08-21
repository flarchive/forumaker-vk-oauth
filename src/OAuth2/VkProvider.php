<?php

namespace forumaker\Vk\OAuth2;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\ResponseInterface;

class VkProvider extends AbstractProvider
{
    public function getBaseAuthorizationUrl(): string
    {
        return 'https://id.vk.ru/authorize';
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://id.vk.ru/oauth2/auth';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return 'https://id.vk.ru/oauth2/user_info';
    }

    protected function getDefaultScopes(): array
    {
        return ['email'];
    }

    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    public function getAccessToken($grant, array $options = []): AccessTokenInterface
    {
        if (empty($options['device_id']) && app()->bound('fof-oauth-request')) {
            $deviceId = resolve('fof-oauth-request')->getQueryParams()['device_id'] ?? null;

            if ($deviceId) {
                $options['device_id'] = $deviceId;
            }
        }

        return parent::getAccessToken($grant, $options);
    }

    public function getResourceOwner(AccessTokenInterface $token): VkResourceOwner
    {
        $request = $this->getRequest(self::METHOD_POST, $this->getResourceOwnerDetailsUrl($token), [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query([
                'access_token' => $token->getToken(),
                'client_id' => $this->clientId,
            ]),
        ]);

        $response = $this->getParsedResponse($request);
        $user = $response['user'] ?? null;

        if (! is_array($user)) {
            throw new IdentityProviderException('VK ID returned no user data.', 0, $response);
        }

        return $this->createResourceOwner($user, $token);
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (isset($data['error'])) {
            $message = $data['error_description'] ?? $data['error'];

            throw new IdentityProviderException((string) $message, (int) $response->getStatusCode(), $data);
        }
    }

    protected function createResourceOwner(array $response, AccessTokenInterface $token): VkResourceOwner
    {
        return new VkResourceOwner($response);
    }

    protected function getDefaultHeaders(): array
    {
        return ['Accept' => 'application/json'] + parent::getDefaultHeaders();
    }
}
