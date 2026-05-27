<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit;

trait ReflectionIdTrait
{
    protected static function setIdOnObject(object $entity, int $id): void
    {
        $reflectionClass = new \ReflectionClass($entity::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($entity, $id);
    }
}
