<?php

namespace VerbruggenAlex\ComposerBuilder;

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
     * @var PluginInstallerConfig $config
     */
    protected $config;

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
        $composerConfig = $composer->getConfig();
        $composerExtra = $composer->getPackage()->getExtra();
        $this->io = $io;
        $this->filesystem =  new SymlinkFilesystem();
        $this->config = new PluginInstallerConfig($composerConfig, $composerExtra);
        $this->installer = new PluginInstaller(
          $io,
          $composer,
          $this->filesystem,
          $this->config)
        ;
        $composer->getInstallationManager()->addInstaller($this->installer);
        
//        $composer->getConfig()->merge([
//          'config' => [
//            'vendor-dir' => $this->config->getOriginalDirectory('vendorDir', 'relative'),
//            'bin-dir' => $this->getOriginalDirectory('binDir', 'relative'),
//          ]
//        ]);
    }

    /**
     * Handle an event callback for initialization.
     *
     * @param \Composer\EventDispatcher\Event $event
     */
    public function onInit(BaseEvent $event)
    {
//        $destination = isset($GLOBALS['argv']) && in_array('info', $GLOBALS['argv']) ? 'dist' : 'build';
//        $baseDir = dirname($this->composer->getConfig()->get('vendor-dir', 1));
//        $branch = trim(str_replace('* ', '', exec("git branch | grep '\*'")));
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
        $sourcePath = $this->config->getOriginalDirectory('baseDir', 'absolute') . DIRECTORY_SEPARATOR;
        $targetPath = $this->config->getBuildDirectory('baseDir', 'absolute') . DIRECTORY_SEPARATOR;
        $syncComposer = array(
            'vendor/composer/installed.json' => 'copy',
            'composer.json' => 'relativeSymlink',
            'composer.lock' => 'copy'
        );
        foreach ($syncComposer as $origin => $action) {
            if (file_exists($sourcePath . $origin)) {
                $this->filesystem->$action($sourcePath . $origin, $targetPath . $origin);
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
                $vendorDirOriginal = $this->config->getOriginalDirectory('absolute', 'vendorDir');
                $vendorDir = $this->config->getBuildDirectory('absolute', 'vendorDir');
                $baseDir = $this->config->getBuildDirectory('absolute', 'baseDir');
                $extraConfig = $this->config->getExtra();
                $installPath = $this->installer->getInstallPath($package);
                $sitePath = $baseDir . rtrim(PackageUtils::getPackageInstallPath($package, $extraConfig), '/');
                $to = $vendorDir . DIRECTORY_SEPARATOR . $prettyName;
                $from = $installPath;
                $this->filesystem->ensureDirectoryExists(dirname($to));
                $this->filesystem->relativeSymlink($from, $to);

                if ($type == 'drupal-core') {
                    $this->filesystem->copy($from, $baseDir);
                }
                elseif (substr($type, 0, 7) === "drupal-") {
                    $this->filesystem->ensureDirectoryExists(dirname($sitePath));
                    $this->filesystem->relativeSymlink($to, $sitePath);
                }

                // Scipping robo because they contain symlinks that can not be copied. To be fixed.
                $binaries = $package->getBinaries();
                if (!empty($binaries) && $name != 'robo') {
                    $binDir = $this->config->getBuildDirectory('absolute', 'binDir');
                    $this->filesystem->remove($vendorDir . DIRECTORY_SEPARATOR . $prettyName );
                    $this->filesystem->copy($installPath, $vendorDir . DIRECTORY_SEPARATOR . $prettyName);
                    foreach ($binaries as $binary) {
                        $from = $vendorDir . DIRECTORY_SEPARATOR . $prettyName .  DIRECTORY_SEPARATOR . $binary;
                        // @todo: Provide fix to ensure no double dirs for bindir.
                        $to = str_replace('/bin/bin' , '/bin', $binDir . DIRECTORY_SEPARATOR . $binary);
                        $this->filesystem->remove($to);
                        $this->filesystem->ensureDirectoryExists(dirname($to));
                        $this->filesystem->relativeSymlink($from, $to);
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
