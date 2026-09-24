<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Cleaner;

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

    /** @var list<int> */
    private const VISIBILITY_TOKENS = [T_PRIVATE, T_PROTECTED, T_PUBLIC];

    public function clean(string $source): string
    {
        $tokens = token_get_all($source);
        $count = count($tokens);

        $output = '';
        $eligibleDepth = 0;
        /** @var list<bool> $braceStack true = this brace opened a namespace block */
        $braceStack = [];
        $expectingNamespaceBrace = false;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '{') {
                    $isNamespaceBrace = $expectingNamespaceBrace;
                    $expectingNamespaceBrace = false;
                    $braceStack[] = $isNamespaceBrace;
                    if (!$isNamespaceBrace) {
                        $eligibleDepth++;
                    }
                } elseif ($token === '}') {
                    $wasNamespaceBrace = array_pop($braceStack) ?? false;
                    if (!$wasNamespaceBrace) {
                        $eligibleDepth--;
                    }
                } elseif ($token === ';') {
                    $expectingNamespaceBrace = false;
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

            if ($eligibleDepth === 0 && in_array($id, self::VISIBILITY_TOKENS, true) && $this->precedesCiteKeyword($tokens, $i)) {
                $i = $this->skipSingleTrailingWhitespace($tokens, $i);
                continue;
            }

            $output .= $text;
        }

        return $output;
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
