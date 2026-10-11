<?php

use Tests\Support\Radios;
use Tests\Support\SubsonicApi;

/**
 * The probe feed is debug scaffolding, so its tests only pin down the shape:
 * one item per markup variant, images that are distinct and correctly sized,
 * and the exact markup each variant must carry for the comparison on the
 * device to mean anything.
 */
it('renders one item per variant with the exact markup each one needs', function (): void {
    $response = $this->get('/psp-test/feed');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    $items = feedItems($response);

    expect($items)->toHaveCount(7)
        ->and((string) feedChannel($response)->title)->toBe('Prova icone PSP');

    $media = 'http://search.yahoo.com/mrss/';
    $itunes = 'http://www.itunes.com/dtds/podcast-1.0.dtd';

    // 1 - Sony style: a .jpg URL with the declared dimensions equal to the
    // real ones, exactly as the official sample channel is written.
    $thumbnail = $items[0]->children($media)->thumbnail->attributes();

    expect((string) $thumbnail['url'])->toBe(route('psp-test.image', ['variant' => 'sony.jpg']))
        ->and((string) $thumbnail['width'])->toBe('80')
        ->and((string) $thumbnail['height'])->toBe('60');

    // 2 - A .jpg URL with the maximum declared dimensions.
    $thumbnail = $items[1]->children($media)->thumbnail->attributes();

    expect((string) $thumbnail['url'])->toBe(route('psp-test.image', ['variant' => 'box.jpg']))
        ->and((string) $thumbnail['width'])->toBe('160')
        ->and((string) $thumbnail['height'])->toBe('120');

    // 3 - The production shape: no extension in the URL.
    $thumbnail = $items[2]->children($media)->thumbnail->attributes();

    expect((string) $thumbnail['url'])->toBe(route('psp-test.image', ['variant' => 'extless']))
        ->and((string) $thumbnail['url'])->not->toEndWith('.jpg')
        ->and((string) $thumbnail['width'])->toBe('160');

    // 4 - The iTunes namespace instead of Media RSS.
    expect(isset($items[3]->children($media)->thumbnail[0]))->toBeFalse()
        ->and((string) $items[3]->children($itunes)->image->attributes()['href'])
        ->toBe(route('psp-test.image', ['variant' => 'itunes.jpg']));

    // 5 - The thumbnail nested inside <media:content>.
    $nested = $items[4]->children($media)->content->thumbnail->attributes();

    expect((string) $nested['url'])->toBe(route('psp-test.image', ['variant' => 'mcontent.jpg']))
        ->and((string) $nested['width'])->toBe('160');

    // 6 - A still-image enclosure, the one kind the device always illustrates.
    expect((string) $items[5]->enclosure['type'])->toBe('image/jpeg')
        ->and((string) $items[5]->enclosure['url'])->toBe(route('psp-test.image', ['variant' => 'picture.jpg']))
        ->and((string) $items[5]->children($media)->thumbnail->attributes()['url'])
        ->toBe(route('psp-test.image', ['variant' => 'picture.jpg']));

    // 7 - The control item carries no image markup at all.
    expect(isset($items[6]->children($media)->thumbnail[0]))->toBeFalse()
        ->and(isset($items[6]->children($itunes)->image[0]))->toBeFalse();
});

it('serves each probe image with its own size and colour', function (
    string $variant,
    int $width,
    int $height,
    array $rgb,
): void {
    $response = $this->get('/psp-test/img/'.$variant);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');

    [$actualWidth, $actualHeight] = getimagesizefromstring((string) $response->getContent());

    expect($actualWidth)->toBe($width)
        ->and($actualHeight)->toBe($height);

    $canvas = imagecreatefromstring((string) $response->getContent());
    // A corner, not the centre: the variant number is drawn dead centre in
    // white, so the centre pixel is the label rather than the fill colour.
    $pixel = imagecolorsforindex($canvas, imagecolorat($canvas, 3, 3));

    // JPEG compression shifts a solid colour by a point or two, so the decoded
    // centre pixel is compared within a small tolerance.
    expect($pixel['red'])->toBeGreaterThanOrEqual($rgb[0] - 8)->toBeLessThanOrEqual($rgb[0] + 8)
        ->and($pixel['green'])->toBeGreaterThanOrEqual($rgb[1] - 8)->toBeLessThanOrEqual($rgb[1] + 8)
        ->and($pixel['blue'])->toBeGreaterThanOrEqual($rgb[2] - 8)->toBeLessThanOrEqual($rgb[2] + 8);
})->with([
    'sony' => ['sony.jpg', 80, 60, [220, 31, 31]],
    'box' => ['box.jpg', 120, 120, [31, 161, 63]],
    'extless without extension' => ['extless', 160, 98, [43, 60, 200]],
    'itunes' => ['itunes.jpg', 120, 120, [227, 200, 31]],
    'mcontent' => ['mcontent.jpg', 120, 120, [200, 40, 180]],
    'picture' => ['picture.jpg', 120, 120, [23, 184, 184]],
]);

it('answers 404 for a probe image that has no variant', function (): void {
    $this->get('/psp-test/img/whatever.jpg')->assertNotFound();
});

it('makes the probe feed reachable from the index page', function (): void {
    SubsonicApi::fake();
    Radios::file([]);

    expect((string) $this->get('/')->getContent())->toContain(route('psp-test.feed'));
});
