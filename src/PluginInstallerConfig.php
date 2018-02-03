<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VerbruggenAlex\ComposerBuilder;

use Composer\Config;

class PluginInstallerConfig
{
    /**
     * @var string
     */
    protected $originalDirectories;

    /**
     * @var string
     */
    protected $buildDirectories;

    /**
     * @var string
     */
    protected $buildPrefix;

    /**
     * @var array
     */
    protected $extraConfig;

    /**
     * @param Config     $composerConfig
     * @param array|null $extraConfigs
     */
    public function __construct($composerConfig, $composerExtra = null)
    {
        $this->setExtra($composerExtra);
        $this->setOriginalDirectories($composerConfig, $composerExtra);
        $this->setBuildPrefix($composerExtra);
        $this->setBuildDirectories();
    }

    /**
     * @param Composer $composer
     */
    protected function setOriginalDirectories($composerConfig, $composerExtra) {
      // Get original directories.
      $vendorDirRelative = $composerConfig->get('vendor-dir', 1);
      $vendorDirAbsolute = $composerConfig->get('vendor-dir');
      $binDirRelative = $composerConfig->get('bin-dir', 1);
      $binDirAbsolute = $composerConfig->get('bin-dir');
      $baseDirAbsolute = substr($vendorDirAbsolute, 0, -strlen($vendorDirRelative));
      // Set original directories.
      $this->originalDirectories =  array(
        'absolute' => array(
          'baseDir' =>$baseDirAbsolute,
          'vendorDir' => $vendorDirAbsolute,
          'binDir' => $binDirAbsolute,
        ),
        'relative' => array(
          'vendorDir' => $vendorDirRelative,
          'binDir' => $binDirRelative,
        ),
      );
    }

    /**
     * @param string $pathType
     * @param string $dirType
     */
    protected function setBuildDirectories() {
      // Get required paths.
      $baseDirAbsolute = $this->originalDirectories['absolute']['baseDir'] . DIRECTORY_SEPARATOR;
      $buildPrefix = $this->buildPrefix . DIRECTORY_SEPARATOR;
      $buildPrefixAbsolute = $baseDirAbsolute . $buildPrefix;
      $vendorDirRelative = $this->originalDirectories{'relative'}['vendorDir'];
      $binDirRelative = $this->originalDirectories{'relative'}['binDir'];
      // Set original directories.
      $this->buildDirectories = array(
        'absolute' => array(
          'baseDir' => $buildPrefixAbsolute,
          'vendorDir' => $buildPrefixAbsolute . $vendorDirRelative,
          'binDir' => $buildPrefixAbsolute . $binDirRelative,
        ),
        'relative' => array(
          'baseDir' => $this->buildPrefix,
          'vendorDir' => $this->buildPrefix . $vendorDirRelative,
          'binDir' => $this->buildPrefix  . $binDirRelative,
        ),
      );
    }

    /**
     * @param array $extraConfigs
     *
     * @throws \InvalidArgumentException
     */
    protected function setBuildPrefix(array $extraConfigs)
    {
      $buildPrefix = '';
      $branch = trim(str_replace('* ', '', exec("git branch | grep '\*'")));
      if (array_key_exists('drupal-installer', $extraConfigs)) {
          foreach (array('build-dir', 'version-dir') as $type) {
              if (array_key_exists($type, $extraConfigs['drupal-installer'])) {
                  $buildPrefix .= (isset($GLOBALS['argv']) && in_array('--no-dev', $GLOBALS['argv']))
                    ? $extraConfigs['drupal-installer'][$type]['--no-dev']
                    : $extraConfigs['drupal-installer'][$type]['--dev'];
              }
              $buildPrefix = rtrim($buildPrefix, '/') . DIRECTORY_SEPARATOR;
          }
      }

      // Replace branch variable.
      // @todo: Also allow tag replacement.
      $availableVars = $this->inflectPackageVars(compact('branch', 'tag'));
      $this->buildPrefix = rtrim($this->templatePath($buildPrefix, $availableVars), '/');
    }

      /**
       * @param string $pathType
       * @param string $dirType
       */
      public function getOriginalDirectory($pathType = 'absolute', $dirType = 'baseDir') {
        if (isset($this->originalDirectories[$pathType][$dirType])) {
          return $this->originalDirectories[$pathType][$dirType];
        }
      }

      /**
       * @param string $pathType
       * @param string $dirType
       *
       * @todo: Incorporate framework directory to allow multiple frameworks to be built.
       */
      public function getBuildDirectory($pathType = 'absolute', $dirType = 'baseDir') {
        if (isset($this->buildDirectories[$pathType][$dirType])) {
          return $this->buildDirectories[$pathType][$dirType];
        }
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
     * Search through a passed paths array for a custom install path.
     *
     * @param  array  $paths
     * @param  string $name
     * @param  string $type
     * @param  string $vendor = NULL
     * @return string
     */
    protected function mapCustomInstallPaths(array $paths, $name, $type, $vendor = NULL)
    {
        foreach ($paths as $path => $names) {
            if (in_array($name, $names) || in_array('type:' . $type, $names) || in_array('vendor:' . $vendor, $names)) {
                return $path;
            }
        }
        return false;
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
       * Set composer extra config.
       *
       * @return array
       */
      public function setExtra($extraConfig)
      {
        $this->extraConfig = $extraConfig;
      }

      /**
       * Return composer extra config.
       *
       * @return array
       */
      public function getExtra()
      {
        return $this->extraConfig;
      }
}
