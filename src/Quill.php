<?php
declare(strict_types=1);
namespace ParagonIE\Quill;

use GuzzleHttp\Client;
use ParagonIE\Certainty\Exception\CertaintyException;
use GuzzleHttp\Psr7\{
    Request,
    Response
};
use ParagonIE\Certainty\Exception\BundleException;
use ParagonIE\Certainty\RemoteFetch;
use ParagonIE\Sapient\Adapter\Guzzle;
use ParagonIE\Sapient\CryptographyKeys\{
    SealingPublicKey,
    SharedEncryptionKey,
    SigningPublicKey,
    SigningSecretKey
};
use ParagonIE\Sapient\Exception\{
    HeaderMissingException,
    InvalidMessageException
};
use ParagonIE\Sapient\Sapient;
use ParagonIE\Sapient\Simple;
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
     * @param Client|null $http
     *
     * @throws CertaintyException
     * @throws \SodiumException
     * @throws \TypeError
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
            $this->http = new Client([
                'curl.options' => [
                    // https://github.com/curl/curl/blob/6aa86c493bd77b70d1f5018e102bc3094290d588/include/curl/curl.h#L1927
                    CURLOPT_SSLVERSION =>
                        CURL_SSLVERSION_TLSv1_2 | (CURL_SSLVERSION_TLSv1 << 16)
                ],
                'verify' => (new RemoteFetch())->getLatestBundle()->getFilePath()
            ]);
        }
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
        } catch (InvalidMessageException $ex) {
            return false;
        } catch (HeaderMissingException $ex) {
            return false;
        }
    }

    /**
     * Encrypt data with a shared (symmetric) encryption key, then write it
     * to a Chronicle. Returns TRUE if published successfully.
     *
     * @param string $data
     * @param SharedEncryptionKey $sharedEncryptionKey
     * @return bool
     * @throws \Error
     */
    public function blindWriteEncrypted(
        string $data,
        SharedEncryptionKey $sharedEncryptionKey
    ): bool
    {
        try {
            $response = $this->writeEncrypted($data, $sharedEncryptionKey);
            // If we're here, the data was written successfully.
            return $response instanceof ResponseInterface;
        } catch (InvalidMessageException $ex) {
            return false;
        } catch (HeaderMissingException $ex) {
            return false;
        }
    }

    /**
     * Encrypt data with an public key (asymmetric encryption), then write it
     * to a Chronicle. Returns TRUE if published successfully.
     *
     * @param string $data
     * @param SealingPublicKey $publicKey
     * @return bool
     * @throws \Error
     */
    public function blindWriteSealed(
        string $data,
        SealingPublicKey $publicKey
    ): bool
    {
        try {
            $response = $this->writeSealed($data, $publicKey);
            // If we're here, the data was written successfully.
            return $response instanceof ResponseInterface;
        } catch (InvalidMessageException $ex) {
            return false;
        } catch (HeaderMissingException $ex) {
            return false;
        }
    }

    /**
     * @return string
     */
    public function getChronicleURL(): string
    {
        return $this->chronicleUrl;
    }

    /**
     * @return string
     */
    public function getClientID(): string
    {
        return $this->clientID;
    }

    /**
     * @return SigningSecretKey
     */
    public function getClientSecretKey(): SigningSecretKey
    {
        return $this->clientSSK;
    }

    /**
     * @return SigningPublicKey
     */
    public function getServerPublicKey(): SigningPublicKey
    {
        return $this->serverSPK;
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
     * Encrypt a message and publish its contents onto a Chronicle instance,
     * using a shared encryption key. (Symmetric cryptography.)
     *
     * @param string $data
     * @param SharedEncryptionKey $sharedEncryptionKey
     * @return ResponseInterface
     * @throws HeaderMissingException
     * @throws InvalidMessageException
     * @throws \Error
     */
    public function writeEncrypted(
        string $data,
        SharedEncryptionKey $sharedEncryptionKey
    ): ResponseInterface {
        return $this->write(
            Simple::encrypt($data, $sharedEncryptionKey)
        );
    }

    /**
     * Encrypt a message and publish its contents onto a Chronicle instance,
     * using a public encryption key. (Asymmetric cryptography.)
     *
     * @param string $data
     * @param SealingPublicKey $publicKey
     * @return ResponseInterface
     * @throws HeaderMissingException
     * @throws InvalidMessageException
     * @throws \Error
     */
    public function writeSealed(
        string $data,
        SealingPublicKey $publicKey
    ): ResponseInterface {
        return $this->write(
            Simple::seal($data, $publicKey)
        );
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

        $url = $this->chronicleUrl;
        $pieces = \explode('/', \trim($this->chronicleUrl, '/'));
        $last = \array_pop($pieces);
        if ($last !== 'publish') {
            $precursor = \array_pop($pieces);
            if ($precursor === 'chronicle') {
                $url = $this->chronicleUrl . '/publish';
            } else {
                $url = $this->chronicleUrl . '/chronicle/publish';
            }
        }

        /** @var Request $request */
        $request = $sapient->createSignedRequest(
            'POST',
            $url,
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
     * @return void
     * @throws \Error
     */
    protected function assertValid()
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
     * Validate the Chronicle's JSON response.
     *
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
