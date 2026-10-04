<?php

namespace App\Http\Controllers;

use App\Data\Subsonic\CoverImage;
use App\Http\Requests\CoverRequest;
use App\Repositories\Contracts\CoverRepository;
use Symfony\Component\HttpFoundation\Response;

class CoverController extends Controller
{
    public function __construct(private readonly CoverRepository $covers) {}

    /**
     * Proxy a cover image.
     *
     * Serves a neutral one pixel image instead of an error, so a missing piece of
     * artwork never leaves a broken frame in the middle of the index page.
     */
    public function show(CoverRequest $request): Response
    {
        $cover = $this->covers->find($request->coverArtId(), $request->size());

        if (! $cover instanceof CoverImage) {
            return $this->placeholder();
        }

        return $this->image($cover->bytes, $cover->contentType);
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
