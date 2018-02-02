<?php

namespace MyBundle\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

class PluginInstaller extends LibraryInstaller
{
  public function getInstallPath(PackageInterface $package)
  {
        $this->initializeVendorDir();
        $version = $package->getPrettyVersion();
        $vendorDir = $this->composer->getConfig()->get('vendor-dir-original', 1);
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
          $sourcePath = $this->getInstallPath($package);
          $targetPath = dirname($this->composer->getConfig()->get('vendor-dir'));
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
          $sourcePath = $this->getInstallPath($package);
          $targetPath = dirname($this->composer->getConfig()->get('vendor-dir'))  . DIRECTORY_SEPARATOR . rtrim(PackageUtils::getPackageInstallPath($package, $this->composer), '/');
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
            return ;
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

