<?php

use App\Services\Radio\Contracts\StreamProbeFetcher;
use Tests\Support\FakeStreamProbeFetcher;
use Tests\Support\Radios;

it('writes the discovered fields back without touching the curated name', function (): void {
    Radios::file([[
        'id' => 'rmc',
        'name' => 'RMC Radio Monte Carlo',
        'streamUrl' => 'http://stream.test/rmc',
    ]]);

    $fetcher = (new FakeStreamProbeFetcher)
        ->answerStream('http://stream.test/rmc', body: "\xff\xfb", contentType: 'audio/mpeg', icy: [
            'name' => 'FM_RMC_mp3_segrate',
            'description' => 'FM_RMC_mp3_segrate',
            'genre' => 'Various',
            'url' => 'http://radiomontecarlo.test',
        ])
        ->answerText('http://radiomontecarlo.test', '<link rel="icon" href="/favicon.ico">');

    $this->app->instance(StreamProbeFetcher::class, $fetcher);

    $this->artisan('radios:probe')->assertSuccessful();

    $row = Radios::read()[0];

    expect($row['name'])->toBe('RMC Radio Monte Carlo')
        ->and($row['tagline'])->toBe('Various')
        ->and($row['resolvedUrl'])->toBe('http://stream.test/rmc')
        ->and($row['favicon'])->toBe('http://radiomontecarlo.test/favicon.ico');
});

it('replaces even the fields written by hand when refresh is asked for', function (): void {
    Radios::file([[
        'id' => 'rmc',
        'name' => 'Nome vecchio',
        'tagline' => 'Vecchia tagline',
        'streamUrl' => 'http://stream.test/rmc',
    ]]);

    $fetcher = (new FakeStreamProbeFetcher)->answerStream(
        'http://stream.test/rmc',
        body: "\xff\xfb",
        icy: ['name' => 'Nome nuovo', 'description' => 'Tagline nuova'],
    );

    $this->app->instance(StreamProbeFetcher::class, $fetcher);

    $this->artisan('radios:probe', ['--refresh' => true])->assertSuccessful();

    $row = Radios::read()[0];

    expect($row['name'])->toBe('Nome nuovo')
        ->and($row['tagline'])->toBe('Tagline nuova');
});

it('leaves a station it cannot reach exactly as it was written', function (): void {
    Radios::file([[
        'id' => 'rmc',
        'name' => 'RMC',
        'streamUrl' => 'http://dead.test/rmc',
    ]]);

    $this->app->instance(StreamProbeFetcher::class, new FakeStreamProbeFetcher);

    $this->artisan('radios:probe')->assertSuccessful();

    // The file is not rewritten at all when nothing was updated, so even the
    // field order a person chose is preserved.
    expect(Radios::read()[0])->toBe([
        'id' => 'rmc',
        'name' => 'RMC',
        'streamUrl' => 'http://dead.test/rmc',
    ]);
});

it('writes the discovered fields in a predictable order', function (): void {
    Radios::file([[
        'streamUrl' => 'http://stream.test/live',
    ]]);

    $fetcher = (new FakeStreamProbeFetcher)->answerStream(
        'http://stream.test/live',
        body: "\xff\xfb",
        icy: ['name' => 'Radio Prova', 'genre' => 'Pop', 'url' => 'http://radio.test'],
    );
    $fetcher->answerText('http://radio.test', '<link rel="icon" href="/icon.png">');

    $this->app->instance(StreamProbeFetcher::class, $fetcher);

    $this->artisan('radios:probe')->assertSuccessful();

    expect(array_keys(Radios::read()[0]))->toBe([
        'id',
        'name',
        'tagline',
        'streamUrl',
        'resolvedUrl',
        'favicon',
    ]);
});

it('fails when there is no station in the file', function (): void {
    Radios::file([]);

    $this->artisan('radios:probe')->assertFailed();
});
