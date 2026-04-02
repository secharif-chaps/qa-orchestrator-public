<?php

namespace App\Tests\Units\Infrastructure\Encryption;

use App\Domain\Encryption\EncryptorInterface;

class NullEncryptor implements EncryptorInterface
{
    public function encrypt(string $data): string
    {
        // This is a no-op encryptor that does not perform any encryption.
        return $data;
    }

    public function decrypt(string $encryptedData): string
    {
        // This is a no-op decryptor that does not perform any decryption.
        return $encryptedData;
    }
}
