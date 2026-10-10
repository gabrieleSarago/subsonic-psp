<?php

use App\Data\Radio\Station;

it('derives the identifier from the name', function (): void {
    $station = Station::fromArray([
        'name' => 'Radio Monte Carlo',
        'streamUrl' => 'http://icecast.test/RMC.mp3',
    ]);

    expect($station->id)->toBe('radio-monte-carlo')
        ->and($station->name)->toBe('Radio Monte Carlo');
});

it('keeps an identifier written by hand', function (): void {
    $station = Station::fromArray([
        'id' => 'rmc',
        'name' => 'Radio Monte Carlo',
        'streamUrl' => 'http://icecast.test/RMC.mp3',
    ]);

    expect($station->id)->toBe('rmc');
});

it('falls back to the host when the name is missing', function (): void {
    $station = Station::fromArray(['streamUrl' => 'http://icecast.unitedradio.it/RMC.mp3']);

    expect($station->id)->toBe('icecastunitedradioit')
        ->and($station->label())->toBe('icecast.unitedradio.it');
});

it('prefers the resolved url as the playable one', function (): void {
    $resolved = Station::fromArray([
        'name' => 'Kiss Kiss',
        'streamUrl' => 'http://old.test/;listen.pls',
        'resolvedUrl' => 'https://new.test/kk.mp3',
    ]);

    $direct = Station::fromArray([
        'name' => 'RMC',
        'streamUrl' => 'http://icecast.test/RMC.mp3',
    ]);

    expect($resolved->playableUrl())->toBe('https://new.test/kk.mp3')
        ->and($direct->playableUrl())->toBe('http://icecast.test/RMC.mp3');
});

it('writes back only the fields that carry a value', function (): void {
    $station = Station::fromArray([
        'name' => 'RMC',
        'streamUrl' => 'http://icecast.test/RMC.mp3',
        'tagline' => '',
        'favicon' => 'http://icecast.test/icon.ico',
    ]);

    expect($station->toArray())->toBe([
        'id' => 'rmc',
        'name' => 'RMC',
        'streamUrl' => 'http://icecast.test/RMC.mp3',
        'favicon' => 'http://icecast.test/icon.ico',
    ]);
});

it('keeps a station without a name playable', function (): void {
    $station = Station::fromArray(['streamUrl' => 'http://icecast.test/RMC.mp3']);

    expect($station->name)->toBe('')
        ->and($station->playableUrl())->toBe('http://icecast.test/RMC.mp3');
});
