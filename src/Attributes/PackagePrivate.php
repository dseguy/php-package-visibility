<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Attributes;

use Attribute;

/**
 * Marks a class, interface, trait, or enum as usable only within its exact declaring namespace.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PackagePrivate
{
}
