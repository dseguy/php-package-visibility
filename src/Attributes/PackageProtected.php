<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Attributes;

use Attribute;

/**
 * Marks a class, interface, trait, or enum as usable within its declaring namespace's
 * ancestor and descendant namespaces, but not sibling namespaces.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PackageProtected
{
}
