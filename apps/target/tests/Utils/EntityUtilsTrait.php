<?php

declare(strict_types=1);

namespace App\Tests\Utils;

trait EntityUtilsTrait
{
    private function forcePropertyValue(object $object, mixed $value, string $propertyName = 'id'): void
    {
        $reflectionClass = new \ReflectionClass($object);
        if (!$reflectionClass->hasProperty($propertyName)) {
            $parentClass = $reflectionClass->getParentClass();
            if ($parentClass && $parentClass->hasProperty($propertyName)) {
                $reflectionClass = $parentClass;
            } else {
                throw new \InvalidArgumentException(\sprintf(
                    'Property %s does not exist in class %s',
                    $propertyName,
                    $object::class
                ));
            }
        }
        $reflectionProperty = $reflectionClass->getProperty($propertyName);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
        $reflectionProperty->setAccessible(false);
    }
}
