<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Checker;

final class Usage
{
    public function __construct(
        public readonly string $targetFqcn,
        public readonly string $usageNamespace,
        public readonly string $file,
        public readonly int $line,
        public readonly string $kind,
    ) {
    }
}
