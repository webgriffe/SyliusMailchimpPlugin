<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Util;

final class IdSanitizer
{
    public static function sanitize(string $id): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $id);

        return $sanitized ?? $id;
    }
}
