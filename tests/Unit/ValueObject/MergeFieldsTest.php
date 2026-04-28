<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

final class MergeFieldsTest extends TestCase
{
    public function test_it_creates_merge_fields_with_defaults(): void
    {
        $mergeFields = new MergeFields();

        self::assertSame('', $mergeFields->firstName);
        self::assertSame('', $mergeFields->lastName);
        self::assertSame([], $mergeFields->extra);
    }

    public function test_it_creates_merge_fields_with_name(): void
    {
        $mergeFields = new MergeFields('John', 'Doe');

        self::assertSame('John', $mergeFields->firstName);
        self::assertSame('Doe', $mergeFields->lastName);
    }

    public function test_with_extra_returns_new_immutable_instance(): void
    {
        $mergeFields = new MergeFields('John', 'Doe');
        $updated = $mergeFields->withExtra(['PHONE' => '+39 123456789']);

        self::assertNotSame($mergeFields, $updated);
        self::assertSame([], $mergeFields->extra);
        self::assertSame(['PHONE' => '+39 123456789'], $updated->extra);
        self::assertSame('John', $updated->firstName);
        self::assertSame('Doe', $updated->lastName);
    }
}
