<?php

namespace App\Services\Radio;

use App\Exceptions\RadioStreamException;

/**
 * A transcode started with proc_open, wrapped so the stream service talks to
 * a process and not to raw resources.
 */
final class ProcStreamProcess implements StreamProcess
{
    /**
     * @param  resource  $process  The handle returned by proc_open.
     * @param  resource  $stdout  The pipe the transcoded audio comes out of.
     */
    private function __construct(
        private mixed $process,
        private mixed $stdout,
    ) {}

    /**
     * @param  array<int, string>  $command
     */
    public static function open(array $command, string $logPath): self
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $logPath, 'a'],
        ];

        $pipes = [];

        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw RadioStreamException::unavailable('ffmpeg non avviabile.');
        }

        // Nothing is ever written to ffmpeg: closing the pipe warns it at once
        // that its input is gone.
        fclose($pipes[0]);

        return new self($process, $pipes[1]);
    }

    public function stdout(): mixed
    {
        return $this->stdout;
    }

    public function isRunning(): bool
    {
        $status = proc_get_status($this->process);

        return $status['running'];
    }

    public function stop(): void
    {
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
            $this->stdout = null;
        }

        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }
}
