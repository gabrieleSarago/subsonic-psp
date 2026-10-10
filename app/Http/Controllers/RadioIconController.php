<?php

namespace App\Http\Controllers;

use App\Data\Radio\FaviconImage;
use App\Http\Requests\RadioIconRequest;
use App\Repositories\Contracts\FaviconRepository;
use Symfony\Component\HttpFoundation\Response;

class RadioIconController extends Controller
{
    public function __construct(private readonly FaviconRepository $icons) {}

    /**
     * Proxy the favicon of a station.
     *
     * Serves a neutral one pixel image instead of an error, so a missing icon
     * never leaves a broken frame in the middle of the index page or of a
     * feed.
     */
    public function show(RadioIconRequest $request): Response
    {
        $icon = $this->icons->find($request->stationId());

        if (! $icon instanceof FaviconImage) {
            return $this->placeholder();
        }

        return $this->image($icon->bytes, $icon->contentType);
    }

    private function placeholder(): Response
    {
        return $this->image(
            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true) ?: '',
            'image/gif',
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
