<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VerbruggenAlex\Composer\Installer\Solver;

use Composer\Package\PackageInterface;
use VerbruggenAlex\Composer\Installer\Config\DrupalInstallerInstallerConfig;
use VerbruggenAlex\Composer\Installer\DrupalInstallerInstaller;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class DrupalInstallerSolver
{
    /**
     * @var array
     */
    protected $packageCallbacks = array();

    /**
     * @var bool
     */
    protected $areAllShared = false;


    /**
     * @param DrupalInstallerInstallerConfig $config
     */
    public function __construct(DrupalInstallerInstallerConfig $config)
    {
        $packageList = $config->getPackageList();

        foreach ($packageList as $packageName) {
            if ('*' === $packageName) {
                $this->areAllShared = true;
            }
        }

        if (!$this->areAllShared) {
            $this->packageCallbacks = $this->createCallbacks($packageList);
        }
    }

    /**
     * @param PackageInterface $package
     *
     * @return bool
     */
    public function isDrupalInstaller(PackageInterface $package)
    {
//        $prettyName = $package->getPrettyName();
//
//        // Avoid putting this package into dependencies folder, because on the first installation the package won't be
//        // installed in dependencies folder but in the vendor folder.
//        // So I prefer keeping this behavior for further installs.
//        if (DrupalInstallerInstaller::PACKAGE_PRETTY_NAME === $prettyName) {
//            return false;
//        }
//
//        if ($this->areAllShared || DrupalInstallerInstaller::PACKAGE_TYPE === $package->getType()) {
//            return true;
//        }
//
//        foreach ($this->packageCallbacks as $equalityCallback) {
//            if ($equalityCallback($prettyName)) {
//                return true;
//            }
//        }
//
//        return false;
        return true;
    }

    /**
     * @param array $packageList
     *
     * @return array
     */
    protected function createCallbacks(array $packageList)
    {
        $callbacks = array();

        foreach ($packageList as $packageName) {
            // Has wild card (*)
            if (false !== strpos($packageName, '*')) {
                $pattern = str_replace('*', '[a-zA-Z0-9-_]+', str_replace('/', '\/', $packageName));

                $callbacks[] = function ($packagePrettyName) use ($pattern) {
                    return 1 === preg_match('/' . $pattern . '/', $packagePrettyName);
                };
            // Raw package name
            } else {
                $callbacks[] = function ($packagePrettyName) use ($packageName) {
                    return $packageName === $packagePrettyName;
                };
            }
        }

        return $callbacks;
    }
}
