<?php

namespace VerbruggenAlex\ComposerBuilder;

use Composer\Util\Filesystem;

class SymlinkFilesystem extends Filesystem
{
    /**
     * Create a symlink
     *
     * @param string $sourcePath
     * @param string $symlinkPath
     *
     * @return bool
     */
    public function ensureSymlinkExists($sourcePath, $symlinkPath)
    {
        if (!is_link($symlinkPath)) {
            $this->ensureDirectoryExists(dirname($symlinkPath));

            return $this->relativeSymlink($sourcePath, $symlinkPath);
        }

        return false;
    }

    /**
     * @param string $symlinkPath
     *
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function removeSymlink($symlinkPath)
    {
        if (is_link($symlinkPath)) {
            if (!$this->unlink($symlinkPath)) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('Unable to remove the symlink : ' . $symlinkPath);
                // @codeCoverageIgnoreEnd
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $directoryPath
     *
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function removeEmptyDirectory($directoryPath)
    {
        if (is_dir($directoryPath) && $this->isDirEmpty($directoryPath)) {
            if (!$this->removeDirectory($directoryPath)) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('Unable to remove the directory : ' . $directoryPath);
                // @codeCoverageIgnoreEnd
            }

            return true;
        }

        return false;
    }
}