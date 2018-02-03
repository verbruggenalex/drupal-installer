<?php

namespace VerbruggenAlex\ComposerBuilder;

use Composer\Composer;
use Composer\Package\PackageInterface;

class PackageUtils
{
    public static function getPackageInstallPath(PackageInterface $package, array $composerExtra)
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

        if(isset($composerExtra['installer-paths'])) {
            $customPath = self::mapCustomInstallPaths($composerExtra['installer-paths'], $prettyName, $type, $vendor);
            if(false !== $customPath) {
                return self::templatePath($customPath, $availableVars);
            }
        }

        return NULL;
    }

    public static function getBuildPath($originalVendor, $extraConfig)
    {
        $vendorDir = '';
        $branch = trim(str_replace('* ', '', exec("git branch | grep '\*'")));
        if (array_key_exists('drupal-installer', $extraConfig)) {
            foreach (array('build-dir', 'version-dir') as $type) {
                if (array_key_exists($type, $extraConfig['drupal-installer'])) {
                    $vendorDir .= (isset($GLOBALS['argv']) && in_array('--no-dev', $GLOBALS['argv']))
                      ? $extraConfig['drupal-installer'][$type]['--no-dev']
                      : $extraConfig['drupal-installer'][$type]['--dev'];
                }
                $vendorDir = rtrim($vendorDir, '/') . DIRECTORY_SEPARATOR;
            }
        }

        // Replace branch variable.
        // @todo: Also allow tag replacement.
        $availableVars = self::inflectPackageVars(compact('branch', 'tag'));
        $vendorDir = rtrim(self::templatePath($vendorDir, $availableVars), '/')
          .DIRECTORY_SEPARATOR
          . $originalVendor;

        return $vendorDir;
    }

    /**
     * For an installer to override to modify the vars per installer.
     *
     * @param  array $vars
     * @return array
     */
    public static function inflectPackageVars($vars)
    {
        return $vars;
    }

    /**
     * Replace vars in a path
     *
     * @param  string $path
     * @param  array  $vars
     * @return string
     */
    protected static function templatePath($path, array $vars = array())
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
     * Search through a passed paths array for a custom install path.
     *
     * @param  array  $paths
     * @param  string $name
     * @param  string $type
     * @param  string $vendor = NULL
     * @return string
     */
    protected static function mapCustomInstallPaths(array $paths, $name, $type, $vendor = NULL)
    {
        foreach ($paths as $path => $names) {
            if (in_array($name, $names) || in_array('type:' . $type, $names) || in_array('vendor:' . $vendor, $names)) {
                return $path;
            }
        }
        return false;
    }
}

