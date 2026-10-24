<?php

declare(strict_types=1);

namespace forumaker\Vk\Tests\unit;

use forumaker\Vk\OAuth2\VkResourceOwner;
use PHPUnit\Framework\TestCase;

/** What the forum learns about a visitor from VK ID's user_info answer. */
class VkResourceOwnerTest extends TestCase
{
    public function test_the_id_is_user_id_or_id_as_a_string(): void
    {
        $this->assertSame('123', (new VkResourceOwner(['user_id' => 123]))->getId());
        $this->assertSame('456', (new VkResourceOwner(['id' => 456]))->getId());
        $this->assertSame('123', (new VkResourceOwner(['user_id' => '123', 'id' => 999]))->getId(), 'user_id wins');
        $this->assertNull((new VkResourceOwner([]))->getId());
    }

    public function test_the_full_name_joins_first_and_last_name(): void
    {
        $this->assertSame('Иван Петров', (new VkResourceOwner(['first_name' => 'Иван', 'last_name' => 'Петров']))->getFullName());
        $this->assertSame('Иван', (new VkResourceOwner(['first_name' => 'Иван']))->getFullName());
        $this->assertSame('Петров', (new VkResourceOwner(['last_name' => 'Петров']))->getFullName());
        $this->assertNull((new VkResourceOwner(['first_name' => ' ', 'last_name' => '']))->getFullName());
        $this->assertNull((new VkResourceOwner([]))->getFullName());
    }

    public function test_the_avatar_is_the_best_photo_available(): void
    {
        $this->assertSame('a', (new VkResourceOwner(['avatar' => 'a', 'photo_big' => 'b', 'photo_medium' => 'c']))->getAvatarUrl());
        $this->assertSame('b', (new VkResourceOwner(['photo_big' => 'b', 'photo_medium' => 'c']))->getAvatarUrl());
        $this->assertSame('c', (new VkResourceOwner(['photo_medium' => 'c']))->getAvatarUrl());
        $this->assertNull((new VkResourceOwner([]))->getAvatarUrl());
    }

    public function test_email_and_screen_name_are_optional(): void
    {
        $owner = new VkResourceOwner(['email' => 'ivan@example.com', 'screen_name' => 'ivan']);

        $this->assertSame('ivan@example.com', $owner->getEmail());
        $this->assertSame('ivan', $owner->getScreenName());
        $this->assertNull((new VkResourceOwner([]))->getEmail());
        $this->assertNull((new VkResourceOwner([]))->getScreenName());
    }

    public function test_the_raw_answer_is_kept(): void
    {
        $this->assertSame(['id' => 1, 'x' => 'y'], (new VkResourceOwner(['id' => 1, 'x' => 'y']))->toArray());
    }
}
