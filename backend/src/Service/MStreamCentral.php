<?php

declare(strict_types=1);

namespace App\Service;

use App\Container\LoggerAwareTrait;
use App\Container\SettingsAwareTrait;
use App\Entity\Api\Admin\UpdateDetails;
use App\Version;
use Exception;
use GuzzleHttp\Client;

final class MStreamCentral
{
    use LoggerAwareTrait;
    use SettingsAwareTrait;

    /** Neutral public service used only to detect this installation's public-facing IP. */
    private const string IP_LOOKUP_URL = 'https://api.ipify.org?format=json';

    public function __construct(
        private readonly Client $httpClient,
    ) {
    }

    /**
     * Return local version details without contacting an external update server.
     */
    public function checkForUpdates(): UpdateDetails
    {
        return new UpdateDetails(
            current_release: Version::STABLE_VERSION,
            latest_release: Version::STABLE_VERSION,
        );
    }

    public function getUniqueIdentifier(): string
    {
        return $this->readSettings()->app_unique_identifier;
    }

    /**
     * Retrieve this installation's likely public-facing IP.
     *
     * @param bool $cached
     */
    public function getIp(bool $cached = true): ?string
    {
        $settings = $this->readSettings();
        $ip = ($cached)
            ? $settings->external_ip
            : null;

        if (empty($ip)) {
            try {
                $response = $this->httpClient->request(
                    'GET',
                    self::IP_LOOKUP_URL
                );

                $bodyRaw = $response->getBody()->getContents();
                $body = json_decode($bodyRaw, true, 512, JSON_THROW_ON_ERROR);

                $ip = $body['ip'] ?? null;
            } catch (Exception $e) {
                $this->logger->error('Could not fetch remote IP: ' . $e->getMessage());
                $ip = null;
            }

            if (!empty($ip) && $cached) {
                $settings->external_ip = $ip;
                $this->writeSettings($settings);
            }
        }

        return $ip;
    }
}
