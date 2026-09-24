<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Checker;

final class FileAnalysisResult
{
    /**
     * @param list<Declaration> $declarations
     * @param list<Usage> $usages
     */
    public function __construct(
        public readonly array $declarations,
        public readonly array $usages,
    ) {
    }
}
