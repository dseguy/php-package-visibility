<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Tests\Checker;

use PhpPackageVisibility\Checker\Checker;
use PhpPackageVisibility\Tests\Support\FixtureDirectory;
use PHPUnit\Framework\TestCase;

final class CheckerTest extends TestCase
{
    private FixtureDirectory $fixtures;
    private Checker $checker;

    protected function setUp(): void
    {
        $this->fixtures = new FixtureDirectory();
        $this->checker = new Checker();
    }

    protected function tearDown(): void
    {
        $this->fixtures->cleanup();
    }

    public function testPrivateClassExtendedFromDescendantNamespaceIsAViolation(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar;

        private class A {}
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace App\Bar\Baz;

        use App\Bar\A;

        class B extends A {}
        PHP);

        $violations = $this->checker->check([$a, $b]);

        self::assertCount(1, $violations);
        self::assertSame('App\Bar\A', $violations[0]->declaration->fqcn);
        self::assertSame('extends', $violations[0]->usage->kind);
    }

    public function testProtectedClassExtendedFromDescendantNamespaceIsAllowed(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar\Baz;

        protected class A {}
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace App\Bar\Baz\Qux;

        use App\Bar\Baz\A;

        class B extends A {}
        PHP);

        $violations = $this->checker->check([$a, $b]);

        self::assertCount(0, $violations);
    }

    public function testProtectedClassExtendedFromSiblingNamespaceIsAViolation(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar\Baz;

        protected class A {}
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace App\Bar\Qux;

        use App\Bar\Baz\A;

        class B extends A {}
        PHP);

        $violations = $this->checker->check([$a, $b]);

        self::assertCount(1, $violations);
        self::assertSame('App\Bar\Baz\A', $violations[0]->declaration->fqcn);
    }

    public function testProtectedClassUsedFromAncestorNamespaceIsAllowed(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar\Baz;

        protected class A {}
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace App\Bar;

        use App\Bar\Baz\A;

        class B
        {
            public function make(): A
            {
                return new A();
            }
        }
        PHP);

        $violations = $this->checker->check([$a, $b]);

        self::assertCount(0, $violations);
    }

    public function testPublicIsUsableFromAnywhere(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar;

        public class A {}
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace Somewhere\Else\Entirely;

        use App\Bar\A;

        class B extends A {}
        PHP);

        $violations = $this->checker->check([$a, $b]);

        self::assertCount(0, $violations);
    }

    public function testBareDeclarationWithNoModifierDefaultsToPublic(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar;

        class A {}
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace Somewhere\Else;

        use App\Bar\A;

        class B extends A {}
        PHP);

        self::assertCount(0, $this->checker->check([$a, $b]));
    }

    public function testAttributeSyntaxIsEquivalentToKeywordSyntax(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar;

        use PhpPackageVisibility\Attributes\PackagePrivate;

        #[PackagePrivate]
        class A {}
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace App\Bar\Baz;

        use App\Bar\A;

        class B extends A {}
        PHP);

        $violations = $this->checker->check([$a, $b]);

        self::assertCount(1, $violations);
        self::assertSame('App\Bar\A', $violations[0]->declaration->fqcn);
    }

    public function testDetectsViolationThroughNewInstantiation(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar;

        private class A {}
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace App\Other;

        use App\Bar\A;

        function make(): A
        {
            return new A();
        }
        PHP);

        $violations = $this->checker->check([$a, $b]);

        $kinds = array_map(static fn ($v) => $v->usage->kind, $violations);
        sort($kinds);

        self::assertSame(['new', 'type-hint'], $kinds);
    }

    public function testDetectsViolationThroughStaticAccessAndInstanceof(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar;

        private class A
        {
            public static function make(): void {}
        }
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace App\Other;

        use App\Bar\A;

        function check($x): void
        {
            A::make();
            if ($x instanceof A) {
            }
        }
        PHP);

        $violations = $this->checker->check([$a, $b]);

        $kinds = array_map(static fn ($v) => $v->usage->kind, $violations);
        sort($kinds);

        self::assertSame(['instanceof', 'static-access'], $kinds);
    }

    public function testUsageWithNoKnownDeclarationIsSkipped(): void
    {
        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        namespace App\Other;

        use Vendor\Package\ExternalThing;

        class B extends ExternalThing {}
        PHP);

        self::assertCount(0, $this->checker->check([$b]));
    }

    public function testGlobalNamespaceCountsAsRootAncestorForProtected(): void
    {
        $a = $this->fixtures->write('A.php', <<<'PHP'
        <?php
        namespace App\Bar;

        protected class A {}
        PHP);

        $b = $this->fixtures->write('B.php', <<<'PHP'
        <?php
        use App\Bar\A;

        class B extends A {}
        PHP);

        self::assertCount(0, $this->checker->check([$a, $b]));
    }
}
