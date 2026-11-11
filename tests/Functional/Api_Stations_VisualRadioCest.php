<?php

declare(strict_types=1);

namespace Functional;

use FunctionalTester;
use RuntimeException;

class Api_Stations_VisualRadioCest extends CestAbstract
{
    /**
     * @before setupComplete
     */
    public function visualRadioRequiresBroadcastingAuth(FunctionalTester $I): void
    {
        $I->wantTo('Require Broadcasting auth for Visual Radio config (no public write of restream secrets).');

        $station = $this->getTestStation();
        $station->enable_public_api = true;
        $this->em->persist($station);
        $this->em->flush();

        $url = '/api/station/' . $station->id . '/visual-radio';
        $payload = [
            'is_enabled' => true,
            'rtmp_source_url' => 'rtmp://studio.example/live',
            'video_bitrate' => 2500,
            'fps' => 30,
            'restream_targets' => [
                [
                    'name' => 'YouTube Live',
                    'url' => 'rtmp://a.rtmp.youtube.com/live2',
                    'key' => 'secret-stream-key',
                    'is_enabled' => true,
                ],
            ],
        ];

        // Anonymous / invalid API key must not read or write restream secrets even with public API on.
        $I->haveHttpHeader('Authorization', 'Bearer invalid-visual-radio-key');
        $I->haveHttpHeader('Content-Type', 'application/json');

        $I->sendGET($url);
        $I->seeResponseCodeIs(403);

        $I->sendPOST($url, $payload);
        $I->seeResponseCodeIs(403);

        // Authenticated Broadcasting-capable user (test admin has GlobalPermissions::All) can manage config.
        $apiKey = $this->login_api_key;
        if ($apiKey === null) {
            throw new RuntimeException('Expected test API key after setupComplete.');
        }
        $I->amBearerAuthenticated($apiKey);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $I->sendGET($url);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'is_enabled' => false,
        ]);

        $I->sendPOST($url, $payload);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'success' => true,
            'visual_radio' => [
                'is_enabled' => true,
                'rtmp_source_url' => 'rtmp://studio.example/live',
                'restream_targets' => [
                    [
                        'key' => 'secret-stream-key',
                    ],
                ],
            ],
        ]);

        $I->sendGET($url);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'is_enabled' => true,
            'rtmp_source_url' => 'rtmp://studio.example/live',
        ]);
    }
}
