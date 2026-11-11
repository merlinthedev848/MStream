<?php

declare(strict_types=1);

namespace App\Radio\Backend\Liquidsoap\Command;

use App\Entity\Station;
use App\Radio\AutoDJ\Annotations;

final class NextSongCommand extends AbstractCommand
{
    public function __construct(
        private readonly Annotations $annotations
    ) {
    }

    protected function doRun(
        Station $station,
        bool $asAutoDj = false,
        array $payload = []
    ): array {
        // Empty string is a structured soft-fail for an empty AutoDJ queue.
        // Liquidsoap treats blank/null nextsong annotations as "no request".
        return [
            'uri' => $this->annotations->annotateNextSong($station, $asAutoDj),
        ];
    }
}
