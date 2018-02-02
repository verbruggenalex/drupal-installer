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
    protected $symlinkDir;

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
        $this->setSymlinkDirectory($baseDir, $extraConfigs);
        $this->setSymlinkBasePath($extraConfigs);
        $this->setIsSymlinkEnabled($extraConfigs);
        $this->setPackageList($extraConfigs);
    }

    /**
     * @param string $baseDir
     * @param array  $extraConfigs
     */
    protected function setSymlinkDirectory($baseDir, array $extraConfigs)
    {
        $this->symlinkDir = $baseDir . 'vendor-shared';

        if (isset($extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['symlink-dir'])) {
            $this->symlinkDir = $extraConfigs[DrupalInstallerInstaller::PACKAGE_TYPE]['symlink-dir'];

//            if ('/' != $this->symlinkDir[0]) {
//                $this->symlinkDir = $baseDir . $this->symlinkDir;
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
                if (in_array($type, $extraConfigs['drupal-installer'])) {
                    $this->vendorDir .= isset($GLOBALS['argv']) && in_array('--no-dev', $GLOBALS['argv'])
                      ? $extraConfigs['drupal-installer'][$type]['--no-dev']
                      : $extraConfigs['drupal-installer'][$type]['no-dev'];
                }
                $this->vendorDir = rtrim('/', $this->vendorDir) . DIRECTORY_SEPARATOR;
            }
        }

        if (false !== getenv(static::ENV_PARAMETER_VENDOR_DIR)) {
            $this->vendorDir = getenv(static::ENV_PARAMETER_VENDOR_DIR);
        }

//        if ('/' != $this->vendorDir[0]) {
//            $this->vendorDir = $baseDir . $this->vendorDir;
//        }

        // Replace branch variable.
        // @todo: Also allow tag replacement.
        $availableVars = $this->inflectPackageVars(compact('branch', 'tag'));
        $this->vendorDir = $this->templatePath($this->vendorDir, $availableVars);
    }

    /**
     * Replace vars in a path
     *
     * @param  string $path
     * @param  array  $vars
     * @return string
     */
    protected function templatePath($path, array $vars = array())
    {
        if (strpos($path, '{') !== false) {
            extract($vars);
            preg_match_all('@\{\$([A-Za-z0-9_]*)\}@i', $path, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $var) {
                    $path = str_replace('{$' . $var . '}', $$var, $path);
                }
            }
        }
        return $path;
    }


    /**
     * For an installer to override to modify the vars per installer.
     *
     * @param  array $vars
     * @return array
     */
    public function inflectPackageVars($vars)
    {
        return $vars;
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
    public function getSymlinkDir()
    {
        return $this->symlinkDir;
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
