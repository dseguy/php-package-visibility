<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Checker;

use PhpPackageVisibility\Visibility;

final class Declaration
{
    public function __construct(
        public readonly string $fqcn,
        public readonly string $namespace,
        public readonly Visibility $visibility,
        public readonly string $file,
        public readonly int $line,
    ) {
    }
}
