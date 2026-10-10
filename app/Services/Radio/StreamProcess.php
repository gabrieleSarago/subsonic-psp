<?php

namespace App\Services\Radio;

/**
 * A running transcode: the pipe its MP3 comes out of, and its lifecycle.
 */
interface StreamProcess
{
    /**
     * The pipe the transcoded audio is read from.
     *
     * @return resource
     */
    public function stdout();

    /**
     * Whether the process is still running.
     */
    public function isRunning(): bool;

    /**
     * Stop the process and release everything it holds.
     */
    public function stop(): void;
}
