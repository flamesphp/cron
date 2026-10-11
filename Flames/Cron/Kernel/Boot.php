<?php

namespace Flames\Cron\Kernel;

use Flames\Docker\Docker;
use Flames\Env\Env;
use Flames\Framework\Cache;
use Flames\Observer\App\Project as ObserverProject;
use Flames\Observer\App\Register as ObserverRegister;

class Boot
{
    public static function boot(): void
    {
        $module = self::getModule();

        if ($module === 'observer') {
            self::observerAppChange();
            return;
        }

        self::runInBackground('observer');
    }

    protected static function getModule(): ?string
    {
        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (str_starts_with($arg, '--') && $arg !== '--cron') {
                return substr($arg, 2);
            }
        }
        return null;
    }

    protected static function runInBackground(string $module): void
    {
        $php    = PHP_BINARY;
        $forge  = ROOT_PATH . 'forge';
        $output = Docker::isDocker() ? '>> /proc/1/fd/1 2>> /proc/1/fd/2' : '> /dev/null 2>&1';
        exec($php . ' ' . $forge . ' --cron --' . $module . ' ' . $output . ' &');
    }

    protected static function observerAppChange(): void
    {
        ObserverProject::configure(
            ROOT_PATH,
            self::observerCacheDir(),
            Env::get('FLAMES_DEVELOPER') === true,
        );

        ObserverRegister::change(function (string $hash): void {
            self::onChange($hash);
        });
    }

    protected static function onChange(string $hash): void
    {
        if (!class_exists(\Flames\Ready\Ready\Service\Supervisor::class)) {
            fwrite(STDERR, "[Flames Observer] Ready supervisor unavailable – change detected (hash={$hash}), worker reload skipped.\n");

            return;
        }

        $reloaded = \Flames\Ready\Ready\Service\Supervisor::reloadWorkers();

        if ($reloaded === false) {
            fwrite(STDERR, "[Flames Observer] Ready supervisor not running – worker reload skipped.\n");
        }
    }

    protected static function observerCacheDir(): string
    {
        return rtrim(Cache::getPath(), '/') . '/observer/app/';
    }
}
