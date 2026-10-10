<?php

namespace Tests\Support;

use App\Services\Radio\RadioListStore;
use Illuminate\Support\Facades\Cache;

/**
 * A station list on disk, for a test to point the application at.
 *
 * The real file lives in storage and is edited by hand, so tests write their
 * own copy in a temporary directory and leave the committed one alone.
 */
final class Radios
{
    public static ?string $path = null;

    /**
     * Write a station list and make the application read it.
     *
     * @param  array<int, array<string, mixed>>  $stations
     */
    public static function file(array $stations): string
    {
        self::cleanup();

        self::$path = sys_get_temp_dir().'/psp-radios-'.bin2hex(random_bytes(6)).'.json';

        file_put_contents(
            self::$path,
            json_encode($stations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        config(['radios.file' => self::$path]);

        // The store holds the path it was built with, and the repository caches
        // the list it read, so both are dropped for the new file to be seen.
        app()->forgetInstance(RadioListStore::class);
        Cache::flush();

        return self::$path;
    }

    /**
     * The rows currently on disk, as the probe command would read them.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function read(): array
    {
        if (self::$path === null || ! is_file(self::$path)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents(self::$path), true);

        return is_array($payload) ? $payload : [];
    }

    public static function cleanup(): void
    {
        if (self::$path !== null && is_file(self::$path)) {
            unlink(self::$path);
        }

        self::$path = null;
    }
}
