<?php

declare(strict_types=1);

use App\Console\Command;

return function (App\Event\BuildConsoleCommands $event) {
    $event->addAliases([
        'mstream:acme:get-certificate' => Command\Acme\GetCertificateCommand::class,
        'mstream:acme:reload' => Command\Acme\ReloadCommand::class,
        'mstream:backup' => Command\Backup\BackupCommand::class,
        'mstream:restore' => Command\Backup\RestoreCommand::class,
        'mstream:debug:optimize-tables' => Command\Debug\OptimizeTablesCommand::class,
        'mstream:internal:on-ssl-renewal' => Command\Internal\OnSslRenewal::class,
        'mstream:internal:ip' => Command\Internal\GetIpCommand::class,
        'mstream:internal:uptime-wait' => Command\Internal\UptimeWaitCommand::class,
        'mstream:media:reprocess' => Command\Media\ReprocessCommand::class,
        'mstream:media:clear-extra' => Command\Media\ClearExtraMetadataCommand::class,
        'mstream:media:clear-computed-autocue' => Command\Media\ClearComputedAutoCueCommand::class,
        'mstream:media:sync' => Command\Media\SyncCommand::class,
        'mstream:playlist:export' => Command\Playlists\ExportCommand::class,
        'mstream:playlist:import' => Command\Playlists\ImportCommand::class,
        'mstream:queue:process' => Command\MessageQueue\ProcessCommand::class,
        'mstream:queue:clear' => Command\MessageQueue\ClearCommand::class,
        'mstream:settings:list' => Command\Settings\ListCommand::class,
        'mstream:settings:set' => Command\Settings\SetCommand::class,
        'mstream:station-queues:clear' => Command\ClearQueuesCommand::class,
        'mstream:account:list' => Command\Users\ListCommand::class,
        'mstream:account:login-token' => Command\Users\LoginTokenCommand::class,
        'mstream:account:reset-password' => Command\Users\ResetPasswordCommand::class,
        'mstream:account:set-administrator' => Command\Users\SetAdministratorCommand::class,
        'mstream:cache:clear' => Command\ClearCacheCommand::class,
        'mstream:setup:migrate' => Command\MigrateDbCommand::class,
        'mstream:setup:fixtures' => Command\SetupFixturesCommand::class,
        'mstream:setup:rollback' => Command\RollbackDbCommand::class,
        'mstream:setup' => Command\SetupCommand::class,
        'mstream:radio:restart' => Command\RestartRadioCommand::class,
        'mstream:sync:nowplaying' => Command\Sync\NowPlayingCommand::class,
        'mstream:sync:nowplaying:station' => Command\Sync\NowPlayingPerStationCommand::class,
        'mstream:sync:run' => Command\Sync\RunnerCommand::class,
        'mstream:sync:task' => Command\Sync\SingleTaskCommand::class,
        'queue:process' => Command\MessageQueue\ProcessCommand::class,
        'queue:clear' => Command\MessageQueue\ClearCommand::class,
        'cache:clear' => Command\ClearCacheCommand::class,
        'acme:cert' => Command\Acme\GetCertificateCommand::class,
        'acme:reload' => Command\Acme\ReloadCommand::class,
    ]);

    if (!$event->getEnvironment()->isProduction()) {
        $event->addAliases([
            'mstream:api:docs' => Command\Dev\GenerateApiDocsCommand::class,
            'mstream:new-version' => Command\Dev\NewVersionCommand::class,
            'mstream:dev:generate-db-fixture' => Command\Dev\GenerateDbFixtureCommand::class,
            'mstream:dev:check-migrations' => Command\Dev\CheckMigrationsCommand::class,
            'mstream:locale:generate' => Command\Locale\GenerateCommand::class,
            'mstream:locale:import' => Command\Locale\ImportCommand::class,
            'locale:generate' => Command\Locale\GenerateCommand::class,
            'locale:import' => Command\Locale\ImportCommand::class,
        ]);
    }
};
