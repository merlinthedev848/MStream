<?php

declare(strict_types=1);

namespace App\Controller\Api\Stations\Media;

use App\Container\EntityManagerAwareTrait;
use App\Entity\Station;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Dynamic Audio Ad Insertion (DAI / VAST) Controller:
 * Handles VAST 2.0/3.0/4.0 XML request generation and pre-roll/mid-roll ad injection metadata.
 */
#[
    OA\Get(
        path: '/station/{station_id}/vast',
        operationId: 'getStationVastAd',
        summary: 'Return a standard VAST XML response for audio pre-roll or mid-roll ad delivery.',
        tags: [OpenApi::TAG_STATIONS],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
            new OA\Parameter(
                name: 'format',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'mp3')
            ),
        ],
        responses: [
            new OpenApi\Response\Success(description: 'VAST 3.0 Audio XML schema'),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class VastAdController
{
    use EntityManagerAwareTrait;

    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $station = $request->getStation();
        $backendConfig = $station->backend_config->toArray();
        $adConfig = is_array($backendConfig['dai_advertising'] ?? null)
            ? $backendConfig['dai_advertising']
            : [];

        $adTitle = !empty($adConfig['ad_title'])
            ? htmlspecialchars((string)$adConfig['ad_title'])
            : 'MStream Audio Sponsor';
        $mediaFile = !empty($adConfig['media_url'])
            ? htmlspecialchars((string)$adConfig['media_url'])
            : '';
        $clickThrough = !empty($adConfig['click_url'])
            ? htmlspecialchars((string)$adConfig['click_url'])
            : 'https://github.com/merlinthedev848/MStream';
        $duration = !empty($adConfig['duration'])
            ? htmlspecialchars((string)$adConfig['duration'])
            : '00:00:15';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<VAST version="3.0">
  <Ad id="mstream-ad-{$station->id}">
    <InLine>
      <AdSystem version="1.0">MStream DAI</AdSystem>
      <AdTitle>{$adTitle}</AdTitle>
      <Creatives>
        <Creative id="creative-{$station->id}" sequence="1">
          <Linear>
            <Duration>{$duration}</Duration>
            <MediaFiles>
              <MediaFile id="media-1" delivery="progressive" type="audio/mpeg" bitrate="128" width="0" height="0">
                <![CDATA[{$mediaFile}]]>
              </MediaFile>
            </MediaFiles>
            <VideoClicks>
              <ClickThrough>
                <![CDATA[{$clickThrough}]]>
              </ClickThrough>
            </VideoClicks>
          </Linear>
        </Creative>
      </Creatives>
    </InLine>
  </Ad>
</VAST>
XML;

        $response->getBody()->write($xml);

        return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }
}
