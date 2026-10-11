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
     * pixels, so the bytes fetched from the site are re-encoded before they
     * are served. A neutral one pixel image is sent instead of an error, so a
     * missing icon never leaves a broken frame in the middle of a feed.
     */
    public function show(RadioIconRequest $request): Response
    {
        $icon = $this->icons->find($request->stationId());

        $jpeg = $icon instanceof FaviconImage
            ? $this->thumbnailer->jpegFromBytes($icon->bytes)
            : null;

        if ($jpeg === null) {
            return $this->placeholder();
        }

        return $this->image($jpeg, 'image/jpeg');
    }

    private function placeholder(): Response
    {
        return $this->image(
            base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==', true) ?: '',
            'image/jpeg',
        );
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
