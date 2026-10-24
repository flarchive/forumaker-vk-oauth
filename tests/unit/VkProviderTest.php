<?php

declare(strict_types=1);

namespace forumaker\Vk\Tests\unit;

use Flarum\Forum\Auth\Registration;
use Flarum\Settings\SettingsRepositoryInterface;
use forumaker\Vk\OAuth2\VkProvider;
use forumaker\Vk\OAuth2\VkResourceOwner;
use forumaker\Vk\Providers\Vk;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;

/** The VK ID OAuth client and its bridge into fof/oauth's sign-in flow. */
class VkProviderTest extends TestCase
{
    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    private function provider(array $queue = []): VkProvider
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return new VkProvider(
            ['clientId' => 'client-1', 'clientSecret' => 'secret', 'redirectUri' => 'https://forum.test/auth/vk'],
            ['httpClient' => new Client(['handler' => $stack])]
        );
    }

    private function settings(array $values = []): SettingsRepositoryInterface
    {
        return new class($values) implements SettingsRepositoryInterface {
            public function __construct(private array $values)
            {
            }

            public function all(): array
            {
                return $this->values;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
            }

            public function delete(string $keyLike): void
            {
            }
        };
    }

    public function test_it_talks_to_vk_id(): void
    {
        $provider = $this->provider();
        $url = $provider->getAuthorizationUrl();

        $this->assertStringStartsWith('https://id.vk.ru/authorize?', $url);
        $this->assertStringContainsString('client_id=client-1', $url);
        $this->assertStringContainsString('scope=email', $url);
        $this->assertSame('https://id.vk.ru/oauth2/auth', $provider->getBaseAccessTokenUrl([]));
    }

    public function test_the_user_is_read_from_the_user_key_of_the_answer(): void
    {
        $provider = $this->provider([new Response(200, [], json_encode(['user' => ['user_id' => '77', 'first_name' => 'Иван', 'email' => 'i@example.com']]))]);

        $owner = $provider->getResourceOwner(new AccessToken(['access_token' => 'tok']));

        $this->assertInstanceOf(VkResourceOwner::class, $owner);
        $this->assertSame('77', $owner->getId());
        $this->assertSame('i@example.com', $owner->getEmail());

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        parse_str((string) $request->getBody(), $form);
        $this->assertSame(['access_token' => 'tok', 'client_id' => 'client-1'], $form);
    }

    public function test_an_answer_without_a_user_is_an_error(): void
    {
        $this->expectException(IdentityProviderException::class);

        $this->provider([new Response(200, [], '{}')])->getResourceOwner(new AccessToken(['access_token' => 'tok']));
    }

    public function test_an_error_answer_is_an_error_with_vks_message(): void
    {
        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage('token expired');

        $this->provider([new Response(400, [], json_encode(['error' => 'invalid_token', 'error_description' => 'token expired']))])
            ->getResourceOwner(new AccessToken(['access_token' => 'tok']));
    }

    public function test_the_flarum_provider_describes_itself_and_builds_the_client_from_settings(): void
    {
        $vk = new Vk($this->settings(['fof-oauth.vk.client_id' => 'abc', 'fof-oauth.vk.client_secret' => 'def']));

        $this->assertSame('vk', $vk->name());
        $this->assertTrue($vk->pkceEnabled());
        $this->assertArrayHasKey('client_id', $vk->fields());
        $this->assertArrayHasKey('client_secret', $vk->fields());

        $client = $vk->provider('https://forum.test/auth/vk');
        $this->assertInstanceOf(VkProvider::class, $client);
        $this->assertStringContainsString('client_id=abc', $client->getAuthorizationUrl());
    }

    public function test_sign_up_suggestions_come_from_the_vk_profile(): void
    {
        $vk = new Vk($this->settings(['fof-oauth.disable_avatars' => '1']));
        $registration = new Registration();

        $vk->suggestions($registration, new VkResourceOwner(['screen_name' => 'ivan.petrov', 'first_name' => 'Иван', 'last_name' => 'Петров', 'email' => 'i@example.com']), 'tok');

        $this->assertSame('i@example.com', $registration->getProvided()['email']);
        $this->assertTrue($registration->getProvided()['email_confirmed'] ?? true);
        $this->assertSame('ivan-petrov', $registration->getSuggested()['username']);
        $this->assertSame('Иван Петров', $registration->getSuggested()['nickname']);
    }

    public function test_the_username_falls_back_to_the_name_and_is_made_safe_and_short(): void
    {
        $vk = new Vk($this->settings(['fof-oauth.disable_avatars' => '1']));

        $registration = new Registration();
        $vk->suggestions($registration, new VkResourceOwner(['first_name' => 'Иван', 'last_name' => 'Петров']), 'tok');
        $this->assertSame('Ivan-Petrov', $registration->getSuggested()['username'], 'a Cyrillic name is transliterated, not dropped');

        $registration = new Registration();
        $vk->suggestions($registration, new VkResourceOwner(['screen_name' => str_repeat('a', 80)]), 'tok');
        $this->assertSame(30, mb_strlen($registration->getSuggested()['username']));

        $registration = new Registration();
        $vk->suggestions($registration, new VkResourceOwner(['screen_name' => '!!!']), 'tok');
        $this->assertSame('vk-user', $registration->getSuggested()['username']);
    }

    public function test_something_that_is_not_a_vk_profile_suggests_nothing(): void
    {
        $registration = new Registration();

        (new Vk($this->settings()))->suggestions($registration, new \stdClass(), 'tok');

        $this->assertSame([], $registration->getSuggested());
        $this->assertSame([], $registration->getProvided());
    }
}
