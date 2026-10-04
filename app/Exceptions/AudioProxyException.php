<?php

namespace App\Exceptions;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Raised while proxying audio to the client.
 *
 * Carries the status code the client should see, because the two failure modes
 * are not equivalent: a track that no longer exists is a missing resource,
 * while an upstream answer that is not audio is a broken gateway.
 */
final class AudioProxyException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 502,
    ) {
        parent::__construct($message);
    }

    /**
     * The requested track is not in the library.
     */
    public static function trackNotFound(): self
    {
        return new self('Brano non trovato.', 404);
    }

    /**
     * The upstream server answered with something that is not audio.
     *
     * A rejected API call answers with an HTTP 200 and an XML or JSON body, so
     * this is the only way to notice that the bytes about to be handed to the
     * player are not audio at all.
     */
    public static function notAudio(string $contentType, string $body): self
    {
        $detail = trim($body) === '' ? 'corpo vuoto' : Str::limit(trim($body), 200, '...');

        return new self(sprintf(
            'Navidrome ha risposto con "%s" invece di audio: %s',
            $contentType === '' ? 'una risposta vuota' : $contentType,
            $detail,
        ));
    }
}
