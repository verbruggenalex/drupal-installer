<?php

namespace MyBundle\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

class PluginInstaller extends LibraryInstaller
{
  public function getInstallPath(PackageInterface $package)
  {
        $this->initializeVendorDir();
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $basePath = $package->getType() == 'drupal-core' ? dirname($vendorDir) : $vendorDir . DIRECTORY_SEPARATOR . $package->getPrettyName();
        $targetDir = $package->getTargetDir();

        return $basePath . ($targetDir ? '/' . $targetDir : '');
  }
    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     *
     * @return bool
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // Just check if the sources folder and the link exist
        if ($package->getType() == 'drupal-core') {
            return file_exists($this->getInstallPath($package) . DIRECTORY_SEPARATOR . "index.php");
        }
        else {
            return $repo->hasPackage($package) && is_readable($this->getInstallPath($package));
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

