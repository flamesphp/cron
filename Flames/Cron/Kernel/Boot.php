<?php

namespace Flames\Cron\Kernel;

use Flames\Env\Env;
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
        $php   = PHP_BINARY;
        $forge = ROOT_PATH . 'forge';
        exec($php . ' ' . escapeshellarg($forge) . ' --cron --' . $module . ' >> /proc/1/fd/1 2>> /proc/1/fd/2 &');
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
        ObserverRegister::change(function (string $hash): void {
            \Flames\Ready\Ready\Service\Supervisor::reloadWorkers();
        }, 59);
    }
}
