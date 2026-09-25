<?php

namespace QueSoft\Ntak\Tests;

use Carbon\Carbon;
use QueSoft\Ntak\NTAKClient;

class FakeNTAKClient extends NTAKClient
{
    public function message(array $message, Carbon $when, string $uri): array
    {
        $json = array_merge(
            [
                'szolgaltatoAdatok' => [
                    'adoszam' => 'fake',
                    'vendeglatoUzletRegSzam' => 'fake',
                ],
                'uzenetAdatok' => [
                    'uzenetKuldesIdeje' => $when->timezone('Europe/Budapest')->toIso8601String(),
                ],
                'kuldoRendszerAdatok' => [
                    'rmsRendszerNTAKAzonosito' => 'fake',
                    'rmsRendszerVerzioszam' => 'fake',
                ],
            ],
            $message
        );

        $this->lastRequest = $json;

        if ($this->fakeResponse !== null) {
            return $this->lastResponse = $this->fakeResponse;
        }

        return $this->lastResponse = [
            'feldolgozasAzonosito' => 'fake-ntak-process-'.bin2hex(random_bytes(8)),
            'uzenetValaszok' => [],
        ];
    }

    protected function requestHeaders(array $message): array
    {
        return [
            'x-jws-signature' => 'fake-jws-signature-content',
            'x-certificate' => 'fake-base64-encoded-cert',
        ];
    }
}
