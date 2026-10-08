<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Tests\Checker;

use PhpPackageVisibility\Tests\Support\CliRunner;
use PhpPackageVisibility\Tests\Support\FixtureDirectory;
use PHPUnit\Framework\TestCase;

final class PvCheckCliTest extends TestCase
{
    private FixtureDirectory $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = new FixtureDirectory();
    }

    protected function tearDown(): void
    {
        $this->fixtures->cleanup();
    }

    public function testExitsZeroAndReportsCleanWhenNoViolations(): void
    {
        $dir = dirname($this->fixtures->write('App/A.php', <<<'PHP'
        <?php
        namespace App;

        class A {}
        PHP), 1);

        $result = CliRunner::run('pv-check', [$dir]);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('No visibility violations found', $result['output']);
    }

    public function testExitsNonZeroAndReportsViolations(): void
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

        class B extends A {}
        PHP);

        $result = CliRunner::run('pv-check', [dirname($a)]);

        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('App\Bar\A', $result['output']);
        self::assertStringContainsString('declared private', $result['output']);
        self::assertStringContainsString('1 violation(s)', $result['output']);
    }

    public function testAcceptsMultipleExplicitPaths(): void
    {
        $a = $this->fixtures->write('a/A.php', <<<'PHP'
        <?php
        namespace App\Bar;

        private class A {}
        PHP);

        $b = $this->fixtures->write('b/B.php', <<<'PHP'
        <?php
        namespace App\Other;

        use App\Bar\A;

        class B extends A {}
        PHP);

        $result = CliRunner::run('pv-check', [$a, $b]);

        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('1 violation(s)', $result['output']);
    }

    public function testFailsWithUsageWhenNoPathIsGiven(): void
    {
        $result = CliRunner::run('pv-check', []);

        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('Usage:', $result['output']);
    }
}
