<?php

declare(strict_types=1);

namespace App\Domain\Encryption;

interface EncryptorInterface
{
    public function encrypt(string $data): string;

    public function decrypt(string $encryptedData): string;
}
