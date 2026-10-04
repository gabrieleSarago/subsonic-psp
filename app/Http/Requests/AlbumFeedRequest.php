<?php

namespace App\Http\Requests;

class AlbumFeedRequest extends RoutedRequest
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
            'album' => ['required', 'string', 'max:255'],
            'part' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * The album identifier, taken from the route.
     */
    public function albumId(): string
    {
        return (string) $this->route('album');
    }

    /**
     * The requested part, defaulting to the first one.
     */
    public function part(): int
    {
        return (int) ($this->route('part') ?? 1);
    }
}
