<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Cleaner;

use PhpPackageVisibility\Visibility;

/**
 * Result of cleaning a source file: the plain PHP it reduces to, plus the keyword-syntax
 * visibility (if any) found for each top-level CITE declaration, in source order.
 */
final class ExtractionResult
{
    /**
     * @param list<Visibility|null> $declaredVisibilities One entry per CITE declaration
     *     encountered, in source order; null when the declaration had no keyword modifier
     *     (either a bare declaration or one using the attribute syntax instead).
     */
    public function __construct(
        public readonly string $cleanedSource,
        public readonly array $declaredVisibilities,
    ) {
    }
}
