<?php

namespace App\Http\Requests;

class PlaylistFeedRequest extends RoutedRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'playlist' => ['required', 'string', 'max:255'],
            'part' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * The playlist identifier, taken from the route.
     */
    public function playlistId(): string
    {
        return (string) $this->route('playlist');
    }

    /**
     * The requested part, defaulting to the first one.
     */
    public function part(): int
    {
        return (int) ($this->route('part') ?? 1);
    }
}
