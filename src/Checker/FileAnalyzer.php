<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Checker;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpPackageVisibility\Cleaner\Cleaner;

/**
 * Analyzes a single file, regardless of which visibility syntax (keyword or attribute) it
 * uses: runs it through the Cleaner's extraction pass to get valid PHP plus the
 * keyword-syntax manifest, parses the result, resolves names, and collects declarations
 * and usages.
 */
final class FileAnalyzer
{
    private Parser $parser;

    public function __construct(
        private readonly Cleaner $cleaner = new Cleaner(),
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function analyze(string $file): FileAnalysisResult
    {
        $source = file_get_contents($file);
        if ($source === false) {
            throw new \RuntimeException("Could not read file: {$file}");
        }

        $extraction = $this->cleaner->extract($source);
        $ast = $this->parser->parse($extraction->cleanedSource);

        if ($ast === null) {
            return new FileAnalysisResult([], []);
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $visitor = new FileVisitor($file, $extraction->declaredVisibilities);
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return new FileAnalysisResult($visitor->declarations, $visitor->usages);
    }
}
