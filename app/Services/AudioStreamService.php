<?php

namespace App\Services;

use App\Data\Subsonic\Song;
use App\Exceptions\AudioProxyException;
use App\Repositories\Contracts\SongRepository;
use App\Services\Subsonic\SubsonicClient;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams audio from Navidrome to the PSP.
 *
 * The PSP only plays MP3, while the library also holds FLAC and friends. Two
 * strategies are used:
 *
 *  - an MP3 source is streamed untouched with format=raw, so the PSP gets the
 *    original bytes, full support for range requests and no transcoding cost;
 *  - anything else is converted by Navidrome to MP3 at the configured bitrate
 *    on the fly, without writing a converted copy to disk here.
 *
 * Whatever the server answers is forwarded as-is. That matters because a track
 * that is being transcoded for the first time is streamed by Navidrome without a
 * content length and without range support, until its own cache is warm, and
 * the client is better served by an honest answer than by a promised range that
 * cannot be honoured.
 */
final class AudioStreamService
{
    public function __construct(
        private readonly SongRepository $songs,
        private readonly SubsonicClient $client,
    ) {}

    /**
     * Open the stream of a track for the given Range header.
     *
     * @throws AudioProxyException when the track is gone or the server refuses.
     */
    public function stream(string $songId, ?string $rangeHeader = null): StreamedResponse
    {
        $song = $this->songs->find($songId);

        if (! $song instanceof Song) {
            throw AudioProxyException::trackNotFound();
        }

        $response = $this->client->openStream(
            'stream',
            $this->upstreamQuery($song),
            $this->upstreamHeaders($rangeHeader),
        );

        $this->guardAgainstApiError($response);

        return $this->toStreamedResponse($response);
    }

    /**
     * The query sent upstream, chosen from the format of the source file.
     *
     * @return array<string, mixed>
     */
    private function upstreamQuery(Song $song): array
    {
        if ($song->isMp3()) {
            return ['id' => $song->id, 'format' => 'raw'];
        }

        return [
            'id' => $song->id,
            'format' => 'mp3',
            'maxBitRate' => config('navidrome.psp_bitrate'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function upstreamHeaders(?string $rangeHeader): array
    {
        if ($rangeHeader === null || trim($rangeHeader) === '') {
            return [];
        }

        return ['Range' => $rangeHeader];
    }

    /**
     * Refuse to play back an API error as if it were audio.
     */
    private function guardAgainstApiError(ResponseInterface $response): void
    {
        $contentType = $response->getHeaderLine('Content-Type');

        if (! str_starts_with($contentType, 'audio/')) {
            throw AudioProxyException::notAudio($contentType, $response->getBody()->read(512));
        }
    }

    /**
     * Copy the upstream body to the client without buffering it.
     */
    private function toStreamedResponse(ResponseInterface $upstream): StreamedResponse
    {
        $chunkSize = (int) config('navidrome.stream_chunk_size');
        $status = $upstream->getStatusCode();

        $response = new StreamedResponse(function () use ($upstream, $chunkSize): void {
            $body = $upstream->getBody();

            while (! $body->eof()) {
                echo $body->read($chunkSize);

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        });

        $response->setStatusCode($status);

        // The PSP is only ever handed MP3, whatever the source format was.
        $response->headers->set('Content-Type', 'audio/mpeg');

        foreach (['Accept-Ranges', 'Content-Length', 'Content-Range'] as $header) {
            $value = $upstream->getHeaderLine($header);

            if ($value !== '') {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }
}
