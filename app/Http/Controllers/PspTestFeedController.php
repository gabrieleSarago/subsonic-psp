<?php

namespace App\Http\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * A discriminating probe feed for the PSP item icons.
 *
 * Every item isolates one markup variant from the rest, and the thumbnails
 * that are meant to be drawn are solid colours, so a look at the device tells
 * which variant, if any, the PSP honours. The last items ask a second
 * question: what the PSP draws, if anything, when the thumbnail URL answers
 * badly. The audio enclosures point nowhere: the feed asks for nothing but a
 * glance at the icons in the item list.
 *
 * This is debug scaffolding. Delete the routes and this controller once the
 * item icon question is settled.
 */
final class PspTestFeedController extends Controller
{
    /**
     * The image of every variant, as width, height, RGB and a label.
     *
     * The label is the item number, so the same number is seen on the probe
     * feed row and inside its thumbnail, which makes the comparison on the
     * device unambiguous.
     *
     * @var array<string, array{0: int, 1: int, 2: array{0: int, 1: int, 2: int}, 3: string}>
     */
    private const array IMAGES = [
        'sony' => [80, 60, [220, 31, 31], '1'],
        'box' => [120, 120, [31, 161, 63], '2'],
        'extless' => [160, 98, [43, 60, 200], '3'],
        'itunes' => [120, 120, [227, 200, 31], '4'],
        'mcontent' => [120, 120, [200, 40, 180], '5'],
        'picture' => [120, 120, [23, 184, 184], '6'],
    ];

    public function feed(): Response
    {
        $image = fn (string $name): string => route('psp-test.image', ['variant' => $name]);
        $audio = fn (int $number): string => url('psp-test/audio/'.$number.'.mp3');
        $ago = fn (int $minutes): CarbonInterface => Carbon::now()->subMinutes($minutes);

        $items = [
            [
                'id' => 'probe-1',
                'title' => '1 · Sony, URL .jpg, 80x60 dichiarati',
                'summary' => 'Come nella guida Sony: estensione .jpg e dimensioni dichiarate uguali al reale.',
                'updated' => $ago(1),
                'enclosure' => $audio(1),
                'enclosureLength' => 100000,
                'thumb' => ['url' => $image('sony.jpg'), 'width' => 80, 'height' => 60],
            ],
            [
                'id' => 'probe-2',
                'title' => '2 · URL .jpg, 160x120 dichiarati',
                'summary' => 'Estensione .jpg ma dimensioni dichiarate al massimo, immagine reale 120x120.',
                'updated' => $ago(2),
                'enclosure' => $audio(2),
                'enclosureLength' => 100000,
                'thumb' => ['url' => $image('box.jpg'), 'width' => 160, 'height' => 120],
            ],
            [
                'id' => 'probe-3',
                'title' => '3 · senza estensione, 160x120',
                'summary' => 'La forma delle radio ora: URL senza estensione, dimensioni dichiarate al massimo.',
                'updated' => $ago(3),
                'enclosure' => $audio(3),
                'enclosureLength' => 100000,
                'thumb' => ['url' => $image('extless'), 'width' => 160, 'height' => 120],
            ],
            [
                'id' => 'probe-4',
                'title' => '4 · solo itunes:image',
                'summary' => 'Nessun media:thumbnail: solo <itunes:image href> dentro l\'item.',
                'updated' => $ago(4),
                'enclosure' => $audio(4),
                'enclosureLength' => 100000,
                'itunesImage' => $image('itunes.jpg'),
            ],
            [
                'id' => 'probe-5',
                'title' => '5 · thumbnail dentro media:content',
                'summary' => 'Il media:thumbnail è annidato dentro <media:content> invece che nell\'item.',
                'updated' => $ago(5),
                'enclosure' => $audio(5),
                'enclosureLength' => 100000,
                'mediaContent' => [
                    'url' => $audio(5),
                    'type' => 'audio/mpeg',
                    'thumb' => ['url' => $image('mcontent.jpg'), 'width' => 160, 'height' => 120],
                ],
            ],
            [
                'id' => 'probe-6',
                'title' => '6 · item immagine (enclosure jpeg)',
                'summary' => 'Enclosure image/jpeg, l\'unico tipo che la PSP illustra di sicuro nella lista.',
                'updated' => $ago(6),
                'enclosure' => $image('picture.jpg'),
                'enclosureType' => 'image/jpeg',
                'thumb' => ['url' => $image('picture.jpg'), 'width' => 160, 'height' => 120],
            ],
            [
                'id' => 'probe-7',
                'title' => '7 · nessuna icona (controllo)',
                'summary' => 'Nessun elemento immagine: deve mostrare l\'icona di default.',
                'updated' => $ago(7),
                'enclosure' => $audio(7),
                'enclosureLength' => 100000,
            ],
            [
                'id' => 'probe-8',
                'title' => '8 · thumbnail risponde 404',
                'summary' => 'L\'URL della miniatura non esiste: la PSP ricade sull\'icona di default?',
                'updated' => $ago(8),
                'enclosure' => $audio(8),
                'enclosureLength' => 100000,
                'thumb' => ['url' => $image('non-esiste.jpg'), 'width' => 160, 'height' => 120],
            ],
            [
                'id' => 'probe-9',
                'title' => '9 · thumbnail 200 ma HTML',
                'summary' => 'L\'URL risponde 200 con una pagina HTML invece che con un\'immagine.',
                'updated' => $ago(9),
                'enclosure' => $audio(9),
                'enclosureLength' => 100000,
                'thumb' => ['url' => route('psp-test.bad', ['kind' => 'html']), 'width' => 160, 'height' => 120],
            ],
            [
                'id' => 'probe-10',
                'title' => '10 · thumbnail PNG (formato sbagliato)',
                'summary' => 'L\'URL risponde con una PNG valida e arancione, ma la PSP legge solo JPEG.',
                'updated' => $ago(10),
                'enclosure' => $audio(10),
                'enclosureLength' => 100000,
                'thumb' => ['url' => route('psp-test.bad', ['kind' => 'png']), 'width' => 160, 'height' => 120],
            ],
            [
                'id' => 'probe-11',
                'title' => '11 · thumbnail JPEG corrotto',
                'summary' => 'L\'URL dichiara image/jpeg ma i byte non sono un JPEG valido.',
                'updated' => $ago(11),
                'enclosure' => $audio(11),
                'enclosureLength' => 100000,
                'thumb' => ['url' => route('psp-test.bad', ['kind' => 'broken']), 'width' => 160, 'height' => 120],
            ],
        ];

        $html = view('feeds.test', [
            'meta' => [
                'title' => 'Prova icone PSP',
                'description' => '7 marche inviate, ciascuna con un markup diverso: i colori dicono quale funziona.',
                'link' => 'psp-test/feed',
            ],
            'items' => $items,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Length' => (string) strlen($html),
        ]);
    }

