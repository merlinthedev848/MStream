<?php

declare(strict_types=1);

namespace Unit;

use App\Doctrine\ReloadableEntityManagerInterface;
use App\Entity\Station;
use App\Environment;
use App\Radio\Adapters;
use App\Radio\AutoDJ\PlaylistQueueResetter;
use App\Radio\Configuration;
use App\Radio\Enums\BackendAdapters;
use App\Radio\Enums\FrontendAdapters;
use Codeception\Test\Unit;
use Mockery;
use ReflectionProperty;
use Supervisor\SupervisorInterface;

/**
 * Ascending port blocks: base, base+4 telnet, base+5 DJ (+6 reserved).
 */
final class ConfigurationPortAllocationTest extends Unit
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAssignRadioPortsUsesAscendingBlocksWithoutGaps(): void
    {
        $configuration = $this->createConfigurationWithEmptyPortCache();

        $stations = [];
        $expectedBases = [
            Configuration::DEFAULT_PORT_MIN,
            Configuration::DEFAULT_PORT_MIN + Configuration::PORT_RANGE_STEP,
            Configuration::DEFAULT_PORT_MIN + (2 * Configuration::PORT_RANGE_STEP),
        ];

        foreach ($expectedBases as $index => $expectedBase) {
            $station = new Station();
            $station->name = 'Port Station ' . ($index + 1);
            $station->frontend_type = FrontendAdapters::Icecast;
            $station->backend_type = BackendAdapters::Liquidsoap;

            $configuration->assignRadioPorts($station);
            $stations[] = $station;

            self::assertSame($expectedBase, $station->frontend_config->port);
            self::assertSame(
                $expectedBase + Configuration::PORT_OFFSET_TELNET,
                $station->backend_config->telnet_port
            );
            self::assertSame(
                $expectedBase + Configuration::PORT_OFFSET_DJ,
                $station->backend_config->dj_port
            );
        }

        // Force-reassign the middle station: still the same block (except itself), not a random hole.
        $configuration->assignRadioPorts($stations[1], true);
        self::assertSame(
            Configuration::DEFAULT_PORT_MIN + Configuration::PORT_RANGE_STEP,
            $stations[1]->frontend_config->port
        );
    }

    public function testGetFirstAvailableRadioPortSkipsOccupiedAscendingBlocks(): void
    {
        $configuration = $this->createConfigurationWithEmptyPortCache();

        $first = new Station();
        $first->name = 'First';
        $first->frontend_type = FrontendAdapters::Icecast;
        $first->backend_type = BackendAdapters::Liquidsoap;
        $configuration->assignRadioPorts($first);

        $next = $configuration->getFirstAvailableRadioPort();
        self::assertSame(
            Configuration::DEFAULT_PORT_MIN + Configuration::PORT_RANGE_STEP,
            $next
        );
    }

    public function testEnumerateDefaultPortsMatchesBlockOffsets(): void
    {
        $ports = Configuration::enumerateDefaultPorts(8000, 8020);

        self::assertSame(
            [
                8000,
                8000 + Configuration::PORT_OFFSET_DJ,
                8000 + Configuration::PORT_OFFSET_DJ + 1,
                8010,
                8010 + Configuration::PORT_OFFSET_DJ,
                8010 + Configuration::PORT_OFFSET_DJ + 1,
            ],
            $ports
        );
    }

    private function createConfigurationWithEmptyPortCache(): Configuration
    {
        $em = Mockery::mock(ReloadableEntityManagerInterface::class);
        $em->shouldReceive('persist');
        // Port tests seed usedPorts via reflection — createQuery must not be required.
        $em->shouldReceive('createQuery')->never();

        $configuration = new Configuration(
            Mockery::mock(Adapters::class),
            Mockery::mock(SupervisorInterface::class),
            Mockery::mock(PlaylistQueueResetter::class),
        );
        $configuration->setEntityManager($em);
        $configuration->setEnvironment(new Environment([]));

        $usedPorts = new ReflectionProperty(Configuration::class, 'usedPorts');
        $usedPorts->setValue($configuration, []);

        return $configuration;
    }
}
