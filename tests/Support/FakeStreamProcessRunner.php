<?php

namespace Tests\Support;

use App\Data\Radio\Station;
use App\Services\Radio\StreamProcess;
use App\Services\Radio\StreamProcessRunner;

/**
 * A runner that hands out one prepared process and remembers what it was asked
 * to transcode.
 */
final class FakeStreamProcessRunner implements StreamProcessRunner
{
    /**
     * @var list<Station>
     */
    public array $stations = [];

    public function __construct(private readonly StreamProcess $process) {}

    public function start(Station $station): StreamProcess
    {
        $this->stations[] = $station;

        return $this->process;
    }
}
