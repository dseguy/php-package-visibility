<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Checker;

use PhpPackageVisibility\Visibility;

/**
 * Implements the namespace-containment rules from SPECS.md's Visibility semantics section.
 */
final class NamespaceRules
{
    public function isPermitted(Visibility $visibility, string $declaringNamespace, string $usageNamespace): bool
    {
        return match ($visibility) {
            Visibility::Public => true,
            Visibility::Private => $this->segments($declaringNamespace) === $this->segments($usageNamespace),
            Visibility::Protected => $this->isAncestorOrSelf($this->segments($usageNamespace), $this->segments($declaringNamespace))
                || $this->isAncestorOrSelf($this->segments($declaringNamespace), $this->segments($usageNamespace)),
        };
    }

    /**
     * @return list<string>
     */
    private function segments(string $namespace): array
    {
        $namespace = trim($namespace, characters: '\\');

        return $namespace === '' ? [] : explode('\\', $namespace);
    }

    /**
     * True when $maybeAncestor is the same namespace as $namespace, or one of its
     * containing namespaces (including the global namespace, represented as []).
     *
     * @param list<string> $maybeAncestor
     * @param list<string> $namespace
     */
    private function isAncestorOrSelf(array $maybeAncestor, array $namespace): bool
    {
        return count($maybeAncestor) <= count($namespace)
            && array_slice($namespace, offset: 0, length: count($maybeAncestor)) === $maybeAncestor;
    }
}
