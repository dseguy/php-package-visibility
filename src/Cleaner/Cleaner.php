<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Cleaner;

use PhpPackageVisibility\Visibility;

/**
 * Strips keyword-syntax visibility modifiers (private/protected/public before
 * class/interface/trait/enum) from a source file, producing plain, valid, executable PHP.
 *
 * Uses token_get_all() WITHOUT the TOKEN_PARSE flag: TOKEN_PARSE performs grammar-level
 * validation and throws a ParseError on "private class ..." since it isn't valid PHP
 * grammar. Plain lexing has no such restriction and tokenizes the extended syntax fine.
 */
final class Cleaner
{
    /** @var list<int> */
    private const CITE_KEYWORDS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    /** @var list<int> */
    private const SKIPPABLE_MODIFIERS = [T_ABSTRACT, T_FINAL, T_READONLY];

    /** @var array<int, Visibility> */
    private const VISIBILITY_TOKEN_MAP = [
        T_PRIVATE => Visibility::Private,
        T_PROTECTED => Visibility::Protected,
        T_PUBLIC => Visibility::Public,
    ];

    public function clean(string $source): string
    {
        return $this->extract($source)->cleanedSource;
    }

    public function extract(string $source): ExtractionResult
    {
        $tokens = token_get_all($source);
        $count = count($tokens);

        $output = '';
        $eligibleDepth = 0;
        /** @var list<bool> $braceStack true = this brace opened a namespace block */
        $braceStack = [];
        $expectingNamespaceBrace = false;
        $pendingVisibility = null;
        /** @var list<Visibility|null> $declaredVisibilities */
        $declaredVisibilities = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                switch ($token) {
                case '{': 
                    $isNamespaceBrace = $expectingNamespaceBrace;
                    $expectingNamespaceBrace = false;
                    $braceStack[] = $isNamespaceBrace;
                    if (!$isNamespaceBrace) {
                        $eligibleDepth++;
                    }
                    break;
                case '}': 
                    $wasNamespaceBrace = array_pop($braceStack) ?? false;
                    if (!$wasNamespaceBrace) {
                        $eligibleDepth--;
                    }
                    break;
                case ';': 
                    $expectingNamespaceBrace = false;
                    break;
                default: 
                    // ok, let it be
            }

                $output .= $token;
                continue;
            }

            [$id, $text] = $token;

            if ($id === T_NAMESPACE) {
                $expectingNamespaceBrace = true;
                $output .= $text;
                continue;
            }

            if ($eligibleDepth === 0 && isset(self::VISIBILITY_TOKEN_MAP[$id]) && $this->precedesCiteKeyword($tokens, $i)) {
                $pendingVisibility = self::VISIBILITY_TOKEN_MAP[$id];
                $i = $this->skipSingleTrailingWhitespace($tokens, $i);
                continue;
            }

            if ($eligibleDepth === 0 && in_array($id, self::CITE_KEYWORDS, true) && $this->isGenuineDeclaration($tokens, $i)) {
                $declaredVisibilities[] = $pendingVisibility;
                $pendingVisibility = null;
                $output .= $text;
                continue;
            }

            $output .= $text;
        }

        return new ExtractionResult($output, $declaredVisibilities);
    }

    /**
     * Distinguishes a real "class Foo" declaration from other appearances of the same
     * keyword token: `Foo::class` (constant fetch) and `new class {}` (anonymous class)
     * are never immediately followed by an identifier naming the class.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isGenuineDeclaration(array $tokens, int $index): bool
    {
        $count = count($tokens);

        for ($j = $index + 1; $j < $count; $j++) {
            $token = $tokens[$j];

            if (is_string($token)) {
                return false;
            }

            $id = $token[0];

            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            return $id === T_STRING;
        }

        return false;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function precedesCiteKeyword(array $tokens, int $index): bool
    {
        $count = count($tokens);

        for ($j = $index + 1; $j < $count; $j++) {
            $token = $tokens[$j];

            if (is_string($token)) {
                return false;
            }

            $id = $token[0];

            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            if (in_array($id, self::CITE_KEYWORDS, true)) {
                return true;
            }

            if (in_array($id, self::SKIPPABLE_MODIFIERS, true)) {
                continue;
            }

            return false;
        }

        return false;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function skipSingleTrailingWhitespace(array $tokens, int $index): int
    {
        $next = $index + 1;
        $count = count($tokens);

        if ($next < $count && !is_string($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
            return $next;
        }

        return $index;
    }
}
