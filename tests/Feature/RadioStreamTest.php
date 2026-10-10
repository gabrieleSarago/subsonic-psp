<?php

use App\Services\Radio\StreamProcessRunner;
use Tests\Support\FakeStreamProcess;
use Tests\Support\FakeStreamProcessRunner;
use Tests\Support\Radios;

it('streams a station as mp3', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);

    $runner = new FakeStreamProcessRunner(FakeStreamProcess::emitting('ID3-streamed-bytes'));
    $this->app->instance(StreamProcessRunner::class, $runner);

    $response = $this->get('/radios/rmc/stream');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'audio/mpeg');

    // The device treats the enclosure as a music file and reads the length
    // before it plays anything, so the stream declares the finite amount of
    // audio it will carry instead of leaving the size unknown.
    expect($response->headers->get('Content-Length'))->toBe('57600000')
        ->and($response->headers->get('Accept-Ranges'))->toBe('bytes')
        ->and($response->streamedContent())->toBe('ID3-streamed-bytes')
        ->and($runner->stations)->toHaveCount(1)
        ->and($runner->stations[0]->playableUrl())->toBe('http://stream.test/rmc');
});

it('answers the tail range the device probes before playing', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);
    config(['radios.stream_max_bytes' => 20480]);

    $this->app->instance(StreamProcessRunner::class, new FakeStreamProcessRunner(FakeStreamProcess::emitting(str_repeat('M', 100000))));

    // The device reads the last ten kilobytes of the file before it starts, the
    // same way it reads a track on a memory stick.
    $response = $this->get('/radios/rmc/stream', ['Range' => 'bytes=10240-']);

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 10240-20479/20480');
    $response->assertHeader('Content-Length', '10240');

    expect($response->streamedContent())->toBe(str_repeat('M', 10240));
});

it('stops at the declared length so the stream ends like a file', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);
    config(['radios.stream_max_bytes' => 5000]);

    $this->app->instance(StreamProcessRunner::class, new FakeStreamProcessRunner(FakeStreamProcess::emitting(str_repeat('M', 100000))));

    $response = $this->get('/radios/rmc/stream');

    $response->assertHeader('Content-Length', '5000');

    expect($response->streamedContent())->toBe(str_repeat('M', 5000));
});

it('answers a size probe without starting a transcode', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);
    config(['radios.stream_max_bytes' => 5000]);

    $runner = new FakeStreamProcessRunner(FakeStreamProcess::emitting('ID3'));
    $this->app->instance(StreamProcessRunner::class, $runner);

    $response = $this->head('/radios/rmc/stream');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'audio/mpeg');
    $response->assertHeader('Content-Length', '5000');
    $response->assertHeader('Accept-Ranges', 'bytes');

    expect($runner->stations)->toHaveCount(0);
});

it('transcodes the resolved url rather than the one written by hand', function (): void {
    Radios::file([[
        'id' => 'kiss',
        'name' => 'Kiss Kiss',
        'streamUrl' => 'http://old.test/;listen.pls',
        'resolvedUrl' => 'https://new.test/kk.mp3',
    ]]);

    $runner = new FakeStreamProcessRunner(FakeStreamProcess::emitting('audio'));
    $this->app->instance(StreamProcessRunner::class, $runner);

    $this->get('/radios/kiss/stream')->assertOk();

    expect($runner->stations[0]->playableUrl())->toBe('https://new.test/kk.mp3');
});

it('passes the whole stream through, not just the first chunk', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);

    // Larger than the 64k chunk size, so it has to be copied across more than
    // one read to arrive whole.
    $body = str_repeat('M', 100000);
    $this->app->instance(StreamProcessRunner::class, new FakeStreamProcessRunner(FakeStreamProcess::emitting($body)));

    expect($this->get('/radios/rmc/stream')->streamedContent())->toBe($body);
});

it('answers 404 for a station that is not in the list', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);

    $this->get('/radios/assente/stream')->assertNotFound();
});

it('answers 503 when the transcode dies without producing audio', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);

    $this->app->instance(StreamProcessRunner::class, new FakeStreamProcessRunner(FakeStreamProcess::dead()));

    $response = $this->get('/radios/rmc/stream');

    $response->assertStatus(503);
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});

it('gives up on a station whose upstream stalls, and stops the transcode', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);
    config(['radios.first_chunk_timeout' => 1]);

    $process = FakeStreamProcess::silent();
    $this->app->instance(StreamProcessRunner::class, new FakeStreamProcessRunner($process));

    $this->get('/radios/rmc/stream')->assertStatus(503);

    expect($process->wasStopped())->toBeTrue();
});
