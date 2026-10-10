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
 * The device treats whatever an enclosure points at as a music file. Before it
 * plays anything it asks for the size with a HEAD, then reads the tail of the
 * stream with a byte range, exactly as it would with a track on a memory stick.
 * A live stream has neither an end nor a tail, so the answer declares a finite
 * length and honours a range: a range that starts at the beginning is served as
 * it arrives, and the tail the device probes for is answered from the start of
 * the same audio, which is all a probe needs to recognise an MP3. Playback
 * carries at most a configurable amount of audio (an hour at the transcode
 * bitrate) and then stops, so the device always sees a file it can read.
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

        return $this->toStreamedResponse($process, $firstChunk, $this->resolveRange($rangeHeader));
    }

    /**
     * Work out the status, the content range and the number of bytes to send
     * for the range the device asked for.
     *
     * A range that starts past the beginning of the stream is the tail probe
     * the device makes before playing: it is answered with a 206 carrying the
     * same amount of audio, taken from the start of the live transcoding, which
     * is an MP3 the device recognises.
     *
     * @return array{status: int, contentRange: ?string, length: int}
     */
    private function resolveRange(?string $rangeHeader): array
    {
        $total = $this->maxStreamBytes;
        $header = trim((string) $rangeHeader);

        // A suffix range asks for the last N bytes, which is another shape the
        // tail probe may take.
        if (preg_match('/^bytes=-(\d+)$/', $header, $matches) === 1) {
            $suffix = min((int) $matches[1], $total);

            if ($suffix > 0) {
                return [
                    'status' => Response::HTTP_PARTIAL_CONTENT,
                    'contentRange' => 'bytes '.($total - $suffix).'-'.($total - 1).'/'.$total,
                    'length' => $suffix,
                ];
            }
        }

        if (preg_match('/^bytes=(\d+)-(\d*)$/', $header, $matches) === 1) {
            $start = (int) $matches[1];
            $end = $matches[2] !== '' ? (int) $matches[2] : $total - 1;
            $end = min($end, $total - 1);

            if ($start <= $end && $start < $total) {
                return [
                    'status' => Response::HTTP_PARTIAL_CONTENT,
                    'contentRange' => 'bytes '.$start.'-'.$end.'/'.$total,
                    'length' => $end - $start + 1,
                ];
            }
        }

        return [
            'status' => Response::HTTP_OK,
            'contentRange' => null,
            'length' => $total,
        ];
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
     * Copy the transcoded bytes to the client, up to the amount the range asks
     * for.
     *
     * @param  array{status: int, contentRange: ?string, length: int}  $range
     */
    private function toStreamedResponse(StreamProcess $process, string $firstChunk, array $range): StreamedResponse
    {
        $chunkSize = $this->chunkSize;
        $length = $range['length'];

        $response = new StreamedResponse(function () use ($process, $firstChunk, $chunkSize, $length): void {
            ignore_user_abort(true);

            $sent = 0;

            try {
                $sent += $this->emit($firstChunk, $sent, $length);

                while ($sent < $length) {
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

                    $sent += $this->emit($chunk, $sent, $length);
                }
            } finally {
                // ffmpeg is stopped even when the loop is left through an
                // exception, so a dead station never leaves the process behind.
                $process->stop();
            }
        });

        $response->setStatusCode($range['status']);
        $response->headers->set('Content-Type', 'audio/mpeg');
        $response->headers->set('Content-Length', (string) $length);
        $response->headers->set('Accept-Ranges', 'bytes');

        if ($range['contentRange'] !== null) {
            $response->headers->set('Content-Range', $range['contentRange']);
        }

        return $response;
    }

    /**
     * Write a chunk, never sending past the length the range allows, and report
     * how many bytes were written.
     */
    private function emit(string $chunk, int $sent, int $length): int
    {
        $remaining = $length - $sent;

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
