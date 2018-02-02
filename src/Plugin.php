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
     * @var $vendorDir
     */
    protected $vendorDir;

    /**
     * @var $baseDir
     */
    protected $baseDir;

    /**
     * @var $prefix
     */
    protected $prefix;

    /**
     * @var Filesystem $fs
     */
    protected $fs;

    /**
     * @var IOInterface $io
     */
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $build = isset($GLOBALS['argv']) && in_array('--no-dev', $GLOBALS['argv']) ? 'dist' : 'build';
        $branch = trim(str_replace('* ', '', exec("git branch | grep '\*'")));
        $baseDir = dirname($composer->getConfig()->get('vendor-dir', 1));
        $vendorDir = basename($composer->getConfig()->get('vendor-dir', 1));
        var_dump("Basedir: " . $baseDir);
        $binDir = $vendorDir . DIRECTORY_SEPARATOR . basename($composer->getConfig()->get('bin-dir', 1));
        $prefix = $build . DIRECTORY_SEPARATOR . $branch . DIRECTORY_SEPARATOR;

        $composer->getConfig()->merge([
          'config' => [
            'vendor-dir' => $prefix . DIRECTORY_SEPARATOR . $vendorDir,
            'bin-dir' => $prefix . DIRECTORY_SEPARATOR . $binDir
            ]
        ]);

        $this->io = $io;
        $this->fs = new FileSystem();
        $this->baseDir = '/home/verbral/toolkit-taskrunner';
        $this->composer = $composer;
        $this->installer = new PluginInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    /**
     * Handle an event callback for initialization.
     *
     * @param \Composer\EventDispatcher\Event $event
     */
    public function onInit(BaseEvent $event)
    {
        $destination = isset($GLOBALS['argv']) && in_array('info', $GLOBALS['argv']) ? 'dist' : 'build';
        $baseDir = dirname($this->composer->getConfig()->get('vendor-dir', 1));
        $branch = trim(str_replace('* ', '', exec("git branch | grep '\*'")));
        // @todo: Replace command output with re-run command on the correct directory?
        // Or make git hook that symlinks the '/vendor/composer/installed.json'
        // To the main location where we look for the file. Git hook seems to
        // have my vote since I probably can't alter the working directory
        // from init.
    }

    /**
     * After installation we symlink the composer resources to the root of the
     * project. When we run composer info we will alter the vendor dir to
     * display the correct versions installed in the build/branch folder.
     *
     * @param ScriptEvent $event
     */
    public function onPostInstallOrUpdate(ScriptEvent $event)
    {
        $composerSource = getcwd() . DIRECTORY_SEPARATOR;
        $composerTarget = dirname($this->composer->getConfig()->get('vendor-dir')) . DIRECTORY_SEPARATOR;
        $syncComposer = array(
            'vendor/composer/installed.json' => 'copy',
            'composer.json' => 'relativeSymlink',
            'composer.lock' => 'copy'
        );
        foreach ($syncComposer as $origin => $action) {
            if (file_exists($composerSource . $origin)) {
                $this->fs->$action($composerSource . $origin, $composerTarget . $origin);
            }
        }
    }

    /**
     * On package installation we symlink it from the vendor dir into the
     * build or dist directory at the location defined in the composer.json
     * file.
     *
     * @param PackageEvent $event
     */
    public function onPostPackageInstall(PackageEvent $event)
    {
        $op = $event->getOperation();
        if ($op instanceof InstallOperation) {
            $package = $op->getPackage();

            $type = $package->getType();
            $prettyName = $package->getPrettyName();
            if (strpos($prettyName, '/') !== false) {
                 list($vendor, $name) = explode('/', $prettyName);
            } else {
                $vendor = '';
                $name = $prettyName;
            }

            if ($type !== 'composer-plugin') {
                $installPath = $this->installer->getInstallPath($package);
                $vendorDir = $this->composer->getConfig()->get('vendor-dir', 1);
                $buildPath = dirname($vendorDir);
                $sitePath = rtrim(PackageUtils::getPackageInstallPath($package, $this->composer), '/');
                $to = $vendorDir . DIRECTORY_SEPARATOR . $prettyName;
                $from = $buildPath . DIRECTORY_SEPARATOR . $installPath;
                $this->fs->ensureDirectoryExists(dirname($to));
                $this->fs->relativeSymlink($from, $to);

                if ($type == 'drupal-core') {
                    $this->fs->copy($from, dirname(dirname(dirname($to))));
                }
                elseif (substr($type, 0, 7) === "drupal-") {
                    $this->fs->ensureDirectoryExists($buildPath . DIRECTORY_SEPARATOR .  dirname($sitePath));
                    $this->fs->relativeSymlink($to, $buildPath . DIRECTORY_SEPARATOR .  $sitePath);
                }

                // Scipping robo because they contain symlinks that can not be copied. To be fixed.
                $binaries = $package->getBinaries();
                if (!empty($binaries) && $name != 'robo') {
                    $binDir = $this->composer->getConfig()->get('bin-dir', 1);
                    var_dump('BinDir: ' . $binDir);
                    $this->fs->remove($vendorDir . DIRECTORY_SEPARATOR . $prettyName );
                    $this->fs->copy($buildPath . DIRECTORY_SEPARATOR .  $installPath, $vendorDir . DIRECTORY_SEPARATOR . $prettyName);
                    foreach ($binaries as $binary) {
                        $from = $vendorDir . DIRECTORY_SEPARATOR . $prettyName .  DIRECTORY_SEPARATOR . $binary;
                        $to = $binDir . DIRECTORY_SEPARATOR . $binary;
                        $this->fs->remove($to);
                        $this->fs->relativeSymlink($from, $to);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'init'=>
                array('onInit', self::CALLBACK_PRIORITY),
            PackageEvents::POST_PACKAGE_INSTALL =>
                array('onPostPackageInstall', self::CALLBACK_PRIORITY),
            ScriptEvents::POST_INSTALL_CMD =>
                array('onPostInstallOrUpdate', self::CALLBACK_PRIORITY),
            ScriptEvents::POST_UPDATE_CMD =>
                array('onPostInstallOrUpdate', self::CALLBACK_PRIORITY),
        );
    }
}
