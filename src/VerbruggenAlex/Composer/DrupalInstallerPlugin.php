<?php

namespace VerbruggenAlex\Composer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use VerbruggenAlex\Composer\Data\Package\DrupalInstallerDataManager;
use VerbruggenAlex\Composer\Installer\Config\DrupalInstallerInstallerConfig;
use VerbruggenAlex\Composer\Installer\Solver\DrupalInstallerSolver;
use VerbruggenAlex\Composer\Installer\DrupalInstallerInstaller;
use VerbruggenAlex\Composer\Installer\Solver\DrupalInstallerInstallerSolver;
use VerbruggenAlex\Composer\Util\SymlinkFilesystem;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class DrupalInstallerPlugin implements PluginInterface
{
    /**
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
      // Set needed variables.
      $config = $this->setConfig($composer);
      $symlink = new SymlinkFilesystem();
      $installerDataManager = new DrupalInstallerDataManager($composer);
      $installerArray = array($io, $composer, $symlink, $installerDataManager, $config);

      // Add the installer.
      $composer->getInstallationManager()->addInstaller(
        new DrupalInstallerInstallerSolver(
          new DrupalInstallerSolver($config),
          new DrupalInstallerInstaller($installerArray),
          new LibraryInstaller($io, $composer)
        )
      );
    }

    /**
     * @param Composer $composer
     *
     * @return DrupalInstallerInstallerConfig
     */
    protected function setConfig(Composer $composer)
    {
      $originalDirectories = array(
        'vendorDir' => array(
          'relative' => $composer->getConfig()->get('vendor-dir'),
          'absolute' => $composer->getConfig()->get('vendor-dir', 1),
        ),
        'binDir' => array(
          'relative' => $composer->getConfig()->get('bin-dir'),
          'absolute' => $composer->getConfig()->get('bin-dir', 1),
        ),
      );
      return new DrupalInstallerInstallerConfig(
        $originalDirectories,
        $composer->getPackage()->getExtra()
      );
   }
}
