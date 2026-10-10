<?php

namespace App\Services\Radio;

use Illuminate\Support\Facades\File;

/**
 * Reads and rewrites the JSON file that holds the curated station list.
 *
 * The file is edited by hand, so a write must never lose a field the writer
 * does not know about: rows are kept as the arrays they were read from.
 * Writing goes to a temporary file that is renamed over the original, so a
 * probe interrupted halfway never leaves a truncated list behind.
 */
final class RadioListStore
{
    public function __construct(private readonly string $path) {}

    /**
     * Every row of the file, untouched.
     *
     * @return list<array<string, mixed>>
     */
    public function read(): array
    {
        if (! File::exists($this->path)) {
            return [];
        }

        $payload = json_decode((string) File::get($this->path), true);

        if (! is_array($payload)) {
            return [];
        }

        return array_values(array_filter($payload, 'is_array'));
    }

    /**
     * Replace the file with the given rows, atomically.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function write(array $rows): void
    {
        $json = json_encode(
            $rows,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ).PHP_EOL;

        File::ensureDirectoryExists(dirname($this->path));

        $temporary = $this->path.'.'.bin2hex(random_bytes(4)).'.tmp';
        File::put($temporary, $json);
        File::move($temporary, $this->path);
    }
}