    /**
     * Serve the solid colour image of a variant.
     *
     * The colour is not cached, so an iteration of the probe never shows a
     * stale thumbnail.
     */
    public function image(string $variant): Response
    {
        $spec = $this->spec($variant);

        if ($spec === null) {
            abort(404);
        }

        [$width, $height, $rgb, $label] = $spec;

        $canvas = $this->canvas($width, $height, $rgb, $label);

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = (string) ob_get_clean();

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) strlen($jpeg),
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Answer the failure probe with a deliberately unusable thumbnail.
     *
     * Each kind answers badly in a different way, so the device shows its hand
     * on the two questions that matter: does a bad response fall back to the
     * default icon, and does it draw a broken frame?
     */
    public function bad(string $kind): Response
    {
        if ($kind === 'html') {
            return response('<html><body>non un\'immagine</body></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-cache',
            ]);
        }

        if ($kind === 'png') {
            $canvas = $this->canvas(120, 120, [245, 130, 20], '10');

            ob_start();
            imagepng($canvas);
            $png = (string) ob_get_clean();

            return response($png, 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => (string) strlen($png),
                'Cache-Control' => 'no-cache',
            ]);
        }

        if ($kind === 'broken') {
            $bytes = 'this is not a jpeg';

            return response($bytes, 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'no-cache',
            ]);
        }

        abort(404);
    }

    /**
     * Draw a solid colour image with the variant number printed in its middle.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function canvas(int $width, int $height, array $rgb, string $label): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if (! $canvas instanceof \GdImage) {
            abort(500);
        }

        imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]));

        $labelWidth = imagefontwidth(5) * strlen($label);
        $labelHeight = imagefontheight(5);
        imagestring(
            $canvas,
            5,
            max(0, intdiv($width - $labelWidth, 2)),
            max(0, intdiv($height - $labelHeight, 2)),
            $label,
            imagecolorallocate($canvas, 255, 255, 255),
        );

        return $canvas;
    }

    /**
     * The spec of a requested variant, resolving the optional .jpg suffix.
     *
     * The URL of the "extless" variant deliberately lacks the suffix, so the
     * same route serves an image with and without an extension.
     *
     * @return array{0: int, 1: int, 2: array{0: int, 1: int, 2: int}, 3: string}|null
     */
    private function spec(string $variant): ?array
    {
        $name = str_ends_with($variant, '.jpg') ? substr($variant, 0, -4) : $variant;

        return self::IMAGES[$name] ?? null;
    }
}
