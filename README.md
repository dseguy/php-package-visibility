# PHP Package Visibility

[![Packagist](https://img.shields.io/packagist/v/dseguy/php-package-visibility.svg)](https://packagist.org/packages/dseguy/php-package-visibility)
[![License](https://img.shields.io/packagist/l/dseguy/php-package-visibility.svg)](LICENSE)

Namespace-scoped visibility, aka `private` / `protected` / `public` but for PHP classes,
interfaces, traits, and enums collectively called **CITEs**, enforced by a static analysis
tool. Nothing changes at the PHP engine level.

PHP has no concept of a namespace-internal class: anything declared in a namespace is
usable from anywhere in the codebase. This package lets you mark a CITE as restricted to
its own namespace, or to its namespace's ancestor/descendant chain, and then statically
check your codebase for violations.

See [SPECS.md](SPECS.md) for the full specification.

## Installation

```
composer require dseguy/php-package-visibility
```

## Usage

### 1. Mark a CITE's visibility

Two equivalent syntaxes are supported. Pick whichever fits your style.

**Keyword syntax**:

```php
namespace App\Billing;

private class InvoiceCalculator {}
```

This is not valid PHP as written, so it requires the `pv-clean` to run on the code before PHP can.

**Attribute syntax**:

```php
namespace App\Billing;

use PhpPackageVisibility\Attributes\PackagePrivate;

#[PackagePrivate]
class InvoiceCalculator {}
```

This is awlays valid, executable PHP, no extra step needed. It works on current and modern versions.

`private` restricts usage to the exact declaring namespace. `protected` allows the
declaring namespace's ancestor and descendant namespaces, but not siblings, uncles and aunties, etc. `public`, which is the default spec and is unchanged from current PHP behavior, allows use from anywhere. See
[SPECS.md](SPECS.md#visibility-semantics) for the full semantics and worked examples.

### 2. Clean keyword-syntax files before running them

Files using the keyword syntax aren't valid PHP until the modifier is stripped:

```
vendor/bin/pv-clean src/ --out=build/
vendor/bin/pv-clean src/ --in-place
```

Files using only the attribute syntax are left untouched. There's nothing to clean.
This tool can be added to production pipeline.

### 3. Check the codebase for violations

```
vendor/bin/pv-check src/
```

`pv-check` exits with non-zero and prints one line per violation when it finds a usage of a CITE outside
its declared visibility, e.g.:

```
src/App/Reporting/Exporter.php:12: extends usage of App\Billing\InvoiceCalculator, declared private in namespace App\Billing

1 violation(s) found in 42 file(s).
```

Usages of a CITE without any declaration, such as a vendor/third-party class, a library, some 
wip code, is treated as `public` and yield no warning, just like PHP now.

#### Checks run by `pv-check`

For every CITE declared in the analyzed files, the checker verifies each usage of that
CITE against its declared visibility. A usage is checked when it appears as:

- `extends`: a class extending a CITE, or an interface extending one
- `implements`:  a class or enum implementing a CITE
- `trait-use`:  a `use` statement inside a class-like body importing a trait
- `new`:  an instantiation with `new X`
- `instanceof`: an `instanceof X` check
- `catch`: a `catch (X $e)` clause
- `static-access`: static calls, constant fetches, and static property fetches `X::...`. No dynamic static calls with objects.
- `type-hint`:  parameter, return, and property types, including nullable, union, and intersection types
- `attribute`:  a class name used as an attribute `#[X]`

A violation is reported when the usage's namespace is not permitted by the target's
visibility:

- `private`:  the usage must be in the exact same namespace as the declaration
- `protected`: the usage must be in an ancestor or descendant namespace of the declaration (siblings are rejected)
- `public`: every usage is permitted

Names are resolved first, taking `use` imports, aliases, and relative names into account,
so a short name is checked against its fully-qualified target. Declarations are read from
both syntaxes, keyword and attribute. A CITE with no declaration in the analyzed files is
not checked.

## Development

```
composer install
composer test   # PHPUnit
composer stan   # PHPStan (level 8)
```

## License

MIT. See [LICENSE](LICENSE).
