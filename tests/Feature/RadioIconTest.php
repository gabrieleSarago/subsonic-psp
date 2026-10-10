<?php

use Illuminate\Support\Facades\Http;
use Tests\Support\Radios;
use Tests\Support\SubsonicApi;

it('proxies the favicon bytes of a station', function (): void {
    Radios::file([[
        'id' => 'rmc',
        'name' => 'RMC',
        'streamUrl' => 'http://stream.test/rmc',
        'favicon' => 'http://cdn.test/logo.png',
    ]]);

    Http::fake([
        'http://cdn.test/logo.png' => Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->get('/radios/rmc/icon');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Cache-Control'))->toContain('max-age=604800');

    expect($response->getContent())->toBe('PNG-BYTES');
});

it('serves a neutral pixel when the station has no favicon', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);

    $response = $this->get('/radios/rmc/icon');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/gif');
});

it('serves the neutral pixel when the remote answer is not an image', function (): void {
    Radios::file([[
        'id' => 'rmc',
        'name' => 'RMC',
        'streamUrl' => 'http://stream.test/rmc',
        'favicon' => 'http://cdn.test/logo',
    ]]);

    Http::fake([
        'http://cdn.test/logo' => Http::response('<html>non è un\'immagine</html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $response = $this->get('/radios/rmc/icon');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/gif');
});

it('serves the neutral pixel for a station that is not in the list', function (): void {
    Radios::file([]);

    $response = $this->get('/radios/assente/icon');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/gif');
});

it('never links the remote image directly from the index page', function (): void {
    SubsonicApi::fake();

    Radios::file([[
        'id' => 'rmc',
        'name' => 'RMC',
        'streamUrl' => 'http://stream.test/rmc',
        'favicon' => 'http://cdn.test/logo.png',
    ]]);

    $content = (string) $this->get('/')->getContent();

    expect($content)
        ->toContain(route('radios.icon.show', ['station' => 'rmc']))
        ->not->toContain('http://cdn.test/logo.png');
});
