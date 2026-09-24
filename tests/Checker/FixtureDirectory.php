<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Tests\Checker;

/**
 * Writes an in-memory set of PHP files to a temp directory for a single test, and cleans
 * up afterwards.
 */
final class FixtureDirectory
{
    private string $dir;

    public function __construct()
    {
        $this->dir = sys_get_temp_dir() . '/pv-checker-test-' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0777, true);
    }

    /**
     * @return string the absolute path written
     */
    public function write(string $relativePath, string $contents): string
    {
        $path = $this->dir . '/' . $relativePath;
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }

    public function cleanup(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->dir);
    }
}
