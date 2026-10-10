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

    // The answer is the same one the music proxy gives the device: the bytes of
    // an MP3 body framed as they arrive, with no length or range invented in
    // between. A live stream can promise neither.
    expect($response->headers->has('Content-Length'))->toBeFalse()
        ->and($response->headers->has('Accept-Ranges'))->toBeFalse()
        ->and($response->streamedContent())->toBe('ID3-streamed-bytes')
        ->and($runner->stations)->toHaveCount(1)
        ->and($runner->stations[0]->playableUrl())->toBe('http://stream.test/rmc');
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
