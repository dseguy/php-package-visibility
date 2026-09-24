<?php

declare(strict_types=1);

namespace PhpPackageVisibility;

/**
 * Internal representation shared by the Cleaner and the Checker, regardless of which
 * source syntax (keyword or attribute) a CITE declaration used.
 */
enum Visibility: string
{
    case Private = 'private';
    case Protected = 'protected';
    case Public = 'public';
}
