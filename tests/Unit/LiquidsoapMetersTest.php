<?php

declare(strict_types=1);

namespace Unit;

use App\Radio\Backend\Liquidsoap;
use Codeception\Test\Unit;

final class LiquidsoapMetersTest extends Unit
{
    public function testParseMetersResponseReadsJsonPayload(): void
    {
        $meters = Liquidsoap::parseMetersResponse([
            '{"vu_l":-12.5,"vu_r":-13.1,"peak_l":-1.8,"peak_r":-2.2}',
            'END',
        ]);

        self::assertTrue($meters['available']);
        self::assertSame(-12.5, $meters['vu_l']);
        self::assertSame(-13.1, $meters['vu_r']);
        self::assertSame(-1.8, $meters['peak_l']);
        self::assertSame(-2.2, $meters['peak_r']);
    }

    public function testParseMetersResponseClampsAndFallsBack(): void
    {
        $meters = Liquidsoap::parseMetersResponse([
            '{"vu_l":5,"vu_r":"-inf","peak_l":"nope","peak_r":-120}',
        ]);

        self::assertTrue($meters['available']);
        self::assertSame(0.0, $meters['vu_l']);
        self::assertSame(-90.0, $meters['vu_r']);
        self::assertSame(-90.0, $meters['peak_l']);
        self::assertSame(-90.0, $meters['peak_r']);
    }

    public function testParseMetersResponseReturnsSilentOnInvalidPayload(): void
    {
        $meters = Liquidsoap::parseMetersResponse([
            'not-json',
            'END',
        ]);

        self::assertSame(Liquidsoap::silentMeters(), $meters);
    }
}
