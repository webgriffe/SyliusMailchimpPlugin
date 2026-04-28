<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

final class MemberTest extends TestCase
{
    private MergeFields $mergeFields;

    protected function setUp(): void
    {
        $this->mergeFields = new MergeFields('John', 'Doe');
    }

    public function test_it_creates_a_member_with_required_fields(): void
    {
        $member = new Member('john@example.com', 'subscribed', $this->mergeFields);

        self::assertSame('john@example.com', $member->emailAddress);
        self::assertSame('subscribed', $member->status);
        self::assertSame($this->mergeFields, $member->mergeFields);
        self::assertSame([], $member->tags);
        self::assertSame([], $member->interests);
        self::assertSame('', $member->language);
        self::assertNull($member->ipSignup);
    }

    public function test_it_creates_a_member_with_all_fields(): void
    {
        $member = new Member(
            'john@example.com',
            'pending',
            $this->mergeFields,
            ['VIP', 'Newsletter'],
            ['abc123' => true],
            'it',
            '192.168.1.1',
        );

        self::assertSame('pending', $member->status);
        self::assertSame(['VIP', 'Newsletter'], $member->tags);
        self::assertSame(['abc123' => true], $member->interests);
        self::assertSame('it', $member->language);
        self::assertSame('192.168.1.1', $member->ipSignup);
    }

    public function test_with_status_returns_new_immutable_instance(): void
    {
        $member = new Member('john@example.com', 'subscribed', $this->mergeFields);
        $updated = $member->withStatus('unsubscribed');

        self::assertNotSame($member, $updated);
        self::assertSame('subscribed', $member->status);
        self::assertSame('unsubscribed', $updated->status);
        self::assertSame('john@example.com', $updated->emailAddress);
    }

    public function test_with_tags_returns_new_immutable_instance(): void
    {
        $member = new Member('john@example.com', 'subscribed', $this->mergeFields);
        $updated = $member->withTags(['VIP']);

        self::assertNotSame($member, $updated);
        self::assertSame([], $member->tags);
        self::assertSame(['VIP'], $updated->tags);
    }

    public function test_with_interests_returns_new_immutable_instance(): void
    {
        $member = new Member('john@example.com', 'subscribed', $this->mergeFields);
        $updated = $member->withInterests(['abc123' => true]);

        self::assertNotSame($member, $updated);
        self::assertSame([], $member->interests);
        self::assertSame(['abc123' => true], $updated->interests);
    }
}
