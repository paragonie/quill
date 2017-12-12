<?php
declare(strict_types=1);
namespace ParagonIE\Quill;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ParagonIE\Sapient\Adapter\Guzzle;
use ParagonIE\Sapient\CryptographyKeys\{
    SigningPublicKey,
    SigningSecretKey
};
use ParagonIE\Sapient\Exception\{
    HeaderMissingException,
    InvalidMessageException
};
use ParagonIE\Sapient\Sapient;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Quill
 * @package ParagonIE\Quill
 */
class Quill
{
    const CLIENT_ID_HEADER = 'Chronicle-Client-Key-ID';

    /**
     * @var string $chronicleUrl
     */
    protected $chronicleUrl = '';

    /**
     * @var string $clientID
     */
    protected $clientID = '';

    /**
     * @var SigningSecretKey $clientSSK
     */
    protected $clientSSK = null;

    /**
     * @var Client
     */
    protected $http = null;

    /**
     * @var SigningPublicKey $serverSPK
     */
    protected $serverSPK = null;

    /**
     * Quill constructor.
     *
     * @param string $url
     * @param string $clientId
     * @param SigningPublicKey|null $serverPublicKey
     * @param SigningSecretKey|null $clientSecretKey
     */
    public function __construct(
        string $url = '',
        string $clientId = '',
        SigningPublicKey $serverPublicKey = null,
        SigningSecretKey $clientSecretKey = null,
        Client $http = null
    ) {
        if ($url) {
            $this->chronicleUrl = $url;
        }
        if ($clientId) {
            $this->clientID = $clientId;
        }
        if ($serverPublicKey) {
            $this->serverSPK = $serverPublicKey;
        }
        if ($clientSecretKey) {
            $this->clientSSK = $clientSecretKey;
        }
        if ($http) {
            $this->http = $http;
        } else {
            $this->http = new Client();
        }
    }

    /**
     * Write data to the Chronicle instance. Return the Response object.
     *
     * @param string $data
     * @return ResponseInterface
     *
     * @throws HeaderMissingException
     * @throws InvalidMessageException
     * @throws \Error
     */
    public function write(string $data): ResponseInterface
    {
        $this->assertValid();
        $sapient = new Sapient(new Guzzle($this->http));

        /** @var Request $request */
        $request = $sapient->createSignedRequest(
            'POST',
            $this->chronicleUrl,
            $data,
            $this->clientSSK,
            [
                static::CLIENT_ID_HEADER => $this->clientID
            ]
        );
        /** @var Response $response */
        $response = $this->http->send($request);

        /** @var Response $verified */
        $verified = $sapient->verifySignedResponse(
            $response,
            $this->serverSPK
        );
        return $this->validateResponse($verified);
    }

    /**
     * Write data to the Chronicle Instance. Return a boolean indicating
     * success or failure, discarding the response body after verification.
     *
     * @param string $data
     * @return bool
     * @throws \Error
     */
    public function blindWrite(string $data): bool
    {
        try {
            $response = $this->write($data);
            // If we're here, the data was written successfully.
            return $response instanceof ResponseInterface;
        } catch (InvalidMessageException | HeaderMissingException $ex) {
            return false;
        }
    }

    /**
     * @param string $url
     * @return self
     */
    public function setChronicleURL(string $url): self
    {
        $this->chronicleUrl = $url;
        return $this;
    }

    /**
     * @param string $clientID
     * @return self
     */
    public function setClientID(string $clientID): self
    {
        $this->clientID = $clientID;
        return $this;
    }

    /**
     * @param SigningSecretKey $secretKey
     * @return self
     */
    public function setClientSecretKey(SigningSecretKey $secretKey): self
    {
        $this->clientSSK = $secretKey;
        return $this;
    }

    /**
     * @param SigningPublicKey $publicKey
     * @return self
     */
    public function setServerPublicKey(SigningPublicKey $publicKey): self
    {
        $this->serverSPK = $publicKey;
        return $this;
    }

    /**
     * @throws \Error
     */
    protected function assertValid(): void
    {
        if (!$this->clientID) {
            throw new \Error('Client ID is not populated');
        }
        if (!$this->chronicleUrl) {
            throw new \Error('Chronicle URL is not populated');
        }
        if (!$this->clientSSK) {
            throw new \Error('Client signing secret key is not populated');
        }
        if (!$this->serverSPK) {
            throw new \Error('Server signing public key is not populated');
        }
    }

    /**
     * @param Response $response
     * @return Response
     * @throws InvalidMessageException
     */
    protected function validateResponse(Response $response): Response
    {
        /** @var string $body */
        $body = (string) $response->getBody();
        /** @var array $decoded */
        $decoded = \json_decode($body, true);
        if (!\is_array($decoded)) {
            throw new InvalidMessageException('Could not parse JSON body');
        }
        if ($decoded['status'] !== 'OK') {
            throw new InvalidMessageException(
                (string) $decoded['message'] ?? 'An unknown error has occurred.'
            );
        }
        return $response;
    }
}
