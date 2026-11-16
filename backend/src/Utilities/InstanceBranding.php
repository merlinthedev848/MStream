<?php

declare(strict_types=1);

namespace App\Utilities;

/**
 * Panel/instance branding helpers.
 *
 * Global login and Admin Console chrome must use the system/instance brand only.
 * Station and customer names belong on station console / public player pages.
 */
final class InstanceBranding
{
    /**
     * Normalize a display name for collision checks (case/spacing/punctuation-insensitive).
     */
    public static function normalize(?string $name): string
    {
        if (null === $name || '' === $name) {
            return '';
        }

        $normalized = preg_replace('/[\s_\-]+/u', '', mb_strtolower(trim($name)));

        return is_string($normalized) ? $normalized : '';
    }

    /**
     * True when $instanceName matches any provided station/customer label after normalization.
     *
     * @param iterable<string|null> $stationLabels
     */
    public static function conflictsWithStationLabels(string $instanceName, iterable $stationLabels): bool
    {
        $needle = self::normalize($instanceName);
        if ('' === $needle) {
            return false;
        }

        // Reserved product brand is always allowed as instance name.
        if ('mstream' === $needle) {
            return false;
        }

        foreach ($stationLabels as $label) {
            if ($needle === self::normalize($label)) {
                return true;
            }
        }

        return false;
    }
}
