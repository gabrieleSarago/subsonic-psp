<?php

namespace App\Services\Radio;

use App\Data\Radio\Station;
use App\Exceptions\RadioStreamException;

/**
 * Starts the process that transcodes a station, kept behind an interface so
 * the stream service can run against a fake process in the tests.
 */
interface StreamProcessRunner
{
    /**
     * @throws RadioStreamException when the binary cannot start.
     */
    public function start(Station $station): StreamProcess;
}
