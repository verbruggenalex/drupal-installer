<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VerbruggenAlex\Composer\Installer\Config;

use VerbruggenAlex\Composer\Installer\DrupalInstallerInstaller;

class DrupalInstallerInstallerConfig
{
    const ENV_PARAMETER_VENDOR_DIR        = 'COMPOSER_SPP_VENDOR_DIR';
    const ENV_PARAMETER_SYMLINK_BASE_PATH = 'COMPOSER_SPP_SYMLINK_BASE_PATH';

    /**
     * @var bool
     */
    protected $devMode;

    /**
     * @var string
     */
    protected $originalVendorDir;

    /**
     * @var string
     */
    protected $originalBinDir;

    /**
     * @var string
     */
    protected $baseDir;

    /**
     * @var string
     */
    protected $vendorDir;

    /**
     * @var string|null
     */
    protected $symlinkBasePath;

    /**
     * @var bool
     */
    protected $isSymlinkEnabled = true;

    /**
     * @var array
     */
    protected $packageList = array();

    /**
     * @var array
     */
    protected $installerPaths = array();


    /**
     * @param array      $originalDirectories
     * @param array|null $extraConfigs
     */
    public function __construct($originalDirectories, $extraConfigs)
    {
        $originalVendorDir = $originalDirectories['vendorDir'];
        $originalBinDir = $originalDirectories['binDir'];
        $baseDir = substr($originalVendorDir['absolute'], 0, -strlen($originalVendorDir['relative']));

        $this->originalVendorDir = $originalVendorDir['relative'];
        $this->originalBinDir = $originalBinDir['relative'];
        $this->setVendorDir($baseDir, $extraConfigs);
        $this->setBaseDir($baseDir, $extraConfigs);
        $this->setSymlinkBasePath($extraConfigs);
        $this->setIsSymlinkEnabled($extraConfigs);
        $this->setPackageList($extraConfigs);
        $this->setInstallerpaths($extraConfigs);
    }

    /**
     * @param string $baseDir
     * @param array  $extraConfigs
     */
    protected function setBaseDir($baseDir)
    {
        $this->baseDir = $baseDir;

        if (isset($extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['symlink-dir'])) {
            $this->baseDir = $extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['symlink-dir'];

//            if ('/' != $this->baseDir[0]) {
//                $this->baseDir = $baseDir . $this->baseDir;
//            }
        }
    }

    /**
     * @param string $baseDir
     * @param array  $extraConfigs
     *
     * @throws \InvalidArgumentException
     */
    protected function setVendorDir($baseDir, array $extraConfigs)
    {
        // Set default build directory name.
        $this->vendorDir = '';
        $buildDirName = isset($GLOBALS['argv']) && in_array('--no-dev', $GLOBALS['argv']) ? 'dist' : 'build';
        $branch = trim(str_replace('* ', '', exec("git branch | grep '\*'")));
        // Check if we have custom naming for the build and/or version directory.
        if (array_key_exists('drupal-installer', $extraConfigs)) {
            foreach (array('build-dir', 'version-dir') as $type) {
                if (array_key_exists($type, $extraConfigs['drupal-installer'])) {
                    $this->vendorDir .= (isset($GLOBALS['argv']) && in_array('--no-dev', $GLOBALS['argv']))
                      ? $extraConfigs['drupal-installer'][$type]['--no-dev']
                      : $extraConfigs['drupal-installer'][$type]['--dev'];
                }
                $this->vendorDir = rtrim($this->vendorDir, '/') . DIRECTORY_SEPARATOR;
            }
        }

//        if (false !== getenv(static::ENV_PARAMETER_VENDOR_DIR)) {
//            $this->vendorDir = getenv(static::ENV_PARAMETER_VENDOR_DIR);
//        }

//        if ('/' != $this->vendorDir[0]) {
//            $this->vendorDir = $baseDir . $this->vendorDir;
//        }

        // Replace branch variable.
        // @todo: Also allow tag replacement.
        $availableVars = $this->inflectPackageVars(compact('branch', 'tag'));
        $this->vendorDir = rtrim($this->templatePath($this->vendorDir, $availableVars), '/')
          .DIRECTORY_SEPARATOR
          . $this->getOriginalVendorDir();
    }

    /**
     * Get the installer paths.
     *
     * @return array
     */
    public function getInstallerPaths()
    {
        return $this->installerPaths;
    }

    /**
     * Allow to override symlinks base path.
     * This is useful for a Virtual Machine environment, where directories can be different
     * on the host machine and the guest machine.
     *
     * @param array $extraConfigs
     */
    protected function setSymlinkBasePath(array $extraConfigs)
    {
//        if (isset($extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['symlink-base-path'])) {
//            $this->symlinkBasePath = $extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['symlink-base-path'];
//
//            if (false !== getenv(static::ENV_PARAMETER_SYMLINK_BASE_PATH)) {
//                $this->symlinkBasePath = getenv(static::ENV_PARAMETER_SYMLINK_BASE_PATH);
//            }
//
//            // Remove the ending slash if exists
//            if ('/' === $this->symlinkBasePath[strlen($this->symlinkBasePath) - 1]) {
//                $this->symlinkBasePath = substr($this->symlinkBasePath, 0, -1);
//            }
//        } elseif (0 < strpos($extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['vendor-dir'], '/')) {
//            $this->symlinkBasePath = $extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['vendor-dir'];
//        }
//
//        // Up to the project root directory
//        if (0 < strpos($this->symlinkBasePath, '/')) {
//            $this->symlinkBasePath = '../../' . $this->symlinkBasePath;
//        }
    }

    /**
     * The symlink directory creation process can be disabled.
     * This may mean that you work directly with the sources directory so the symlink directory is useless.
     *
     * @param array $extraConfigs
     */
    protected function setIsSymlinkEnabled(array $extraConfigs)
    {
        if (isset($extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['symlink-enabled'])) {
            if (!is_bool($extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['symlink-enabled'])) {
                throw new \UnexpectedValueException('The configuration "symlink-enabled" should be a boolean');
            }

            $this->isSymlinkEnabled = $extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['symlink-enabled'];
        }
    }

    /**
     * @return array
     */
    public function getPackageList()
    {
        return $this->packageList;
    }

    /**
     * @param array $extraConfigs
     */
    public function setPackageList(array $extraConfigs)
    {
        if (isset($extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['package-list'])) {
            $packageList = $extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['package-list'];

            if (!is_array($packageList)) {
                throw new \UnexpectedValueException('The configuration "package-list" should be a JSON object');
            }

            $this->packageList = $packageList;
        }
    }

    /**
     * @param array $extraConfigs
     */
    public function setInstallerPaths(array $extraConfigs)
    {
        if (isset($extraConfigs['installer-paths'])) {
            $this->installerPaths = $extraConfigs['installer-paths'];
        }
    }

    /**
     * @return bool
     */
    public function isSymlinkEnabled()
    {
        return $this->isSymlinkEnabled;
    }

    /**
     * @return string
     */
    public function getVendorDir()
    {
        return $this->vendorDir;
    }

    /**
     * @return string
     */
    public function getBaseDir()
    {
        return $this->baseDir;
    }

    /**
     * @param bool $endingSlash
     *
     * @return string
     */
    public function getOriginalVendorDir($endingSlash = false)
    {
        if ($endingSlash && null != $this->originalVendorDir) {
            return $this->originalVendorDir . '/';
        }

        return $this->originalVendorDir;
    }

    /**
     * @return string|null
     */
    public function getSymlinkBasePath()
    {
        return $this->symlinkBasePath;
    }
}
