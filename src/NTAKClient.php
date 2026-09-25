<?php

namespace QueSoft\Ntak;

use Carbon\Carbon;
use Gamegos\JWS\JWS;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use QueSoft\Ntak\Exceptions\NTAKClientException;

class NTAKClient
{
    protected Client $client;
    protected ?Carbon $when = null;
    protected string $url;
    protected ?array $lastRequest = null;
    protected ?array $lastResponse = null;
    protected ?int $lastRequestTime = null;
    protected ?array $fakeResponse = null;

    protected static string $prodUrl = 'https://rms.ntak.hu/';
    protected static string $testUrl = 'https://rms.tesztntak.hu';

    public function __construct(
        protected string $taxNumber,
        protected string $regNumber,
        protected string $softwareRegNumber,
        protected string $version,
        protected string $certPath,
        protected bool $testing = false,
    ) {
        $this->url = $testing
            ? self::$testUrl
            : self::$prodUrl;

        $this->client = new Client([
            'base_uri' => $this->url,
            'cert' => $certPath,
            'ssl_key' => $certPath,
            'verify' => false,
            'timeout' => 30.0,
            'connect_timeout' => 20.0,
        ]);
    }

    public function fakeResponse(array $response): self
    {
        $this->fakeResponse = $response;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     *
     * @throws NTAKClientException
     * @throws GuzzleException
     */
    public function message(array $message, Carbon $when, string $uri): array
    {
        $this->when = $when;

        $json = array_merge(
            $this->header(),
            $message
        );

        $headers = $this->requestHeaders($json);

        try {
            $this->lastRequest = $json;

            $start = Carbon::now();

            $response = $this->fakeResponse
                ?? $this->client->request(
                    'POST',
                    $uri,
                    compact('json', 'headers')
                );

            $this->lastRequestTime = (int) Carbon::now()->diffInMilliseconds($start);
        } catch (RequestException $e) {
            throw new NTAKClientException(
                $e->hasResponse()
                    ? $e->getResponse()->getBody()->getContents()
                    : $e->getMessage(),
                previous: $e
            );
        }

        return $this->lastResponse = is_array($response)
            ? $response
            : (json_decode((string) $response->getBody(), true) ?? []);
    }

    public static function prodUrl(): string
    {
        return self::$prodUrl;
    }

    public static function testUrl(): string
    {
        return self::$testUrl;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{x-jws-signature: string, x-certificate: string}
     */
    protected function requestHeaders(array $message): array
    {
        $privateKey = openssl_pkey_get_private(
            (string) file_get_contents($this->certPath)
        );

        $jws = (new JWS())->encode(
            [
                'alg' => 'RS256',
                'typ' => 'JWS',
            ],
            $message,
            $privateKey
        );

        $tmp = explode('.', $jws);

        openssl_x509_export(
            (string) file_get_contents($this->certPath),
            $tmpCert
        );

        return [
            'x-jws-signature' => $tmp[0].'..'.$tmp[2],
            'x-certificate' => base64_encode($tmpCert),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function header(): array
    {
        return [
            'szolgaltatoAdatok' => [
                'adoszam' => $this->taxNumber,
                'vendeglatoUzletRegSzam' => $this->regNumber,
            ],
            'uzenetAdatok' => [
                'uzenetKuldesIdeje' => $this->when->timezone('Europe/Budapest')->toIso8601String(),
            ],
            'kuldoRendszerAdatok' => [
                'rmsRendszerNTAKAzonosito' => $this->softwareRegNumber,
                'rmsRendszerVerzioszam' => $this->version,
            ],
        ];
    }

    public function lastRequest(): ?array
    {
        return $this->lastRequest;
    }

    public function lastResponse(): ?array
    {
        return $this->lastResponse;
    }

    public function lastRequestTime(): ?int
    {
        return $this->lastRequestTime;
    }
}
