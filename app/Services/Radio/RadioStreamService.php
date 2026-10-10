<?php

namespace App\Services\Radio;

use App\Data\Radio\Station;
use App\Exceptions\RadioStreamException;
use App\Repositories\Contracts\RadioStationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a radio station to the PSP, transcoded to MP3 in real time.
 *
 * The stream is never buffered: ffmpeg writes to a pipe and the bytes are
 * copied to the client in chunks, so one listener is one small process instead
 * of one file in memory.
 *
 * A live stream has no end, but the device only plays an answer whose length
 * it can see. A body of unknown length is framed as chunked by the server in
 * front of this application, and the PSP refuses such an answer, while a track
 * from the music server always carries the length of the file. The response
 * therefore declares a length that is never reached, so the audio reaches the
 * device unwrapped and plays as it arrives.
 */
final class RadioStreamService
{
    /**
     * The length declared when the configured one is missing or nonsense, so
     * that a stale configuration cache cannot turn the stream into an empty
     * answer.
     */
    private const int FALLBACK_DECLARED_LENGTH = 2_000_000_000;

    public function __construct(
        private readonly RadioStationRepository $stations,
        private readonly StreamProcessRunner $runner,
        private readonly int $firstChunkTimeout,
        private readonly int $chunkSize,
        private readonly int $declaredLength,
    ) {}

    /**
     * Open the stream of a station.
     *
     * @throws RadioStreamException when the station is gone or its stream
     *                              produces no audio.
     */
    public function stream(string $stationId, ?string $rangeHeader = null): StreamedResponse
    {
        $station = $this->stations->find($stationId);

        if (! $station instanceof Station) {
            throw RadioStreamException::stationNotFound();
        }

        $process = $this->runner->start($station);

        $firstChunk = $this->firstChunk($process);

        if ($firstChunk === null) {
            $process->stop();

            throw RadioStreamException::unavailable(
                'nessun audio ricevuto da '.$station->label(),
            );
        }

        return $this->toStreamedResponse($process, $firstChunk, $rangeHeader);
    }

    /**
     * Wait for the first transcoded bytes.
     *
     * This runs before the response is built, so a station whose source is
     * dead is refused with a real 5xx instead of a 200 that plays silence.
     * An upstream that stalls is refused too, once the timeout runs out.
     */
    private function firstChunk(StreamProcess $process): ?string
    {
        $deadline = microtime(true) + $this->firstChunkTimeout;

        while (microtime(true) < $deadline) {
            $read = [$process->stdout()];
            $write = null;
            $except = null;

            $remaining = $deadline - microtime(true);
            $seconds = (int) floor($remaining);
            $microseconds = (int) (($remaining - $seconds) * 1_000_000);

            $selected = @stream_select($read, $write, $except, $seconds, $microseconds);

            if ($selected === false) {
                // Interrupted while waiting: try again, the deadline still
                // bounds the whole wait.
                continue;
            }

            if ($selected > 0) {
                $chunk = fread($process->stdout(), $this->chunkSize);

                if ($chunk !== false && $chunk !== '') {
                    return $chunk;
                }
            }

            if (! $process->isRunning()) {
                return null;
            }
        }

        return null;
    }

    /**
     * Copy the transcoded bytes to the client without buffering them.
     */
    private function toStreamedResponse(
        StreamProcess $process,
        string $firstChunk,
        ?string $rangeHeader,
    ): StreamedResponse {
        $chunkSize = $this->chunkSize;

        $response = new StreamedResponse(function () use ($process, $firstChunk, $chunkSize): void {
            ignore_user_abort(true);

            try {
                echo $firstChunk;
                $this->flush();

                while (true) {
                    if (connection_aborted() !== 0) {
                        break;
                    }

                    $read = [$process->stdout()];
                    $write = null;
                    $except = null;

                    $selected = @stream_select($read, $write, $except, 10, 0);

                    if ($selected === false || $selected === 0) {
                        if (! $process->isRunning()) {
                            break;
                        }

                        continue;
                    }

                    $chunk = fread($process->stdout(), $chunkSize);

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    echo $chunk;
                    $this->flush();
                }
            } finally {
                // ffmpeg is stopped even when the loop is left through an
                // exception, so a dead station never leaves the process behind.
                $process->stop();
            }
        });

        $response->headers->set('Content-Type', 'audio/mpeg');
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Accel-Buffering', 'no');
        $this->declareLength($response, $rangeHeader);

        return $response;
    }

    /**
     * Present the live stream as a resource of a known length.
     *
     * Without a Content-Length the answer is framed as chunked on the way out,
     * and the device refuses a chunked body. The declared length is large
     * enough never to be reached, so the stream keeps flowing until the
     * listener stops it. A range that begins at the start of the stream is
     * answered with a 206, like the music server answers the device for a
     * track; any other range is ignored, because a live stream cannot seek.
     */
    private function declareLength(StreamedResponse $response, ?string $rangeHeader): void
    {
        $length = $this->declaredLength > 0
            ? $this->declaredLength
            : self::FALLBACK_DECLARED_LENGTH;

        $response->headers->set('Content-Length', (string) $length);
        $response->headers->set('Accept-Ranges', 'bytes');

        if (! $this->rangeStartsAtZero($rangeHeader)) {
            return;
        }

        $response->setStatusCode(Response::HTTP_PARTIAL_CONTENT);
        $response->headers->set('Content-Range', 'bytes 0-'.($length - 1).'/'.$length);
    }

    /**
     * Whether the device asked for the stream from its very first byte.
     */
    private function rangeStartsAtZero(?string $rangeHeader): bool
    {
        if ($rangeHeader === null) {
            return false;
        }

        return preg_match('/^bytes=0-/', trim($rangeHeader)) === 1;
    }

    /**
     * Send whatever is buffered, so the bytes reach the client as they are
     * produced rather than when the stream ends.
     */
    private function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
