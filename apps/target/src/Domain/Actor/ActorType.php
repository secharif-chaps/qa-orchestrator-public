<?php

declare(strict_types=1);

namespace App\Domain\Actor;

use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Inflector\FrenchInflector;

enum ActorType: string
{
    case COMPETITOR = 'competitor';
    case PARTNER = 'partner';
    case SUPPLIER = 'supplier';
    case CUSTOMER = 'customer';
    case REGULATOR = 'regulator';
    case SUBSIDIARY = 'subsidiary';
    case PARENT = 'parent';
    case OTHER = 'other';

    public static function fromRoleString(string $role): self
    {
        $roleLower = mb_strtolower($role);

        $singularized = self::singularize($roleLower);

        return match ($singularized) {
            'competitor', 'concurrent' => self::COMPETITOR,
            'partner', 'partenaire' => self::PARTNER,
            'supplier', 'fournisseur' => self::SUPPLIER,
            'customer', 'client' => self::CUSTOMER,
            'regulator', 'regulateur' => self::REGULATOR,
            'subsidiary', 'filiale' => self::SUBSIDIARY,
            'parent' => self::PARENT,
            default => self::OTHER,
        };
    }

    private static function singularize(string $word): string
    {
        $englishInflector = new EnglishInflector();
        $frenchInflector = new FrenchInflector();

        $singulars = $englishInflector->singularize($word);
        if (\count($singulars) > 0) {
            return $singulars[0];
        }

        $singulars = $frenchInflector->singularize($word);
        if (\count($singulars) > 0) {
            return $singulars[0];
        }

        return $word;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
