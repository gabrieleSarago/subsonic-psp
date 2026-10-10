<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised while preparing a radio stream for the client.
 *
 * A station that is not in the list is a missing resource, while a stream that
 * produces no audio is a broken upstream: the two statuses are carried along so
 * the device can tell a dead link from a dead station.
 */
final class RadioStreamException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 502,
    ) {
        parent::__construct($message);
    }

    /**
     * The requested station is not in the curated list.
     */
    public static function stationNotFound(): self
    {
        return new self('Stazione non trovata.', 404);
    }

    /**
     * The stream could not be started, or started without producing audio.
     */
    public static function unavailable(string $reason = ''): self
    {
        $message = 'Stazione non disponibile';

        if ($reason !== '') {
            $message .= ': '.$reason;
        }

        return new self($message.'.', 503);
    }
}
