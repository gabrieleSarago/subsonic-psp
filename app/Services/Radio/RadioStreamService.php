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
 * The answer is the same one the music proxy gives the device for a track: an
 * MP3 body framed as it arrives, with no header invented in between. A live
 * stream has no length and cannot seek, and the device is better served by an
 * honest answer than by a promised length or range that cannot be honoured.
 */
final class RadioStreamService
{
    public function __construct(
        private readonly RadioStationRepository $stations,
        private readonly StreamProcessRunner $runner,
        private readonly int $firstChunkTimeout,
        private readonly int $chunkSize,
    ) {}

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
     * Copy the transcoded bytes to the client without buffering them.
     */
    private function toStreamedResponse(StreamProcess $process, string $firstChunk): StreamedResponse
    {
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

        return $response;
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
