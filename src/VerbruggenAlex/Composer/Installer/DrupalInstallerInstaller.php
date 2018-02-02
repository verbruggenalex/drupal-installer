<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VerbruggenAlex\Composer\Installer;

use Composer\Composer;
use Composer\Config;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Downloader\FilesystemException;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use VerbruggenAlex\Composer\Util\SymlinkFilesystem;
use VerbruggenAlex\Composer\Data\Package\PackageDataManagerInterface;
use VerbruggenAlex\Composer\Installer\Config\DrupalInstallerInstallerConfig;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class DrupalInstallerInstaller extends LibraryInstaller
{
    const PACKAGE_TYPE = 'drupal-installer';
    const PACKAGE_PRETTY_NAME = 'verbruggenalex/drupal-installer';

    /**
     * @var DrupalInstallerInstallerConfig
     */
    protected $config;

    /**
     * @var PackageDataManagerInterface
     */
    protected $packageDataManager;

    /**
     * @var SymlinkFilesystem
     */
    protected $filesystem;


    /**
     * @param IOInterface                  $io
     * @param Composer                     $composer
     * @param SymlinkFilesystem            $filesystem
     * @param PackageDataManagerInterface  $dataManager
     * @param DrupalInstallerInstallerConfig $config
     */
    public function __construct(
        IOInterface $io,
        Composer $composer,
        SymlinkFilesystem $filesystem,
        PackageDataManagerInterface $dataManager,
        DrupalInstallerInstallerConfig $config
    )
    {
        $this->filesystem = $filesystem;

        parent::__construct($io, $composer, 'library', $this->filesystem);

        $this->config = $config;
        $this->vendorDir = $this->config->getVendorDir();
        $this->packageDataManager = $dataManager;
        $this->packageDataManager->setVendorDir($this->vendorDir);
    }

    /**
     * @inheritdoc
     */
    public function getInstallPath(PackageInterface $package)
    {
        $this->initializeVendorDir();

        $basePath =
            $this->config->getOriginalVendorDir(). DIRECTORY_SEPARATOR
            . $package->getPrettyName() . DIRECTORY_SEPARATOR
            . $package->getPrettyVersion()
        ;

        $targetDir = $package->getTargetDir();

        return $basePath . ($targetDir ? '/' . $targetDir : '');
    }

    /**
     * @param PackageInterface $package
     *
     * @return string
     */
    protected function getPackageVendorSymlink(PackageInterface $package)
    {
        return $this->config->getBaseDir() . DIRECTORY_SEPARATOR . $this->config->getVendorDir() . DIRECTORY_SEPARATOR . $package->getPrettyName();
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!is_readable($this->getInstallPath($package))) {
            parent::install($repo, $package);
        } elseif (!$repo->hasPackage($package)) {
            $this->binaryInstaller->installBinaries($package, $this->getInstallPath($package));
            $repo->addPackage(clone $package);
        }

        if ($package->getType() == 'drupal-core') {
            $sourcePath = $this->getInstallPath($package);
            $targetPath = dirname($this->config->getVendorDir());
            $sourceAvailable = file_exists($sourcePath . DIRECTORY_SEPARATOR . "index.php");
            $siteAvailable = file_exists($targetPath . DIRECTORY_SEPARATOR . "index.php");
            if (!$siteAvailable) {
                $this->filesystem->copy($sourcePath, $targetPath);
            }
        }
        else {
            $this->createPackageVendorSymlink($package);
            if (substr($package->getType(), 0, 7) === "drupal-") {
                $this->createPackageBuildsymlink($package);
            }
        }
        $this->packageDataManager->addPackageUsage($package);
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     *
     * @return bool
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
//        else {
//            $sourcePath = $this->getInstallPath($package);
//            $targetPath = dirname($this->composer->getConfig()->get('vendor-dir')) . DIRECTORY_SEPARATOR . rtrim(PackageUtils::getPackageInstallPath($package, $this->composer), '/');
//            $sourceAvailable = $repo->hasPackage($package) && is_readable($sourcePath);
//            $packageAvailable = is_readable($targetPath);
//            if (!$sourceAvailable) {
//                return false;
//            } elseif (!$packageAvailable) {
//                $fs->ensureDirectoryExists(dirname($targetPath));
//                $fs->relativeSymlink($sourcePath, $targetPath);
//                return true;
//            } else {
//                return true;
//            }
//        }
        // Just check if the sources folder and the link exist
        return
            $repo->hasPackage($package)
            && is_readable($this->getInstallPath($package))
            && is_link($this->getPackageVendorSymlink($package))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $this->packageDataManager->setPackageInstallationSource($initial);
        $this->packageDataManager->setPackageInstallationSource($target);

        // The package need only a code update because the version (branch), only the commit changed
        if ($this->getInstallPath($initial) === $this->getInstallPath($target)) {
            $this->createPackageVendorSymlink($target);

            parent::update($repo, $initial, $target);
        } else {
            // If the initial package sources folder exists, uninstall it
            $this->composer->getInstallationManager()->uninstall($repo, new UninstallOperation($initial));

            // Install the target package
            $this->composer->getInstallationManager()->install($repo, new InstallOperation($target));
        }
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     *
     * @throws FilesystemException
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($this->isSourceDirUnused($package) && $this->io->askConfirmation(
                "The package version <info>" . $package->getPrettyName() . "</info> "
                . "(<fg=yellow>" . $package->getPrettyVersion() . "</fg=yellow>) seems to be unused."
                . PHP_EOL
                . 'Do you want to <fg=red>delete the source folder</fg=red> ? [y/n] (default: no) : ',
                false
            )) {
            $this->packageDataManager->setPackageInstallationSource($package);

            parent::uninstall($repo, $package);
        } else {
            $this->binaryInstaller->removeBinaries($package);
            $repo->removePackage($package);
        }

        $this->packageDataManager->removePackageUsage($package);
        $this->removePackageVendorSymlink($package);
    }

    /**
     * Detect if other project use the dependency by using the "packages.json" file
     *
     * @param PackageInterface $package
     *
     * @return bool
     */
    protected function isSourceDirUnused(PackageInterface $package)
    {
        $usageData = $this->packageDataManager->getPackageUsage($package);

        return sizeof($usageData) <= 1;
    }

    /**
     * @param PackageInterface $package
     */
    protected function createPackageVendorSymlink(PackageInterface $package)
    {
        if ($this->config->isSymlinkEnabled() && $this->filesystem->ensureSymlinkExists(
            $this->getSymlinkSourcePath($package),
            $this->getPackageVendorSymlink($package)
          )
        ) {
            $this->io->writeError(array(
              '  - Creating vendor symlink for <info>' . $package->getPrettyName()
              . '</info> (<fg=yellow>' . $package->getPrettyVersion() . '</fg=yellow>)',
              ''
            ));
        }
    }

    /**
     * @param PackageInterface $package
     */
    protected function createPackageBuildsymlink(PackageInterface $package)
    {
        if ($this->config->isSymlinkEnabled() && $this->filesystem->ensureSymlinkExists(
            $this->getPackageVendorSymlink($package),
            $this->getPackageBuildSymlink($package)
          )
        ) {
            $this->io->writeError(array(
              '  - Creating build symlink for <info>' . $package->getPrettyName()
              . '</info> (<fg=yellow>' . $package->getPrettyVersion() . '</fg=yellow>)',
              ''
            ));
        }
    }

    /**
     * @param PackageInterface $package
     *
     * @return string
     */
    protected function getSymlinkSourcePath(PackageInterface $package)
    {
        return $this->config->getBaseDir() . DIRECTORY_SEPARATOR . $this->getInstallPath($package);
    }

    /**
     * @param PackageInterface $package
     *
     * @throws FilesystemException
     */
    protected function removePackageVendorSymlink(PackageInterface $package)
    {
//        if (
//            $this->config->isSymlinkEnabled()
//            && $this->filesystem->removeSymlink($this->getPackageVendorSymlink($package))
//        ) {
//            $this->io->write(array(
//                '  - Deleting symlink for <info>' . $package->getPrettyName() . '</info> '
//                . '(<fg=yellow>' . $package->getPrettyVersion() . '</fg=yellow>)',
//                ''
//            ));
//
//            $symlinkParentDirectory = dirname($this->getPackageVendorSymlink($package));
//            $this->filesystem->removeEmptyDirectory($symlinkParentDirectory);
//        }
    }

    public function getPackageBuildSymlink(PackageInterface $package)
    {
        $type = $package->getType();
        $prettyName = $package->getPrettyName();
        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name = $prettyName;
        }

        $availableVars = compact('name', 'vendor');

        $extra = $package->getExtra();
        if (!empty($extra['installer-name'])) {
            $availableVars['name'] = $extra['installer-name'];
        }
        if(!empty($this->config->getInstallerPaths())) {
            $customPath = $this->config->mapCustomInstallPaths($this->config->getInstallerPaths(), $prettyName, $type, $vendor);
            if(false !== $customPath) {
                return $this->config->getBaseDir() . DIRECTORY_SEPARATOR
                . dirname($this->config->getVendorDir()) . DIRECTORY_SEPARATOR
                . rtrim($this->config->templatePath($customPath, $availableVars), '/');
            }
        }

        return NULL;
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
