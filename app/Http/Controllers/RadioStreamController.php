<?php

namespace App\Http\Controllers;

use App\Http\Requests\RadioStreamRequest;
use App\Services\Radio\RadioStreamService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RadioStreamController extends Controller
{
    public function __construct(private readonly RadioStreamService $radio) {}

    /**
     * Stream a station to the PSP as MP3.
     */
    public function show(RadioStreamRequest $request): Response
    {
        // Kept while the device's stream behaviour is still being pinned down:
        // the request it makes (version, range, agent) is the only way to see
        // how it opens an enclosure.
        Log::info('radio stream request', [
            'station' => $request->stationId(),
            'method' => $request->method(),
            'protocol' => $request->getProtocolVersion(),
            'range' => $request->header('Range'),
            'user_agent' => $request->userAgent(),
            'accept' => $request->header('Accept'),
            'connection' => $request->header('Connection'),
        ]);

        // A HEAD request asks for the size, not for the audio: answer it from
        // the headers alone, so no transcode is started just to be discarded.
        if ($request->isMethod('HEAD')) {
            return new Response('', Response::HTTP_OK, [
                'Content-Type' => 'audio/mpeg',
                'Content-Length' => (string) $this->radio->declaredLength(),
                'Accept-Ranges' => 'bytes',
            ]);
        }

        return $this->radio->stream($request->stationId(), $request->rangeHeader());
    }
}
