<?php

use Illuminate\Support\Facades\Http;
use Tests\Support\Radios;
use Tests\Support\SubsonicApi;

/**
 * A tiny PNG the proxy is asked for, real enough for the thumbnailer to
 * decode it back into a JPEG.
 */
function faviconFixture(): string
{
    $canvas = imagecreatetruecolor(64, 48);
    imagefilledrectangle($canvas, 0, 0, 63, 47, imagecolorallocate($canvas, 10, 120, 200));

    ob_start();
    imagepng($canvas);
    $bytes = ob_get_clean();

    return (string) $bytes;
}

it('serves the favicon of a station as a JPEG thumbnail', function (): void {
    Radios::file([[
        'id' => 'rmc',
        'name' => 'RMC',
        'streamUrl' => 'http://stream.test/rmc',
        'favicon' => 'http://cdn.test/logo.png',
    ]]);

    Http::fake([
        'http://cdn.test/logo.png' => Http::response(faviconFixture(), 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->get('/radios/rmc/icon.jpg');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');

    expect($response->headers->get('Cache-Control'))->toContain('max-age=604800');

    [$width, $height] = getimagesizefromstring((string) $response->getContent());

    expect($width)->toBeLessThanOrEqual(160)
        ->and($height)->toBeLessThanOrEqual(120);
});

it('answers 404 when the station has no favicon, so the PSP draws its own icon', function (): void {
    Radios::file([['id' => 'rmc', 'name' => 'RMC', 'streamUrl' => 'http://stream.test/rmc']]);

    $response = $this->get('/radios/rmc/icon.jpg');

    $response->assertNotFound();
    // The absence must not be cached: a favicon added later has to show up.
    expect($response->headers->get('Cache-Control'))->not->toContain('max-age=604800');
});

it('answers 404 when the remote answer is not an image', function (): void {
    Radios::file([[
        'id' => 'rmc',
        'name' => 'RMC',
        'streamUrl' => 'http://stream.test/rmc',
        'favicon' => 'http://cdn.test/logo',
    ]]);

    Http::fake([
        'http://cdn.test/logo' => Http::response('<html>non è un\'immagine</html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $this->get('/radios/rmc/icon.jpg')->assertNotFound();
});

it('answers 404 for a station that is not in the list', function (): void {
    Radios::file([]);

    $this->get('/radios/assente/icon.jpg')->assertNotFound();
});

it('never links the remote image directly from the index page', function (): void {
    SubsonicApi::fake();

    Radios::file([[
        'id' => 'rmc',
        'name' => 'RMC',
        'streamUrl' => 'http://stream.test/rmc',
        'favicon' => 'http://cdn.test/logo.png',
    ]]);

    expect((string) $this->get('/')->getContent())->not->toContain('http://cdn.test/logo.png');
});
