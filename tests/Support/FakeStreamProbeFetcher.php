<?php

namespace Tests\Support;

use App\Data\Radio\ProbeResponse;
use App\Services\Radio\Contracts\StreamProbeFetcher;

/**
 * A stand-in for the probe's network access.
 *
 * Streams and documents are registered by URL; a URL that was not registered
 * is reported as unreachable, the way a dead host is. Text lookups also match
 * on a prefix, so the radio-browser search can be answered without spelling out
 * the query string it is built with.
 */
final class FakeStreamProbeFetcher implements StreamProbeFetcher
{
    /**
     * @var array<string, ProbeResponse>
     */
    private array $streams = [];

    /**
     * @var array<string, string|null>
     */
    private array $texts = [];

    /**
     * Every URL the probe asked for, in order.
     *
     * @var list<string>
     */
    public array $streamRequests = [];

    /**
     * @var list<string>
     */
    public array $textRequests = [];

    /**
     * @param  array<string, string>  $icy
     */
    public function answerStream(
        string $url,
        string $body = '',
        string $contentType = 'audio/mpeg',
        int $status = 200,
        array $icy = [],
        ?string $resolvedUrl = null,
    ): self {
        $this->streams[$url] = new ProbeResponse(
            url: $resolvedUrl ?? $url,
            status: $status,
            contentType: $contentType,
            body: $body,
            icy: $icy,
        );

        return $this;
    }

    public function answerText(string $url, ?string $body): self
    {
        $this->texts[$url] = $body;

        return $this;
    }

    public function fetchStream(string $url, int $maxBytes): ?ProbeResponse
    {
        $this->streamRequests[] = $url;

        return $this->streams[$url] ?? null;
    }

    public function fetchText(string $url, int $maxBytes): ?string
    {
        $this->textRequests[] = $url;

        foreach ($this->texts as $key => $body) {
            if ($url === $key || str_starts_with($url, $key)) {
                return $body;
            }
        }

        return null;
    }
}
