<?php

declare(strict_types=1);

namespace PhpPackageVisibility\Attributes;

use Attribute;

/**
 * Marks a class, interface, trait, or enum as usable from anywhere. This is the default
 * when no visibility modifier is present; the attribute exists for explicitness.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PackagePublic
{
}
