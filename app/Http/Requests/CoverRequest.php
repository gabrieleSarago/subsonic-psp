<?php

namespace App\Http\Requests;

class CoverRequest extends RoutedRequest
{
    /**
     * Small enough to stay cheap on the 480x272 screen of the PSP.
     */
    public const int DEFAULT_SIZE = 100;

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
            'cover' => ['required', 'string', 'max:255'],
            'size' => ['sometimes', 'integer', 'min:16', 'max:1024'],
        ];
    }

    /**
     * The cover identifier, taken from the route.
     */
    public function coverArtId(): string
    {
        return (string) $this->route('cover');
    }

    /**
     * The requested edge length in pixels.
     */
    public function size(): int
    {
        return (int) ($this->route('size') ?? self::DEFAULT_SIZE);
    }
}
