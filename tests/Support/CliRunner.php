<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Tests\Support;

final class CliRunner
{
    /**
     * @param list<string> $args
     * @return array{exitCode: int, output: string}
     */
    public static function run(string $binName, array $args): array
    {
        $binPath = dirname(__DIR__, 2) . '/bin/' . $binName;

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($binPath);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        exec($command . ' 2>&1', $outputLines, $exitCode);

        return ['exitCode' => $exitCode, 'output' => implode("\n", $outputLines)];
    }
}
