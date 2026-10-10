<?php

namespace App\Services\Radio;

use App\Data\Radio\Station;

/**
 * Starts ffmpeg to transcode a station to MP3, always at the same output
 * settings so the PSP hears the same bitrate whatever the source codec is.
 *
 * The options are the ones verified against real Italian stations: reconnect
 * for a stream that hiccups, a lone Icy-MetaData request so a legacy Shoutcast
 * server answers with audio rather than with a redirection, and generated
 * timestamps so an HLS source does not drown the log in dts warnings. A
 * browser-like User-Agent is handed over because some CDNs refuse anything
 * else.
 */
final class FfmpegStreamProcessRunner implements StreamProcessRunner
{
    public function __construct(
        private readonly string $ffmpegBin,
        private readonly int $bitrate,
        private readonly string $userAgent,
        private readonly string $logPath,
    ) {}

    public function start(Station $station): StreamProcess
    {
        $command = [
            $this->ffmpegBin,
            '-hide_banner',
            '-loglevel', 'error',
            '-reconnect', '1',
            '-reconnect_streamed', '1',
            '-reconnect_delay_max', '5',
            '-icy', '1',
            '-fflags', '+genpts',
            '-headers', "User-Agent: {$this->userAgent}\r\n",
            '-i', $station->playableUrl(),
            '-vn',
            '-acodec', 'libmp3lame',
            '-ab', "{$this->bitrate}k",
            '-ar', '44100',
            '-ac', '2',
            '-f', 'mp3',
            'pipe:1',
        ];

        return ProcStreamProcess::open($command, $this->logPath);
    }
}
