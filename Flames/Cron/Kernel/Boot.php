<?php

namespace Flames\Cron\Kernel;

use Flames\Docker\Docker;
use Flames\Env\Env;
use Flames\Framework\Cache;
use Flames\Observer\App as ObserverApp;
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
        ObserverApp::setRoot(ROOT_PATH);

        $paths = [
            ROOT_PATH . '.env',
            ROOT_PATH . 'App',
            ROOT_PATH . 'Microservice',
            ROOT_PATH . 'public'
        ];

        if (Env::get('FLAMES_DEVELOPER')) {
            $paths[] = (ROOT_PATH . 'vendor/flamesphp');
        }

        ObserverApp::setPaths($paths);
        ObserverApp::setCachePath(self::observerCacheDir());

        ObserverRegister::change(function (string $hash): void {
            self::onChange($hash);
        });
    }

    protected static function onChange(string $hash): void
    {
        \Flames\Ready\Ready\Service\Supervisor::reloadWorkers();
    }

    protected static function observerCacheDir(): string
    {
        $cachePath = (string) Env::get('CACHE_PATH');

        if ($cachePath === '') {
            return rtrim(Cache::getPath(), '/') . '/observer/app/';
        }

        if (str_starts_with($cachePath, '/') || str_starts_with($cachePath, '~')) {
            $base = $cachePath;
        } else {
            $base = ROOT_PATH . ltrim($cachePath, './');
        }

        return rtrim($base, '/') . '/observer/app/';
    }
}
