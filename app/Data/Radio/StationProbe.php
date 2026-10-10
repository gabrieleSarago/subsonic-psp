<?php

namespace App\Data\Radio;

/**
 * What the probe learned about one station.
 */
final readonly class StationProbe
{
    public function __construct(
        /**
         * The URL of the actual audio stream, after following redirects and
         * playlist hops. This is what the transcoder is told to read.
         */
        public string $resolvedUrl,
        public ?string $name = null,
        public ?string $tagline = null,
        public ?string $favicon = null,
    ) {}
}
