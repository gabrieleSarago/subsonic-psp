<?php

namespace App\Data\Radio;

/**
 * What a probe fetch read back from one URL: the headers and enough of the
 * body to tell a stream from a playlist.
 */
final readonly class ProbeResponse
{
    /**
     * @param  array<string, string>  $icy  The icy-* headers, without the prefix.
     */
    public function __construct(
        public string $url,
        public int $status,
        public string $contentType,
        public string $body,
        public array $icy = [],
    ) {}
}
