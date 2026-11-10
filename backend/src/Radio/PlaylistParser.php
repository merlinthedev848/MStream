<?php

declare(strict_types=1);

namespace App\Radio;

final class PlaylistParser
{
    /**
     * @return string[]
     */
    public static function getSongs(string $playlistRaw): array
    {
        $trimmedRaw = trim($playlistRaw);

        // Process as full PLS if the header is present.
        if (str_starts_with($trimmedRaw, '[playlist]')) {
            $parsedPlaylist = parse_ini_string($trimmedRaw, true, INI_SCANNER_RAW);

            if (is_array($parsedPlaylist) && isset($parsedPlaylist['playlist']) && is_array($parsedPlaylist['playlist'])) {
                return array_values(
                    array_filter(
                        $parsedPlaylist['playlist'],
                        static function ($key) {
                            return str_starts_with(strtolower((string)$key), 'file');
                        },
                        ARRAY_FILTER_USE_KEY
                    )
                );
            }
        }

        // Process as a simple list of files or M3U-style playlist.
        $lines = preg_split(
            "/[\r\n]+/",        // regex supports Windows, Linux/Unix & Old Macs EOL's
            $playlistRaw,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
        if (false === $lines) {
            return [];
        }
        return array_values(
            array_filter(
                array_map('trim', $lines),
                static function ($line) {
                    return $line !== '' && $line[0] !== '#';
                }
            )
        );
    }
}
