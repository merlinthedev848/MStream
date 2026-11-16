<?php

declare(strict_types=1);

namespace Unit;

use App\Utilities\InstanceBranding;
use Codeception\Test\Unit;

class InstanceBrandingTest extends Unit
{
    public function testNormalizeStripsSeparatorsAndCase(): void
    {
        self::assertSame('vauradio', InstanceBranding::normalize('VAU Radio'));
        self::assertSame('vauradio', InstanceBranding::normalize('VAURadio'));
        self::assertSame('vauradio', InstanceBranding::normalize('vau_radio'));
        self::assertSame('vauradio', InstanceBranding::normalize(' VAU-Radio '));
        self::assertSame('', InstanceBranding::normalize(null));
        self::assertSame('', InstanceBranding::normalize(''));
    }

    public function testConflictsWithStationLabels(): void
    {
        $labels = ['VAU Radio', 'default_radio', 'Acme Media'];

        self::assertTrue(InstanceBranding::conflictsWithStationLabels('VAURadio', $labels));
        self::assertTrue(InstanceBranding::conflictsWithStationLabels('vau-radio', $labels));
        self::assertTrue(InstanceBranding::conflictsWithStationLabels('Acme Media', $labels));
        self::assertFalse(InstanceBranding::conflictsWithStationLabels('MStream Hosting', $labels));
        self::assertFalse(InstanceBranding::conflictsWithStationLabels('MStream', $labels));
        self::assertFalse(InstanceBranding::conflictsWithStationLabels('', $labels));
    }
}
