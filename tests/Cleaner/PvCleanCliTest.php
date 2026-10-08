<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Tests\Cleaner;

use PhpPackageVisibility\Tests\Support\CliRunner;
use PhpPackageVisibility\Tests\Support\FixtureDirectory;
use PHPUnit\Framework\TestCase;

final class PvCleanCliTest extends TestCase
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

    public function testCleansADirectoryToAnOutputDirectory(): void
    {
        $src = $this->fixtures->write('src/Widget.php', <<<'PHP'
        <?php

        namespace App\Widgets;

        private class Widget
        {
            public function name(): string
            {
                return 'widget';
            }
        }
        PHP);

        $outDir = dirname($src, 2) . '/out';

        $result = CliRunner::run('pv-clean', [dirname($src), '--out=' . $outDir]);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('Cleaned 1 file(s).', $result['output']);

        $cleanedFile = $outDir . '/Widget.php';
        self::assertFileExists($cleanedFile);

        $cleaned = file_get_contents($cleanedFile);
        self::assertIsString($cleaned);
        self::assertStringNotContainsString('private class', $cleaned);
        self::assertStringContainsString('class Widget', $cleaned);

        exec('php -l ' . escapeshellarg($cleanedFile) . ' 2>&1', $lintOutput, $lintExitCode);
        self::assertSame(0, $lintExitCode, implode("\n", $lintOutput));
    }

    public function testCleansInPlace(): void
    {
        $file = $this->fixtures->write('Widget.php', <<<'PHP'
        <?php

        protected interface WidgetContract {}
        PHP);

        $result = CliRunner::run('pv-clean', [$file, '--in-place']);

        self::assertSame(0, $result['exitCode'], $result['output']);

        $cleaned = file_get_contents($file);
        self::assertSame("<?php\n\ninterface WidgetContract {}", $cleaned);
    }

    public function testLeavesAttributeSyntaxFileUnchanged(): void
    {
        $source = <<<'PHP'
        <?php

        #[PackagePrivate]
        class Widget {}
        PHP;

        $file = $this->fixtures->write('Widget.php', $source);

        $result = CliRunner::run('pv-clean', [$file, '--in-place']);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertSame($source, file_get_contents($file));
    }

    public function testFailsWithUsageWhenNoModeIsGiven(): void
    {
        $file = $this->fixtures->write('Widget.php', "<?php\nclass Widget {}\n");

        $result = CliRunner::run('pv-clean', [$file]);

        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('Usage:', $result['output']);
    }
}
