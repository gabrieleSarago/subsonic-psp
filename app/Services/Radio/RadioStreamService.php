<?php

namespace App\Services\Radio;

use App\Data\Radio\Station;
use App\Exceptions\RadioStreamException;
use App\Repositories\Contracts\RadioStationRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a radio station to the PSP, transcoded to MP3 in real time.
 *
 * The stream is never buffered: ffmpeg writes to a pipe and the bytes are
 * copied to the client in chunks, so one listener is one small process instead
 * of one file in memory.
 *
 * The device treats whatever an enclosure points at as a music file: it reads
 * the length first, then plays the bytes as they arrive, and it can seek within
 * them like a track on a memory stick. A live stream has no end, so the answer
 * declares one: the stream carries at most a configurable amount of audio (an
 * hour at the transcode bitrate) and then stops, so the device sees a finite
 * file it knows how to play. Playback ends when that much audio has been
 * delivered, or sooner when the listener stops it.
 */
final class RadioStreamService
{
    public function __construct(
        private readonly RadioStationRepository $stations,
        private readonly StreamProcessRunner $runner,
        private readonly int $firstChunkTimeout,
        private readonly int $chunkSize,
        private readonly int $maxStreamBytes,
    ) {}

    /**
     * The number of bytes a single playback of a station declares, so a caller
     * can answer a size probe without starting a transcode.
     */
    public function declaredLength(): int
    {
        return $this->maxStreamBytes;
    }

    /**
     * Open the stream of a station.
     *
     * @throws RadioStreamException when the station is gone or its stream
     *                              produces no audio.
     */
    public function stream(string $stationId): StreamedResponse
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

        return $this->toStreamedResponse($process, $firstChunk);
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
     * Copy the transcoded bytes to the client, up to the declared length.
     */
    private function toStreamedResponse(StreamProcess $process, string $firstChunk): StreamedResponse
    {
        $chunkSize = $this->chunkSize;
        $maxBytes = $this->maxStreamBytes;

        $response = new StreamedResponse(function () use ($process, $firstChunk, $chunkSize, $maxBytes): void {
            ignore_user_abort(true);

            $sent = 0;

            try {
                $sent += $this->emit($firstChunk, $sent, $maxBytes);

                while ($sent < $maxBytes) {
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

                    $sent += $this->emit($chunk, $sent, $maxBytes);
                }
            } finally {
                // ffmpeg is stopped even when the loop is left through an
                // exception, so a dead station never leaves the process behind.
                $process->stop();
            }
        });

        $response->headers->set('Content-Type', 'audio/mpeg');
        $response->headers->set('Content-Length', (string) $this->maxStreamBytes);

        return $response;
    }

    /**
     * Write a chunk, never sending past the declared length, and report how
     * many bytes were written.
     */
    private function emit(string $chunk, int $sent, int $maxBytes): int
    {
        $remaining = $maxBytes - $sent;

        if (strlen($chunk) > $remaining) {
            $chunk = substr($chunk, 0, max(0, $remaining));
        }

        if ($chunk === '') {
            return 0;
        }

        echo $chunk;
        $this->flush();

        return strlen($chunk);
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
