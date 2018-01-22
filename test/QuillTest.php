<?php
declare(strict_types=1);

use ParagonIE\Quill\Quill;
use PHPUnit\Framework\TestCase;
use ParagonIE\Sapient\CryptographyKeys\{
    SigningPublicKey,
    SigningSecretKey
};

/**
 * Class QuillTest
 */
class QuillTest extends TestCase
{
    /**
     * @covers Quill::__construct()
     * @covers Quill::getClientSecretKey()
     * @covers Quill::getClientID()
     * @covers Quill::getChronicleURL()
     * @covers Quill::getServerPublicKey()
     */
    public function testDefaultValues()
    {
        $quill = new Quill(
            '',
            '',
            null,
            null,
            new \GuzzleHttp\Client()
        );
        $this->assertSame('', $quill->getChronicleURL());
        $this->assertSame('', $quill->getClientID());
        try {
            $quill->getClientSecretKey();
            $this->fail('A TypeError should have bene thrown');
        } catch (\TypeError $ex) {
        }

        try {
            $quill->getServerPublicKey();
            $this->fail('A TypeError should have bene thrown');
        } catch (\TypeError $ex) {
        }
    }

    /**
     * @covers Quill::getClientSecretKey()
     * @covers Quill::getClientID()
     * @covers Quill::getChronicleURL()
     * @covers Quill::getServerPublicKey()
     * @covers Quill::setClientSecretKey()
     * @covers Quill::etClientID()
     * @covers Quill::setChronicleURL()
     * @covers Quill::setServerPublicKey()
     */
    public function testGetSet()
    {
        $quill = new Quill(
            '',
            '',
            null,
            null,
            new \GuzzleHttp\Client()
        );

        $sign_keypair = sodium_crypto_sign_keypair();
        $signingSecretKey = new SigningSecretKey(
            sodium_crypto_sign_secretkey($sign_keypair)
        );
        $signingPublicKey = new SigningPublicKey(
            sodium_crypto_sign_publickey($sign_keypair)
        );

        $clientID = bin2hex(random_bytes(32));
        $quill->setClientID($clientID);
        $this->assertSame($clientID, $quill->getClientID());

        $url = 'https://' . bin2hex(random_bytes(32)) . '.example.com/chronicle';
        $quill->setChronicleURL($url);
        $this->assertSame($url, $quill->getChronicleURL());

        $quill->setServerPublicKey($signingPublicKey);
        $this->assertSame($signingPublicKey->getString(), $quill->getServerPublicKey()->getString());

        $quill->setClientSecretKey($signingSecretKey);
        $this->assertSame($signingSecretKey->getString(), $quill->getClientSecretKey()->getString());
    }
}
