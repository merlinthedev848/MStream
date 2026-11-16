<?php

declare(strict_types=1);

namespace App\Radio\Enums;

use App\Radio\Frontend\AbstractFrontend;
use App\Radio\Frontend\Flussonic;
use App\Radio\Frontend\Icecast;
use App\Radio\Frontend\NginxRtmp;
use App\Radio\Frontend\Rsas;
use App\Radio\Frontend\Shoutcast;
use App\Radio\Frontend\Shoutcast1;
use App\Radio\Frontend\Wowza;
use OpenApi\Attributes as OA;

#[OA\Schema(type: 'string')]
enum FrontendAdapters: string implements AdapterTypeInterface
{
    case Icecast = 'icecast';
    case Shoutcast = 'shoutcast2';
    case Shoutcast1 = 'shoutcast1';
    case Rsas = 'rsas';
    case NginxRtmp = 'nginx_rtmp';
    case Flussonic = 'flussonic';
    case Wowza = 'wowza';
    case Remote = 'remote';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getName(): string
    {
        return match ($this) {
            self::Icecast => 'Icecast 2',
            self::Shoutcast => 'SHOUTcast 2',
            self::Shoutcast1 => 'SHOUTcast 1',
            self::Rsas => 'Rocket Streaming Audio Server (RSAS)',
            self::NginxRtmp => 'Nginx RTMP',
            self::Flussonic => 'Flussonic',
            self::Wowza => 'Wowza Streaming Engine',
            self::Remote => 'Remote',
        };
    }

    /**
     * @return class-string<AbstractFrontend>|null
     */
    public function getClass(): ?string
    {
        return match ($this) {
            self::Icecast => Icecast::class,
            self::Shoutcast => Shoutcast::class,
            self::Shoutcast1 => Shoutcast1::class,
            self::Rsas => Rsas::class,
            self::NginxRtmp => NginxRtmp::class,
            self::Flussonic => Flussonic::class,
            self::Wowza => Wowza::class,
            default => null
        };
    }

    public function isEnabled(): bool
    {
        return self::Remote !== $this;
    }

    /**
     * Audio radio frontends that AutoDJ (Liquidsoap) can push to locally.
     */
    public function isAudio(): bool
    {
        return match ($this) {
            self::Icecast, self::Shoutcast, self::Shoutcast1, self::Rsas => true,
            default => false,
        };
    }

    /**
     * Live video / RTMP-class frontends (OBS ingest → HLS/playback).
     */
    public function isVideo(): bool
    {
        return match ($this) {
            self::NginxRtmp, self::Flussonic, self::Wowza => true,
            default => false,
        };
    }

    /**
     * Proprietary engines that MStream never vendors — operator must BYO binary/license.
     */
    public function isBringYourOwn(): bool
    {
        return match ($this) {
            self::Shoutcast, self::Shoutcast1, self::Rsas, self::Flussonic, self::Wowza => true,
            // Nginx RTMP is open; binary may still be uploaded if not on PATH.
            self::NginxRtmp => true,
            default => false,
        };
    }

    public function supportsMounts(): bool
    {
        return match ($this) {
            self::Shoutcast,
            self::Shoutcast1,
            self::Icecast,
            self::Rsas,
            self::NginxRtmp,
            self::Flussonic,
            self::Wowza => true,
            default => false,
        };
    }

    public function supportsReload(): bool
    {
        return self::Icecast === $this
            || self::Rsas === $this
            || self::NginxRtmp === $this;
    }

    public function defaultMountName(): string
    {
        return $this->isVideo() ? '/live' : '/radio.mp3';
    }

    public static function default(): self
    {
        return self::Icecast;
    }
}
