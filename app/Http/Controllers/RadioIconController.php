<?php

namespace App\Http\Controllers;

use App\Data\Radio\FaviconImage;
use App\Http\Requests\RadioIconRequest;
use App\Repositories\Contracts\FaviconRepository;
use App\Support\Thumbnailer;
use Symfony\Component\HttpFoundation\Response;

class RadioIconController extends Controller
{
    public function __construct(
        private readonly FaviconRepository $icons,
        private readonly Thumbnailer $thumbnailer,
    ) {}

    /**
     * Proxy the favicon of a station as the thumbnail the feed items use.
     *
     * The PSP draws an item image only from a JPEG no larger than 160 by 120
     * pixels, so the bytes fetched from the site are re-encoded before they are
     * served. When there is no favicon the answer is a 404: the device then
     * draws its own default item icon, which looks better than a blank frame
     * and is not cached for a week.
     */
    public function show(RadioIconRequest $request): Response
    {
        $icon = $this->icons->find($request->stationId());

        $jpeg = $icon instanceof FaviconImage
            ? $this->thumbnailer->jpegFromBytes($icon->bytes)
            : null;

        if ($jpeg === null) {
            abort(404);
        }

        return $this->image($jpeg, 'image/jpeg');
    }

    private function image(string $bytes, string $contentType): Response
    {
        return response($bytes, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
