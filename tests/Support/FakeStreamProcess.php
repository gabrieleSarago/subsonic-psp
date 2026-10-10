<?php

namespace Tests\Support;

use App\Services\Radio\StreamProcess;
use RuntimeException;

/**
 * A transcode that is not a process at all.
 *
 * The bytes an ffmpeg would write to its pipe are written into one end of a
 * socket pair, so the stream service can select and read them exactly as it
 * reads the real one, without ffmpeg having to run in the test suite.
 */
final class FakeStreamProcess implements StreamProcess
{
    /**
     * @var resource|null
     */
    private $reader;

    /**
     * @var resource|null
     */
    private $writer;

    private bool $running;

    private bool $stopped = false;

    /**
     * @param  resource  $reader
     * @param  resource|null  $writer
     */
    private function __construct($reader, $writer, bool $running)
    {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->running = $running;
    }

    /**
     * A process that emits the given bytes and then reaches the end of its
     * output, like a transcode whose source has ended.
     */
    public static function emitting(string $bytes): self
    {
        [$reader, $writer] = self::pair();

        fwrite($writer, $bytes);
        fclose($writer);

        return new self($reader, null, false);
    }

    /**
     * A process that produced nothing and is no longer running.
     */
    public static function dead(): self
    {
        [$reader, $writer] = self::pair();

        fclose($writer);

        return new self($reader, null, false);
    }

    /**
     * A process that is running but emits nothing, like a stalled upstream.
     */
    public static function silent(): self
    {
        [$reader, $writer] = self::pair();

        return new self($reader, $writer, true);
    }

    public function stdout()
    {
        return $this->reader;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function stop(): void
    {
        if (is_resource($this->reader)) {
            fclose($this->reader);
        }

        if (is_resource($this->writer)) {
            fclose($this->writer);
        }

        $this->reader = null;
        $this->writer = null;
        $this->running = false;
        $this->stopped = true;
    }

    public function wasStopped(): bool
    {
        return $this->stopped;
    }

    /**
     * @return array{0: resource, 1: resource}
     */
    private static function pair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        if ($pair === false) {
            throw new RuntimeException('Impossibile creare una coppia di socket per il test.');
        }

        return $pair;
    }
}
