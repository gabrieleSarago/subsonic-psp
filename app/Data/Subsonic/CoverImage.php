<?php

namespace App\Data\Subsonic;

/**
 * A cover image ready to be written to a response.
 */
final readonly class CoverImage
{
    public function __construct(
        public string $bytes,
        public string $contentType,
    ) {}
}
