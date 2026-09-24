<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Checker;

final class Violation
{
    public function __construct(
        public readonly Declaration $declaration,
        public readonly Usage $usage,
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '%s:%d: %s usage of %s, declared %s in namespace %s',
            $this->usage->file,
            $this->usage->line,
            $this->usage->kind,
            $this->declaration->fqcn,
            $this->declaration->visibility->value,
            $this->declaration->namespace === '' ? '\\' : $this->declaration->namespace,
        );
    }
}
