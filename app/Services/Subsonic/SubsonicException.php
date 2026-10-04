<?php

namespace App\Services\Subsonic;

use RuntimeException;

/**
 * Raised when the Subsonic server answers with a failure, or cannot be reached.
 */
final class SubsonicException extends RuntimeException
{
    /**
     * The Subsonic code for a request that points at data which is not there.
     */
    private const int NOT_FOUND = 70;

    private function __construct(
        string $message,
        public readonly ?int $errorCode = null,
        private readonly ?string $deviceMessage = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Whether the server said the requested album or playlist does not exist.
     *
     * This is not a failure of the server: an album removed from the library is
     * simply gone, and the caller is expected to answer "not found" rather than
     * to report an outage.
     */
    public function isNotFound(): bool
    {
        return $this->errorCode === self::NOT_FOUND;
    }

    /**
     * The sentence to answer the device with.
     *
     * It is the message itself unless another one was given, because the detail
     * of a transport failure carries the signed URL, with the user name and the
     * token in it. That detail belongs to the log and to the diagnostics command,
     * not to a page a console reads.
     */
    public function deviceMessage(): string
    {
        return $this->deviceMessage ?? $this->getMessage();
    }

    /**
     * Build an exception from the "error" object of a Subsonic response.
     *
     * The server reports API level problems inside a payload, and by default
     * with an HTTP 200 status, so the payload has to be inspected explicitly.
     *
     * @param  array<string, mixed>  $error
     */
    public static function apiError(array $error): self
    {
        $code = isset($error['code']) ? (int) $error['code'] : null;
        $message = (string) ($error['message'] ?? 'Errore sconosciuto');

        return new self(
            $code === null ? $message : "[{$code}] {$message}",
            $code,
        );
    }

    /**
     * Build an exception for a server that did not answer as expected.
     */
    public static function malformedResponse(string $endpoint): self
    {
        return new self("Risposta non valida dall'endpoint {$endpoint}.");
    }

    /**
     * Build an exception for a server that could not be reached at all.
     *
     * The reason describes the situation rather than the transport: it is short,
     * it carries nothing secret, and on its own it tells whoever reads it what to
     * fix. A dropped connection does not qualify, and has its own constructor.
     */
    public static function unreachable(string $reason): self
    {
        return new self("Navidrome non raggiungibile: {$reason}");
    }

    /**
     * Build an exception for a connection that failed rather than for an answer
     * that never arrived.
     *
     * The reason stays in the message, so that the log and the diagnostics command
     * can still say which endpoint failed and why, but the device is given the
     * plain sentence instead: what a transport reports is the URL it was handed,
     * and that URL carries the user name and the token.
     */
    public static function connectionLost(string $reason): self
    {
        return new self(
            "Navidrome non raggiungibile: {$reason}",
            deviceMessage: 'Navidrome non raggiungibile: nessuna risposta dal server.',
        );
    }
}
