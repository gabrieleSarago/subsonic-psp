<?php

namespace App\Services\Subsonic;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * The single place that talks to the Subsonic API of Navidrome.
 *
 * Authentication uses the salted token scheme: the password itself never
 * appears in a URL, only md5(password + salt). A fresh salt is generated for
 * every request.
 */
final class SubsonicClient
{
    /**
     * Build the URL of an endpoint, signed with a token.
     *
     * @param  array<string, mixed>  $query
     */
    public function signedUrl(string $endpoint, array $query = []): string
    {
        $salt = Str::random(32);

        return $this->url($endpoint, $query + [
            'u' => $this->username(),
            't' => md5($this->password().$salt),
            's' => $salt,
            'v' => $this->apiVersion(),
            'c' => config('navidrome.client_name'),
        ]);
    }

    /**
     * Call an endpoint and return the contents of the "subsonic-response" object.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->pendingRequest()
                ->retry(3, 200, fn ($exception) => $exception instanceof ConnectionException)
                ->get($this->signedUrl($endpoint, $query + ['f' => 'json']));
        } catch (ConnectionException $exception) {
            throw SubsonicException::connectionLost($exception->getMessage());
        }

        if ($response->failed()) {
            throw SubsonicException::unreachable("HTTP {$response->status()} su {$endpoint}");
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['subsonic-response'])) {
            throw SubsonicException::malformedResponse($endpoint);
        }

        $body = $payload['subsonic-response'];

        if (($body['status'] ?? 'ok') !== 'ok') {
            throw SubsonicException::apiError((array) ($body['error'] ?? []));
        }

        return $body;
    }

    /**
     * Call an endpoint whose target may have been removed from the library.
     *
     * A "not found" answer becomes null instead of an exception, so that a feed
     * for an album that no longer exists reads as a missing page rather than as
     * an outage.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function getOrNull(string $endpoint, array $query = []): ?array
    {
        try {
            return $this->get($endpoint, $query);
        } catch (SubsonicException $exception) {
            if ($exception->isNotFound()) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * Call an endpoint that answers with bytes rather than with a JSON document.
     *
     * Used for the artwork, where the answer is an image or an error document
     * dressed as one: the caller is the only one that can tell the two apart, so
     * a refusal from the server is passed back rather than raised here.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws SubsonicException when the server cannot be reached at all.
     */
    public function getBinary(string $endpoint, array $query = []): Response
    {
        try {
            return $this->pendingRequest()
                ->retry(3, 200, fn ($exception) => $exception instanceof ConnectionException)
                ->get($this->signedUrl($endpoint, $query));
        } catch (ConnectionException $exception) {
            throw SubsonicException::connectionLost($exception->getMessage());
        }
    }

    /**
     * Open a lazily read stream of a track, for proxying to the PSP.
     *
     * The response body is never buffered: a whole track would otherwise be
     * held in memory for the entire download.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function openStream(string $endpoint, array $query, array $headers = []): ResponseInterface
    {
        // No "f" parameter here: with f=json the server answers with a JSON
        // document instead of the audio stream.
        $url = $this->signedUrl($endpoint, $query);

        try {
            return Http::buildClient()
                ->request('GET', $url, [
                    'stream' => true,
                    'headers' => $headers,
                    'connect_timeout' => config('navidrome.connect_timeout'),
                    'timeout' => config('navidrome.stream_timeout'),
                    'http_errors' => false,
                ]);
        } catch (ConnectException|ConnectionException $exception) {
            // This call goes through the client of Guzzle rather than through the
            // one of the framework, so a refused connection arrives as the
            // exception of Guzzle. Both are the same fault, and both mean the
            // music server is not answering.
            throw SubsonicException::connectionLost($exception->getMessage());
        }
    }

    /**
     * The configured server version, used by the diagnostics command.
     */
    public function serverVersion(): string
    {
        return (string) ($this->get('ping')['serverVersion'] ?? 'sconosciuta');
    }

    /**
     * Build the URL of an endpoint on the configured host.
     *
     * @param  array<string, mixed>  $query
     */
    private function url(string $endpoint, array $query): string
    {
        $host = rtrim((string) config('navidrome.host'), '/');

        return $host.'/rest/'.$endpoint.'.view?'.http_build_query($query);
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::connectTimeout(config('navidrome.connect_timeout'))
            ->timeout(config('navidrome.timeout'))
            ->acceptJson();
    }

    private function username(): string
    {
        $username = (string) config('navidrome.username');

        if ($username === '') {
            throw SubsonicException::unreachable('SUBSONIC_USERNAME non configurata.');
        }

        return $username;
    }

    private function password(): string
    {
        $password = (string) config('navidrome.password');

        if ($password === '') {
            throw SubsonicException::unreachable('SUBSONIC_PASSWORD non configurata.');
        }

        return $password;
    }

    private function apiVersion(): string
    {
        return (string) config('navidrome.api_version');
    }
}
