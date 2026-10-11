<?php

namespace App\Http\Controllers;

use App\Data\Subsonic\CoverImage;
use App\Http\Requests\CoverRequest;
use App\Repositories\Contracts\CoverRepository;
use App\Support\Thumbnailer;
use Symfony\Component\HttpFoundation\Response;

class CoverController extends Controller
{
    /**
     * The edge length the server is asked for the art of a feed item thumbnail.
     *
     * The PSP displays item images at no more than 160 by 120 pixels, so asking
     * for a square of this side is enough and keeps the transfer small.
     */
    private const int THUMBNAIL_SOURCE_SIZE = 160;

    public function __construct(
        private readonly CoverRepository $covers,
        private readonly Thumbnailer $thumbnailer,
    ) {}

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

    /**
     * Proxy a cover as the thumbnail the feed items use.
     *
     * The PSP draws an item image only from a JPEG no larger than 160 by 120
     * pixels, so the bytes from the server are re-encoded before they are
     * served, whatever their original format was.
     */
    public function thumb(CoverRequest $request): Response
    {
        $cover = $this->covers->find($request->coverArtId(), self::THUMBNAIL_SOURCE_SIZE);

        $jpeg = $cover instanceof CoverImage
            ? $this->thumbnailer->jpegFromBytes($cover->bytes)
            : null;

        if ($jpeg === null) {
            return $this->jpegPlaceholder();
        }

        return $this->image($jpeg, 'image/jpeg');
    }

    private function placeholder(): Response
    {
        return $this->image(
            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true) ?: '',
            'image/gif',
        );
    }

    private function jpegPlaceholder(): Response
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
