<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Encryption;

use App\Domain\Encryption\Exception\EncryptionFailedException;
use App\Domain\Encryption\Exception\InvalidSecretKeyException;
use App\Infrastructure\Encryption\Encryptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Encryptor::class)]
class EncryptorTest extends TestCase
{
    private const string TEST_DATA = 'This is a test string.';
    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secretKey = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
    }

    public function testEncryptDecryptSuccess(): void
    {
        $encryptor = new Encryptor($this->secretKey);
        $encrypted = $encryptor->encrypt(self::TEST_DATA);
        $decrypted = $encryptor->decrypt($encrypted);

        $this->assertNotSame(self::TEST_DATA, $encrypted);
        $this->assertSame(self::TEST_DATA, $decrypted);
    }

    public function testEncryptDecryptEmptyString(): void
    {
        $encryptor = new Encryptor($this->secretKey);
        $encrypted = $encryptor->encrypt('');
        $decrypted = $encryptor->decrypt($encrypted);

        $this->assertNotEmpty($encrypted);
        $this->assertSame('', $decrypted);
    }

    public function testEncryptDecryptWithBinaryData(): void
    {
        $encryptor = new Encryptor($this->secretKey);
        $binaryData = random_bytes(128);
        $encrypted = $encryptor->encrypt($binaryData);
        $decrypted = $encryptor->decrypt($encrypted);

        $this->assertSame($binaryData, $decrypted);
    }

    public function testInvalidSecretKeyLength(): void
    {
        $this->expectException(InvalidSecretKeyException::class);
        $this->expectExceptionMessageMatches(
            '/The provided secret key must be exactly \d+ bytes long, given: \d+ bytes/',
        );
        new Encryptor('short-secret-key');
    }

    public function testDecryptWithWrongKey(): void
    {
        $encryptor1 = new Encryptor($this->secretKey);
        $encrypted = $encryptor1->encrypt(self::TEST_DATA);

        $wrongKey = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
        $encryptor2 = new Encryptor($wrongKey);

        $this->expectException(EncryptionFailedException::class);
        $encryptor2->decrypt($encrypted);
    }

    public function testDecryptInvalidBase64Data(): void
    {
        $encryptor = new Encryptor($this->secretKey);

        $this->expectException(EncryptionFailedException::class);
        $encryptor->decrypt('invalid-base64-data');
    }

    public function testDecryptTamperedCiphertext(): void
    {
        $encryptor = new Encryptor($this->secretKey);
        $encrypted = $encryptor->encrypt(self::TEST_DATA);
        $decoded = base64_decode($encrypted);
        $tampered = $decoded;
        $lastIndex = \strlen($tampered) - 1;
        $tampered[$lastIndex] = \chr(\ord($tampered[$lastIndex]) ^ 0xFF);

        $this->expectException(EncryptionFailedException::class);
        $encryptor->decrypt(base64_encode($tampered));
    }

    public function testDecryptTamperedNonce(): void
    {
        $encryptor = new Encryptor($this->secretKey);
        $encrypted = $encryptor->encrypt(self::TEST_DATA);
        $decoded = base64_decode($encrypted);
        // XOR first byte with 0xFF to guarantee it's always different
        $tampered = $decoded;
        $tampered[0] = \chr(\ord($tampered[0]) ^ 0xFF);

        $this->expectException(EncryptionFailedException::class);
        $encryptor->decrypt(base64_encode($tampered));
    }

    public function testDecryptTruncatedData(): void
    {
        $encryptor = new Encryptor($this->secretKey);
        $encrypted = $encryptor->encrypt(self::TEST_DATA);
        $decoded = base64_decode($encrypted);
        $truncated = substr($decoded, 0, 10);

        $this->expectException(EncryptionFailedException::class);
        $encryptor->decrypt(base64_encode($truncated));
    }
}
