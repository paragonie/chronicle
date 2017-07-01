<?php
namespace ParagonIE\Chronicle\UnitTests;

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;
use PHPUnit\Framework\TestCase;

class ChronicleTest extends TestCase
{
    /**
     * @covers Chronicle::getSigningKey()
     */
    public function testSigningKey()
    {
        $this->assertInstanceOf(
            SigningSecretKey::class,
            Chronicle::getSigningKey()
        );
    }
}
