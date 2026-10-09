<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Checker;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpPackageVisibility\Visibility;

/**
 * Collects CITE declarations and CITE usages from a single, already name-resolved AST
 * (i.e. after running PhpParser\NodeVisitor\NameResolver over it).
 *
 * Declarations and usages are collected in the same pass because both need the same
 * "which namespace am I currently inside" tracking.
 */
final class FileVisitor extends NodeVisitorAbstract
{
    private const ATTRIBUTE_VISIBILITY_MAP = [
        'PhpPackageVisibility\Attributes\PackagePrivate' => Visibility::Private,
        'PhpPackageVisibility\Attributes\PackageProtected' => Visibility::Protected,
        'PhpPackageVisibility\Attributes\PackagePublic' => Visibility::Public,
    ];

    /** @var list<Declaration> */
    public array $declarations = [];

    /** @var list<Usage> */
    public array $usages = [];

    private int $declarationCursor = 0;

    /** @var list<string> */
    private array $namespaceStack = [''];

    /**
     * @param list<Visibility|null> $keywordVisibilities Cleaner::extract()'s manifest for
     *     this same file, in source order — one entry per CITE declaration.
     */
    public function __construct(
        private readonly string $file,
        private readonly array $keywordVisibilities,
    ) {
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespaceStack[] = $node->name?->toString() ?? '';
        }

        if ($node instanceof Node\Stmt\ClassLike && $node->name !== null) {
            $this->recordDeclaration($node);
        }

        match (true) {
            $node instanceof Node\Stmt\Class_ => $this->recordClassUsages($node),
            $node instanceof Node\Stmt\Interface_ => $this->recordNames($node->extends, 'extends'),
            $node instanceof Node\Stmt\Enum_ => $this->recordNames($node->implements, 'implements'),
            $node instanceof Node\Stmt\TraitUse => $this->recordNames($node->traits, 'trait-use'),
            $node instanceof Node\Expr\New_ => $this->recordExpr($node->class, 'new'),
            $node instanceof Node\Expr\Instanceof_ => $this->recordExpr($node->class, 'instanceof'),
            $node instanceof Node\Stmt\Catch_ => $this->recordNames($node->types, 'catch'),
            $node instanceof Node\Expr\ClassConstFetch => $this->recordExpr($node->class, 'static-access'),
            $node instanceof Node\Expr\StaticCall => $this->recordExpr($node->class, 'static-access'),
            $node instanceof Node\Expr\StaticPropertyFetch => $this->recordExpr($node->class, 'static-access'),
            $node instanceof Node\Param => $this->recordType($node->type, 'type-hint'),
            $node instanceof Node\Stmt\Property => $this->recordType($node->type, 'type-hint'),
            $node instanceof Node\FunctionLike => $this->recordType($node->getReturnType(), 'type-hint'),
            $node instanceof Node\AttributeGroup => $this->recordAttributeUsages($node),
            default => null,
        };

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            array_pop($this->namespaceStack);
        }

        return null;
    }

    private function currentNamespace(): string
    {
        $index = array_key_last($this->namespaceStack);

        return $index === null ? '' : $this->namespaceStack[$index];
    }

    private function recordDeclaration(Node\Stmt\ClassLike $node): void
    {
        $fqcn = $node->namespacedName?->toString() ?? $node->name?->toString() ?? '';

        $keywordVisibility = $this->keywordVisibilities[$this->declarationCursor] ?? null;
        $this->declarationCursor++;

        $visibility = $keywordVisibility ?? $this->attributeVisibility($node) ?? Visibility::Public;

        $this->declarations[] = new Declaration(
            $fqcn,
            $this->currentNamespace(),
            $visibility,
            $this->file,
            $node->getStartLine(),
        );
    }

    private function attributeVisibility(Node\Stmt\ClassLike $node): ?Visibility
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $visibility = self::ATTRIBUTE_VISIBILITY_MAP[$attr->name->toString()] ?? null;
                if ($visibility !== null) {
                    return $visibility;
                }
            }
        }

        return null;
    }

    private function recordClassUsages(Node\Stmt\Class_ $node): void
    {
        if ($node->extends !== null) {
            $this->recordExpr($node->extends, 'extends');
        }

        $this->recordNames($node->implements, 'implements');
    }

    private function recordAttributeUsages(Node\AttributeGroup $group): void
    {
        foreach ($group->attrs as $attr) {
            $this->recordExpr($attr->name, 'attribute');
        }
    }

    private function recordType(Node\Identifier|Node\Name|Node\ComplexType|null $type, string $kind): void
    {
        foreach ($this->extractNames($type) as $name) {
            $this->recordExpr($name, $kind);
        }
    }

    /**
     * @return array<Node\Name>
     */
    private function extractNames(Node\Identifier|Node\Name|Node\ComplexType|null $type): array
    {
        return match (true) {
            $type instanceof Node\Name => [$type],
            $type instanceof Node\NullableType => $this->extractNames($type->type),
            $type instanceof Node\UnionType, $type instanceof Node\IntersectionType => array_merge(
                [],
                ...array_map($this->extractNames(...) , $type->types),
            ),
            default => [],
        };
    }

    /**
     * @param array<Node\Name> $names
     */
    private function recordNames(array $names, string $kind): void
    {
        foreach ($names as $name) {
            $this->recordExpr($name, $kind);
        }
    }

    private function recordExpr(?Node $target, string $kind): void
    {
        if (!$target instanceof Node\Name\FullyQualified) {
            return;
        }

        $this->usages[] = new Usage(
            ltrim($target->toString(), characters: '\\'),
            $this->currentNamespace(),
            $this->file,
            $target->getStartLine(),
            $kind,
        );
    }
}
