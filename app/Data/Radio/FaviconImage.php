<?php

namespace App\Data\Radio;

/**
 * A favicon ready to be written to a response.
 */
final readonly class FaviconImage
{
    public function __construct(
        public string $bytes,
        public string $contentType,
    ) {}
}
