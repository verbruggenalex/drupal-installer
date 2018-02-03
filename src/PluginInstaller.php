<?php

namespace VerbruggenAlex\ComposerBuilder;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

class PluginInstaller extends LibraryInstaller {

  /**
   * @var Config $config
   */
  protected $onfig;

  /**
   * @var Composer $composer
   */
  protected $composer;

  /**
   * @var SymlinkFilesystem $filesystem
   */
  protected $filesystem;

  /**
   * @param IOInterface           $io
   * @param Composer              $composer
   * @param SymlinkFilesystem     $filesystem
   * @param PluginInstallerConfig $config
   */
  public function __construct(
    IOInterface $io,
    Composer $composer,
    SymlinkFilesystem $filesystem,
    PluginInstallerConfig $config
  )
  {
    $this->config = $config;
    $this->composer = $composer;
    $this->filesystem = $filesystem;

    parent::__construct($io, $composer, 'library', $this->filesystem);
  }

  public function getInstallPath(PackageInterface $package) {
    
        $this->initializeVendorDir();
        $version = $package->getPrettyVersion();
        $vendorDir = $this->config->getOriginalDirectory('absolute', 'vendorDir');
        $basePath = $vendorDir . DIRECTORY_SEPARATOR . $package->getPrettyName() . DIRECTORY_SEPARATOR . $version;
        $targetDir = $package->getTargetDir();

        return $basePath;
  }
    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     *
     * @return bool
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
      $fs = new FileSystem();
        // Just check if the sources folder and the link exist
        if ($package->getType() == 'drupal-core') {
          $targetPath = $this->config->getBuildDirectory('absolute', 'baseDir');
          $sourcePath = $this->config->getOriginalDirectory('absolute', 'baseDir') . $this->getInstallPath($package);
          $sourceAvailable = file_exists($sourcePath . DIRECTORY_SEPARATOR . "index.php");
          $siteAvailable = file_exists($targetPath . DIRECTORY_SEPARATOR . "index.php");
          if (!$sourceAvailable) {
            return false;
          }
          elseif (!$siteAvailable) {
            $fs->copy($sourcePath, $targetPath);
            return true;
          }
          else  {
            return true;
          }
        }
        else {
          $composerExtra = $this->config->getExtra();
          var_dump($composerExtra);
          $sourcePath = $this->getInstallPath($package);
          $targetPath = $this->config->getBuildDirectory()  . DIRECTORY_SEPARATOR . rtrim(PackageUtils::getPackageInstallPath($package, $composerExtra), '/');
          $sourceAvailable = $repo->hasPackage($package) && is_readable($sourcePath);
          $packageAvailable = is_readable($targetPath);
          if (!$sourceAvailable) {
            return false;
          }
          elseif (!$packageAvailable) {
            $fs->ensureDirectoryExists(dirname($targetPath));
            $fs->relativeSymlink($sourcePath, $targetPath);
            return true;
          }
          else  {
            return true;
          }
        }
    }

    /**
     * @param string $packageType
     *
     * @return bool
     */
    public function supports($packageType)
    {
        return true;
    }
}

