<?php

namespace App\Services\Radio;

use App\Data\Radio\ProbeResponse;
use App\Services\Radio\Contracts\StreamProbeFetcher;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * The probe's access to the network, through the same HTTP client the rest of
 * the application uses.
 *
 * The Icy-MetaData header is sent while probing so that the legacy Shoutcast
 * servers that require it answer with the audio stream instead of a
 * redirection. Redirects are followed by the client itself (the probe reads
 * the final URL from the transfer statistics), because a stream that moved
 * keeps working for years at its old address.
 */
final class GuzzleStreamProbeFetcher implements StreamProbeFetcher
{
    public function __construct(
        private readonly string $userAgent,
        private readonly int $timeout,
        private readonly int $connectTimeout,
    ) {}

    public function fetchStream(string $url, int $maxBytes): ?ProbeResponse
    {
        try {
            $effectiveUrl = $url;

            $response = Http::buildClient()
                ->request('GET', $url, [
                    'stream' => true,
                    'timeout' => $this->timeout,
                    'connect_timeout' => $this->connectTimeout,
                    'http_errors' => false,
                    'headers' => [
                        'Icy-MetaData' => '1',
                        'User-Agent' => $this->userAgent,
                        'Accept' => '*/*',
                    ],
                    'on_stats' => function (TransferStats $stats) use (&$effectiveUrl): void {
                        $effectiveUrl = (string) $stats->getEffectiveUri();
                    },
                ]);
        } catch (Throwable) {
            return null;
        }

        $body = $response->getBody();
        $prefix = $this->readPrefix($body, $maxBytes);
        $body->close();

        $icy = [];

        foreach (['name', 'description', 'genre', 'url', 'br'] as $field) {
            $value = trim($response->getHeaderLine('icy-'.$field));

            if ($value !== '') {
                $icy[$field] = $value;
            }
        }

        return new ProbeResponse(
            url: $effectiveUrl,
            status: $response->getStatusCode(),
            contentType: strtolower($response->getHeaderLine('Content-Type')),
            body: $prefix,
            icy: $icy,
        );
    }

    public function fetchText(string $url, int $maxBytes): ?string
    {
        try {
            $response = Http::buildClient()
                ->request('GET', $url, [
                    'stream' => true,
                    'timeout' => $this->timeout,
                    'connect_timeout' => $this->connectTimeout,
                    'http_errors' => false,
                    'headers' => ['User-Agent' => $this->userAgent],
                ]);
        } catch (Throwable) {
            return null;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $response->getBody()->close();

            return null;
        }

        $body = $response->getBody();
        $text = $this->readPrefix($body, $maxBytes);
        $body->close();

        return $text === '' ? null : $text;
    }

    private function readPrefix(StreamInterface $body, int $maxBytes): string
    {
        $prefix = '';

        while (strlen($prefix) < $maxBytes) {
            try {
                $chunk = $body->read(min(4096, $maxBytes - strlen($prefix)));
            } catch (Throwable) {
                break;
            }

            if ($chunk === '') {
                break;
            }

            $prefix .= $chunk;
        }

        return $prefix;
    }
}
