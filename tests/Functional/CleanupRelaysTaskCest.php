<?php

declare(strict_types=1);

namespace Functional;

use App\Entity\Relay;
use App\Sync\Task\CleanupRelaysTask;
use App\Utilities\Time;
use FunctionalTester;

final class CleanupRelaysTaskCest extends CestAbstract
{
    private const string RELAY_BASE_URL = 'http://relay.example.test';

    /**
     * @before setupComplete
     */
    public function heartbeatKeepsRelayFreshAgainstCleanup(FunctionalTester $I): void
    {
        $I->wantTo(
            'Keep a live MStreamRelay after CleanupRelaysTask when the heartbeat repeats unchanged fields.'
        );

        $this->postRelayHeartbeat($I);
        $I->seeResponseCodeIs(200);

        $relayId = $this->assertRelayExists($I);

        $this->ageRelayUpdatedAt($relayId, 120);
        $this->postRelayHeartbeat($I);
        $I->seeResponseCodeIs(200);

        $this->em->clear();
        $this->di->get(CleanupRelaysTask::class)->run(true);
        $this->em->clear();

        $I->assertInstanceOf(
            Relay::class,
            $this->em->find(Relay::class, $relayId),
            'Heartbeat must bump updated_at so CleanupRelaysTask does not delete a live relay.'
        );
    }

    /**
     * @before setupComplete
     */
    public function cleanupRemovesStaleRelayWithoutHeartbeat(FunctionalTester $I): void
    {
        $I->wantTo('Delete a relay whose updated_at is older than the CleanupRelaysTask threshold.');

        $this->postRelayHeartbeat($I);
        $I->seeResponseCodeIs(200);

        $relayId = $this->assertRelayExists($I);
        $this->ageRelayUpdatedAt($relayId, 120);

        $this->em->clear();
        $this->di->get(CleanupRelaysTask::class)->run(true);
        $this->em->clear();

        $I->assertNull(
            $this->em->find(Relay::class, $relayId),
            'CleanupRelaysTask should still remove relays that miss the heartbeat window.'
        );
    }

    private function postRelayHeartbeat(FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/internal/relays', [
            'base_url' => self::RELAY_BASE_URL,
            'name' => 'Relay',
            'is_visible_on_public_pages' => true,
            'nowplaying' => [],
        ]);
    }

    private function assertRelayExists(FunctionalTester $I): int
    {
        $this->em->clear();

        $relay = $this->em->getRepository(Relay::class)->findOneBy([
            'base_url' => self::RELAY_BASE_URL,
        ]);

        $I->assertInstanceOf(Relay::class, $relay);

        return $relay->id;
    }

    private function ageRelayUpdatedAt(int $relayId, int $secondsAgo): void
    {
        // Bypass Relay::preUpdate, which would otherwise rewrite updated_at to now.
        $this->em->createQuery(
            <<<'DQL'
                UPDATE App\Entity\Relay r
                SET r.updated_at = :stale
                WHERE r.id = :id
            DQL
        )->setParameter('stale', Time::nowUtc()->subSeconds($secondsAgo))
            ->setParameter('id', $relayId)
            ->execute();

        $this->em->clear();
    }
}
