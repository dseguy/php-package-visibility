<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Checker;

/**
 * Analyzes a whole project: builds a registry of every CITE declaration found, and checks
 * every usage found anywhere in the same set of files against that registry.
 *
 * A usage whose target isn't in the registry (e.g. a vendor/third-party class) is treated
 * as public/skipped — the checker only enforces visibility for CITEs it can see declared.
 */
final class Checker
{
    public function __construct(
        private readonly FileAnalyzer $analyzer = new FileAnalyzer(),
        private readonly NamespaceRules $rules = new NamespaceRules(),
    ) {
    }

    /**
     * @param list<string> $files
     * @return list<Violation>
     */
    public function check(array $files): array
    {
        /** @var array<string, Declaration> $declarations */
        $declarations = [];
        /** @var list<Usage> $usages */
        $usages = [];

        foreach ($files as $file) {
            $result = $this->analyzer->analyze($file);

            foreach ($result->declarations as $declaration) {
                $declarations[$declaration->fqcn] = $declaration;
            }

            array_push($usages, ...$result->usages);
        }

        $violations = [];

        foreach ($usages as $usage) {
            $declaration = $declarations[$usage->targetFqcn] ?? null;

            if ($declaration === null) {
                continue;
            }

            if (!$this->rules->isPermitted($declaration->visibility, $declaration->namespace, $usage->usageNamespace)) {
                $violations[] = new Violation($declaration, $usage);
            }
        }

        return $violations;
    }
}
