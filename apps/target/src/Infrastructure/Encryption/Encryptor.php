<?php

declare(strict_types=1);

namespace App\Infrastructure\Encryption;

use App\Domain\Encryption\EncryptorInterface;
use App\Domain\Encryption\Exception\EncryptionFailedException;
use App\Domain\Encryption\Exception\InvalidSecretKeyException;

class Encryptor implements EncryptorInterface
{
    private const int NONCE_BYTES = \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    public function __construct(
        private readonly string $secretKey,
    ) {
        if (\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== mb_strlen($this->secretKey, '8bit')) {
            throw new InvalidSecretKeyException(\sprintf(
                'The provided secret key must be exactly %s bytes long, given: %s bytes.',
                \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
                mb_strlen($this->secretKey, '8bit'),
            ));
        }
    }

    public function encrypt(string $data): string
    {
        try {
            $nonce = random_bytes(self::NONCE_BYTES);
            $encrypted = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($data, $nonce, $nonce, $this->secretKey);

            return base64_encode($nonce . $encrypted);
        } catch (\Throwable $e) {
            throw new EncryptionFailedException('Failed to encrypt data.', 0, $e);
        }
    }

    public function decrypt(string $encryptedData): string
    {
        try {
            $decoded = base64_decode($encryptedData, true);
            if (false === $decoded) {
                throw new \RuntimeException('Invalid base64 data.');
            }

            $nonce = mb_substr($decoded, 0, self::NONCE_BYTES, '8bit');
            $ciphertext = mb_substr($decoded, self::NONCE_BYTES, null, '8bit');

            $decrypted = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                $nonce,
                $nonce,
                $this->secretKey
            );

            if (false === $decrypted) {
                throw new \RuntimeException('Failed to decrypt data.');
            }

            return $decrypted;
        } catch (\Throwable $e) {
            throw new EncryptionFailedException('Failed to decrypt data.', 0, $e);
        }
    }
}
