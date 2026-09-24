<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Tests\Checker;

use PhpPackageVisibility\Checker\NamespaceRules;
use PhpPackageVisibility\Visibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NamespaceRulesTest extends TestCase
{
    private NamespaceRules $rules;

    protected function setUp(): void
    {
        $this->rules = new NamespaceRules();
    }

    public function testPublicIsAlwaysPermitted(): void
    {
        self::assertTrue($this->rules->isPermitted(Visibility::Public, 'a\\b\\c', 'x\\y\\z'));
        self::assertTrue($this->rules->isPermitted(Visibility::Public, 'a\\b\\c', ''));
    }

    public function testPrivateOnlyPermitsExactSameNamespace(): void
    {
        self::assertTrue($this->rules->isPermitted(Visibility::Private, 'a\\b', 'a\\b'));
        self::assertFalse($this->rules->isPermitted(Visibility::Private, 'a\\b', 'a\\b\\c'));
        self::assertFalse($this->rules->isPermitted(Visibility::Private, 'a\\b', 'a'));
        self::assertFalse($this->rules->isPermitted(Visibility::Private, 'a\\b', 'a\\x'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function protectedCases(): iterable
    {
        yield 'declaring namespace itself' => ['a\\b\\c', true];
        yield 'direct parent' => ['a\\b', true];
        yield 'root ancestor' => ['a', true];
        yield 'global namespace (root of everything)' => ['', true];
        yield 'descendant' => ['a\\b\\c\\d', true];
        yield 'deep descendant' => ['a\\b\\c\\d\\e', true];
        yield 'sibling' => ['a\\b\\x', false];
        yield 'unrelated' => ['x\\y', false];
    }

    #[DataProvider('protectedCases')]
    public function testProtectedFollowsNamespaceContainmentTree(string $usageNamespace, bool $expected): void
    {
        self::assertSame($expected, $this->rules->isPermitted(Visibility::Protected, 'a\\b\\c', $usageNamespace));
    }

    public function testLeadingAndTrailingBackslashesAreIgnored(): void
    {
        self::assertTrue($this->rules->isPermitted(Visibility::Private, '\\a\\b\\', 'a\\b'));
        self::assertTrue($this->rules->isPermitted(Visibility::Protected, 'a\\b\\c', '\\a\\'));
    }
}
