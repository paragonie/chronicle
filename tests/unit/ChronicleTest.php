<?php
namespace ParagonIE\Chronicle\UnitTests;

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\Exception\SecurityViolation;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;
use ParagonIE\Sapient\Sapient;
use PHPUnit\Framework\TestCase;
use Slim\Http\Request;

class ChronicleTest extends TestCase
{
    /** @var SigningSecretKey */
    protected $signingKey;

    /**
     * Setup the basic configuration for unit testing purposes.
     */
    public function setUp()
    {
        parent::setUp();
        $this->signingKey = SigningSecretKey::generate();
        try {
            $settings = Chronicle::getSettings();
        } catch (\TypeError $ex) {
            $settings = [];
        }
        $settings['request-timeout'] = '10 minutes';
        Chronicle::storeSettings($settings);
    }

    /**
     * @covers Chronicle::errorResponse()
     */
    public function testErrorResponse()
    {
        $baseResponse = Chronicle::getSapient()->createSignedJsonResponse(200, [], $this->signingKey, []);
        $message = 'This is a generic error message';

        $response = Chronicle::errorResponse(
            $baseResponse,
            $message,
            200
        );

        $validated = Chronicle::getSapient()->decodeSignedJsonResponse(
            $response,
            Chronicle::getSigningKey()->getPublicKey()
        );
        $this->assertTrue(\is_array($validated));
        $this->assertEquals(
            [
                'version' => Chronicle::VERSION,
                'datetime' => $validated['datetime'],
                'status' => 'ERROR',
                'message' => $message
            ],
            $validated
        );
    }

    /**
     * @covers Chronicle::getSapient()
     */
    public function testSapient()
    {
        $this->assertInstanceOf(
            Sapient::class,
            Chronicle::getSapient()
        );
    }

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

    /**
     * @covers Chronicle::validateTimestamps()
     */
    public function testValidateTimestamps()
    {
        $now = new \DateTime('NOW');
        $earlier = (new \DateTime('NOW'))
            ->sub(
                \DateInterval::createFromDateString('1 year')
            );

        $request = Chronicle::getSapient()->createSignedJsonRequest(
            'POST',
            '/test-request',
            [
                'request-time' => $now->format(\DateTime::ATOM)
            ],
            $this->signingKey
        );
        $oldRequest = Chronicle::getSapient()->createSignedJsonRequest(
            'POST',
            '/test-request',
            [
                'request-time' => $earlier->format(\DateTime::ATOM)
            ],
            $this->signingKey
        );

        try {
            Chronicle::validateTimestamps($request);
            $this->assertTrue($request instanceof Request);
        } catch (SecurityViolation $ex) {
            $this->fail($ex->getMessage());
        }

        try {
            Chronicle::validateTimestamps($oldRequest);
            $this->fail('Timestamp is not being enforced');
        } catch (SecurityViolation $ex) {
        }

        // If we increase the request timeout, does it pass?
        $settings = Chronicle::getSettings();
        $settings['request-timeout'] = '2 years';
        Chronicle::storeSettings($settings);

        try {
            Chronicle::validateTimestamps($oldRequest);
            $this->assertTrue($oldRequest instanceof Request);
        } catch (SecurityViolation $ex) {
            $this->fail($ex->getMessage());
        }

        $settings['request-timeout'] = '10 minutes';
        Chronicle::storeSettings($settings);
    }
}
