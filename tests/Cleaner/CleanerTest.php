<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Tests\Cleaner;

use PhpPackageVisibility\Cleaner\Cleaner;
use PHPUnit\Framework\TestCase;

final class CleanerTest extends TestCase
{
    private Cleaner $cleaner;

    protected function setUp(): void
    {
        $this->cleaner = new Cleaner();
    }

    public function testStripsPrivateClass(): void
    {
        $source = "<?php\nprivate class ConnectionPool {}\n";
        $expected = "<?php\nclass ConnectionPool {}\n";

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testStripsProtectedInterface(): void
    {
        $source = "<?php\nprotected interface RepositoryContract {}\n";
        $expected = "<?php\ninterface RepositoryContract {}\n";

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testStripsPublicTrait(): void
    {
        $source = "<?php\npublic trait Loggable {}\n";
        $expected = "<?php\ntrait Loggable {}\n";

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testStripsPrivateEnum(): void
    {
        $source = "<?php\nprivate enum RetryPolicy {}\n";
        $expected = "<?php\nenum RetryPolicy {}\n";

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testStripsVisibilityBeforeOtherModifiers(): void
    {
        $source = "<?php\nprivate abstract class BaseHandler {}\n";
        $expected = "<?php\nabstract class BaseHandler {}\n";

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testStripsVisibilityAfterOtherModifiers(): void
    {
        $source = "<?php\nabstract private class BaseHandler {}\n";
        $expected = "<?php\nabstract class BaseHandler {}\n";

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testStripsVisibilityBetweenMultipleModifiers(): void
    {
        $source = "<?php\nfinal private readonly class Value {}\n";
        $expected = "<?php\nfinal readonly class Value {}\n";

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testStripsMultipleCitesWithMixedVisibility(): void
    {
        $source = <<<'PHP'
        <?php
        private class A {}
        protected interface B {}
        public trait C {}
        class D {}
        PHP;

        $expected = <<<'PHP'
        <?php
        class A {}
        interface B {}
        trait C {}
        class D {}
        PHP;

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testStripsInsideNamespaceBlockForm(): void
    {
        $source = <<<'PHP'
        <?php
        namespace App\Bar {
            private class B {}
            protected class C {}
        }
        PHP;

        $expected = <<<'PHP'
        <?php
        namespace App\Bar {
            class B {}
            class C {}
        }
        PHP;

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testStripsInsideSingleStatementNamespaceForm(): void
    {
        $source = <<<'PHP'
        <?php
        namespace App\Bar;

        private class B {}
        PHP;

        $expected = <<<'PHP'
        <?php
        namespace App\Bar;

        class B {}
        PHP;

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testDoesNotTouchAttributeSyntax(): void
    {
        $source = <<<'PHP'
        <?php
        #[PackagePrivate]
        class ConnectionPool {}
        PHP;

        self::assertSame($source, $this->cleaner->clean($source));
    }

    public function testLeavesMixedSyntaxFilePartiallyCleaned(): void
    {
        $source = <<<'PHP'
        <?php
        private class A {}

        #[PackageProtected]
        class B {}
        PHP;

        $expected = <<<'PHP'
        <?php
        class A {}

        #[PackageProtected]
        class B {}
        PHP;

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testDoesNotTouchAnonymousClasses(): void
    {
        $source = <<<'PHP'
        <?php
        $x = new class {};
        $y = new class extends Foo {};
        PHP;

        self::assertSame($source, $this->cleaner->clean($source));
    }

    public function testDoesNotTouchClassMemberModifiers(): void
    {
        $source = <<<'PHP'
        <?php
        class Foo
        {
            private $x;
            protected $y;
            public $z;

            private function bar() {}
        }
        PHP;

        self::assertSame($source, $this->cleaner->clean($source));
    }

    public function testDoesNotTouchConstructorPromotedProperties(): void
    {
        $source = <<<'PHP'
        <?php
        class Foo
        {
            public function __construct(private string $x, protected int $y) {}
        }
        PHP;

        self::assertSame($source, $this->cleaner->clean($source));
    }

    public function testDoesNotConfuseRelativeNamespaceOperatorWithDeclaration(): void
    {
        $source = <<<'PHP'
        <?php
        namespace App;

        private class A {}

        $x = namespace\Something;
        PHP;

        $expected = <<<'PHP'
        <?php
        namespace App;

        class A {}

        $x = namespace\Something;
        PHP;

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testPreservesCommentsAndFormatting(): void
    {
        $source = "<?php\n/** Doc */\nprivate class A\n{\n    // body\n}\n";
        $expected = "<?php\n/** Doc */\nclass A\n{\n    // body\n}\n";

        self::assertSame($expected, $this->cleaner->clean($source));
    }

    public function testIsIdempotentOnAlreadyCleanSource(): void
    {
        $source = "<?php\nclass A {}\ninterface B {}\n";

        self::assertSame($source, $this->cleaner->clean($source));
    }

    public function testCleanedOutputIsValidPhp(): void
    {
        $source = <<<'PHP'
        <?php
        namespace App\Bar;

        private abstract class BaseHandler {}
        protected interface RepositoryContract {}
        public trait Loggable {}
        private enum RetryPolicy {}
        PHP;

        $cleaned = $this->cleaner->clean($source);
        $ast = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse($cleaned);

        self::assertIsArray($ast);
        self::assertNotEmpty($ast);
    }
}
