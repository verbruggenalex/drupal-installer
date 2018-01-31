<?php

namespace MyBundle\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\Event as BaseEvent;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * Priority that plugin uses to register callbacks.
     */
    const CALLBACK_PRIORITY = 50000;

    /**
     * @var Composer $composer
     */
    protected $composer;

    /**
     * @var IOInterface $io
     */
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $destination = isset($GLOBALS['argv']) && in_array('--no-dev', $GLOBALS['argv']) ? 'dist' : 'build';
        $baseDir = dirname($composer->getConfig()->get('vendor-dir'));
        $branch = trim(str_replace('* ', '', exec("git branch | grep '\*'")));

        $vendorDir = $baseDir . DIRECTORY_SEPARATOR . $destination . DIRECTORY_SEPARATOR . $branch . DIRECTORY_SEPARATOR . "vendor";
        $composer->getConfig()->merge(['config' => ['vendor-dir' => $vendorDir]]);

        $this->io = $io;
        $this->composer = $composer;
        $this->installer = new PluginInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    /**
     * @param ScriptEvent $event
     */
    public function onPostInstallOrUpdate(ScriptEvent $event)
    {
    }

    /**
     * @param PackageEvent $event
     */
    public function onPostPackageInstall(PackageEvent $event)
    {
        $op = $event->getOperation();
        if ($op instanceof InstallOperation) {
            $package = $op->getPackage();
            $type = $package->getType();
            if (substr( $type, 0, 7) === "drupal-" && $type !== 'drupal-core') {
                $installPath = $this->installer->getInstallPath($package);
                $basePath = dirname($this->composer->getConfig()->get('vendor-dir'));
                $sitePath = rtrim(PackageUtils::getPackageInstallPath($package, $this->composer), '/');
                $symlinkPath = $basePath . DIRECTORY_SEPARATOR . $sitePath;
                $fs = new FileSystem();
                $fs->ensureDirectoryExists(dirname($symlinkPath));
                $fs->relativeSymlink($installPath, $symlinkPath);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            PackageEvents::POST_PACKAGE_INSTALL =>
                array('onPostPackageInstall', self::CALLBACK_PRIORITY),
            ScriptEvents::POST_INSTALL_CMD =>
                array('onPostInstallOrUpdate', self::CALLBACK_PRIORITY),
            ScriptEvents::POST_UPDATE_CMD =>
                array('onPostInstallOrUpdate', self::CALLBACK_PRIORITY),
        );
    }
}
