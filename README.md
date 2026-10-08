# PHP Package Visibility

[![Packagist](https://img.shields.io/packagist/v/dseguy/php-package-visibility.svg)](https://packagist.org/packages/dseguy/php-package-visibility)
[![License](https://img.shields.io/packagist/l/dseguy/php-package-visibility.svg)](LICENSE)

Namespace-scoped visibility — `private` / `protected` / `public` — for PHP classes,
interfaces, traits, and enums (collectively **CITEs**), enforced by two static-analysis
tools. Nothing changes at the PHP engine level.

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

Two equivalent syntaxes are supported — pick whichever fits a given file.

**Keyword syntax** (not valid PHP as written — see step 2):

```php
namespace App\Billing;

private class InvoiceCalculator {}
```

**Attribute syntax** (already valid, executable PHP — no extra step needed):

```php
namespace App\Billing;

use PhpPackageVisibility\Attributes\PackagePrivate;

#[PackagePrivate]
class InvoiceCalculator {}
```

`private` restricts usage to the exact declaring namespace. `protected` allows the
declaring namespace's ancestor and descendant namespaces, but not siblings. `public`
(the default, unchanged from current PHP behavior) allows use from anywhere. See
[SPECS.md](SPECS.md#visibility-semantics) for the full semantics and worked examples.

### 2. Clean keyword-syntax files before running them

Files using the keyword syntax aren't valid PHP until the modifier is stripped:

```
vendor/bin/pv-clean src/ --out=build/
vendor/bin/pv-clean src/ --in-place
```

Files using only the attribute syntax are left untouched — there's nothing to clean.

### 3. Check your codebase for violations

```
vendor/bin/pv-check src/
```

Exits non-zero and prints one line per violation when it finds a usage of a CITE outside
its declared visibility, e.g.:

```
src/App/Reporting/Exporter.php:12: extends usage of App\Billing\InvoiceCalculator, declared private in namespace App\Billing

1 violation(s) found in 42 file(s).
```

A usage of a class this tool has no declaration for (e.g. a vendor/third-party class) is
treated as public and skipped.

## Development

```
composer install
composer test   # PHPUnit
composer stan   # PHPStan (level 8)
```

## License

MIT. See [LICENSE](LICENSE).
