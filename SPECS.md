# Specification — PHP Package Visibility

## Overview

PHP has no namespace-scoped visibility. Any class, interface, trait, or enum (collectively **CITE** in this document) declared in a namespace is globally usable from anywhere in the codebase, regardless of how deeply internal it's meant to be. This project adds an opt-in, namespace-scoped visibility system, namely `private` / `protected` / `public`, on top of PHP, without changing the language or engine.

The system is enforced by two cooperating command-line utilities rather than a runtime check:

1. **Cleaner**: strips the extended syntax down to plain, valid, executable PHP. This is a build/deploy step.
2. **Checker**: statically analyzes a codebase and reports every usage that violates a CITE's declared visibility. It is a preprocessor, a lint/CI step.

Both utilities are static-analysis tools. Nothing changes at the PHP engine level, and shipped/deployed code (after cleaning, or written with attributes) is always plain, standard PHP.

## Package metadata

- Name: `dseguy/php-package-visibility` (placeholder — confirm on init)
- PHP version target: 8.1+ (enums require 8.1; attributes require 8.0+)
- Runtime dependency: `nikic/php-parser` (`^5.0`, matching sibling projects `typedphp` and `php-lazy-constants`)
- Dev dependencies: `phpunit/phpunit` (`^11.0`), `phpstan/phpstan` (`^2.0`)

## Design principles

- **Namespaced CITEs only**. Visibility applies exclusively to classes, interfaces, traits, and enums declared inside a namespace. CITEs declared in the global namespace (no `namespace` statement) are out of scope and always treated as public.
- **Anonymous classes are excluded**. `new class {}` / `new class extends X {}` expressions never carry a visibility modifier and are never subject to visibility checks as declarations (they can still be *usages* of other CITEs, e.g. via `extends`).
- **Public by default, fully backward compatible**. A CITE with no visibility keyword/attribute behaves exactly as PHP behaves today: usable from anywhere. Adopting this system is opt-in, file by file.
- **Two independent, equivalent syntaxes**. Authors may express visibility as a language-like keyword or as a PHP attribute; the two are semantically identical and interchangeable across a codebase. See [Syntax](#syntax).
- **Inheritance is not special-cased**. `extends`, `implements`, and trait `use` are just syntax that names a CITE; they are checked exactly like any other usage. See [Inheritance and visibility](#inheritance-and-visibility).

## Visibility semantics

- **`public`** (default): usable from any namespace, anywhere in the codebase. Identical to current PHP behavior.
- **`private`**: usable only within the *exact* namespace where the CITE is declared. Not usable from parent namespaces, child namespaces, or siblings.
- **`protected`**: usable within the declaring namespace's containment-tree chain: all of its **ancestor** namespaces and all of its **descendant** namespaces, but not siblings.

  Example: a CITE declared `protected` in `a\b\c` is usable from:

  | Namespace   | Relationship to `a\b\c` | Allowed? |
  |-------------|--------------------------|----------|
  | `a`         | ancestor                 | yes      |
  | `a\b`       | ancestor                 | yes      |
  | `a\b\c`     | declaring namespace      | yes      |
  | `a\b\c\d`   | descendant               | yes      |
  | `a\b\x`     | sibling                  | **no**   |
  | `w\x`       | unrelated                | **no**   |

> **Terminology note.** This `protected` is *not* the OOP inheritance-based `protected` you'd expect from class members. Class-member `protected` is bidirectional (a class and its super and subclasses only) and inheritance-based. This `protected` is bidirectional, ancestors *and* descendants, and based purely on namespace containment:  there is no subclass relationship involved. The keyword is deliberately reused from PHP's existing vocabulary for familiarity, but the semantics differ; readers of this spec and its implementation should not assume OOP-protected behavior.

## Syntax

Exactly one visibility modifier (or none, meaning public) applies to a given CITE declaration. Both syntaxes below are equivalent; a codebase may mix them freely across files.

### Keyword syntax

The modifier is placed immediately before the CITE keyword, alongside any existing modifiers (`abstract`, `final`, `readonly`) in either order:

```php
private class ConnectionPool {}
protected interface RepositoryContract {}
public trait Loggable {}
private enum RetryPolicy {}
private abstract class BaseHandler {}
```

**This is not valid PHP as written.** `private`, `protected`, and `public` are not currently legal modifiers on a CITE declaration, so a file using this syntax must be processed by the [Cleaner](#utility-1-cleaner) before it can run. It never applies to anonymous classes (`new class {}` has no CITE-level modifier position to occupy).

### Attribute syntax

The modifier is expressed as a native PHP attribute on the declaration:

```php
#[PackagePrivate]
class ConnectionPool {}

#[PackageProtected]
interface RepositoryContract {}

#[PackagePublic]
trait Loggable {}
```

> **Naming note.** `private`, `protected`, and `public` are PHP reserved words and cannot be used to name a class, interface, trait, or enum. Since an attribute is just a reference to a class (`#[X]` resolves to a class `X`), the marker attributes cannot be named `Private`/`Protected`/`Public` and are instead named `PackagePrivate`/`PackageProtected`/`PackagePublic` — the `Package` prefix also usefully distinguishes them from member-level visibility at a glance.

**This is already valid, executable PHP.** No preprocessing is required — the attribute is inert at runtime (unless something explicitly reflects on it) and the file runs as-is. The Cleaner passes attribute-syntax files through unchanged.

## Inheritance and visibility

`extends`, `implements`, and trait `use` are references to a CITE name, exactly like `new X`, a type hint, `instanceof X`, `catch (X $e)`, or `X::method()`. They are checked with the same rule as any other usage: the *referencing* CITE's own declaring namespace is the usage location, checked against the *target* CITE's declared visibility.

Confirmed examples:

- `private class A` declared in `a\b`; `class B extends A` declared in `a\b\c` => **violation** (private only permits the exact same namespace).
- `protected class A` declared in `a\b\c`; `class B extends A` declared in `a\b\c\d` => **allowed** (descendant namespace).
- `protected class A` declared in `a\b\c`; `class B extends A` declared in `a\b\x` => **violation** (sibling namespace).

No additional rule constrains the visibility of the *inheriting* CITE relative to its parent — a `public` class may extend a `protected` or `private` one, provided the `extends` reference itself is legal per the table above. See [Out of scope](#out-of-scope-v1) for the rejected alternative (visibility monotonicity).

## Utility 1: Cleaner

**Purpose:** convert keyword-syntax source into plain, valid, executable PHP, typically as a build or deploy step.

**Algorithm:**

1. Tokenize the file with PHP's built-in tokenizer (`token_get_all`).
2. Track brace depth. Real CITE declarations only ever occur at file top level or inside a `namespace { ... }` block — PHP has no nested class/interface/trait/enum declarations (only anonymous class *expressions*, which never carry this modifier). Only consider modifier tokens found at that depth.
3. At that depth, when a `T_PRIVATE`, `T_PROTECTED`, or `T_PUBLIC` token is immediately followed — skipping only whitespace/comments and any of `T_ABSTRACT` / `T_FINAL` / `T_READONLY` in either order — by `T_CLASS`, `T_INTERFACE`, `T_TRAIT`, or `T_ENUM`, treat it as a CITE visibility modifier.
4. Drop that modifier token and its trailing whitespace; re-emit every other token verbatim, byte for byte.
5. Files using only the attribute syntax pass through unchanged — there is nothing to strip.

Re-emitting tokens verbatim (rather than round-tripping through an AST pretty-printer) preserves exact source formatting, comments, and line numbers, so line numbers in stack traces and coverage reports stay meaningful after cleaning.

**CLI shape:**

```
bin/clean src/ --out=build/
bin/clean src/ --in-place
```

## Utility 2: Checker

**Purpose:** statically analyze a project's source tree and report every usage of a CITE that falls outside its declared visibility's permitted namespace scope.

**Usage forms checked (uniformly, per [Inheritance and visibility](#inheritance-and-visibility)):**

`new`, `extends`, `implements`, trait `use`, parameter/return/property type hints, `instanceof`, `catch`, static access (`X::`), and class-name arguments inside attributes.

**Pipeline:**

- *Keyword-syntax files:* run the same tokenizer extraction pass as the Cleaner to produce (a) an in-memory cleaned source and (b) a manifest mapping each CITE declaration's position to its extracted visibility. Parse the cleaned source with `nikic/php-parser`. Reattach the extracted visibility to the matching class-like AST node.
- *Attribute-syntax files:* parse directly with `nikic/php-parser`; read visibility off the CITE's `AttributeGroup` / `Attribute` nodes.
- Both paths converge on one shared AST-based resolution core: resolve every name reference to a fully-qualified name (handling `use` imports, aliases, and relative names — this is why an AST-based resolver is used instead of raw tokens, which cannot reliably disambiguate these cases), determine the referencing node's own namespace, and check it against the target CITE's declared namespace and visibility using the containment rule from [Visibility semantics](#visibility-semantics).
- A referenced CITE with no declaration found in the analyzed source set (e.g. a third-party vendor class) is treated as public / skipped — the checker only enforces visibility for CITEs it can see declared.

**Output:** a list of violations, each with the declaring CITE's fully-qualified name, its declared visibility and namespace, and the violating usage's file, line, and usage kind (e.g. `extends`, `new`, `instanceof`).

**CLI shape:**

```
bin/check src/
```

Exits non-zero when violations are found, for CI use.

## Out of scope (v1)

- CITEs declared in the global namespace (no `namespace` statement) — always public.
- Anonymous classes.
- **Visibility monotonicity** — a rule requiring a CITE's own visibility to be no wider than any CITE it extends/implements/uses as a trait. Explicitly rejected for v1: a `public` class may extend a `protected` or `private` one, matching how most OOP languages (Java, C#) treat this case — the reference itself being legal is the only requirement.
- Composer-package/vendor-boundary-aware visibility (this system is purely namespace-tree based, not tied to physical package boundaries).
- IDE integration.

## Reserved for future versions (RFU)

- Visibility-monotonicity enforcement as an optional, opt-in stricter mode.
- Plugin for real-time, in-editor checking instead of a separate CLI step.
- Composer-package-boundary-aware visibility rules.

## Quality

### PHPUnit

Cleaner and Checker each need unit coverage of tokenizer/parser edge cases, notably:

- Modifier combined with `abstract` / `final` / `readonly`, in either order.
- Comment and whitespace preservation across a cleaned file.
- Multiple CITEs per file, with mixed visibilities.
- CITEs declared inside a `namespace { ... }` block form vs. the single-statement `namespace X;` form.
- Files mixing keyword-syntax and attribute-syntax declarations.
- All usage forms (`new`, `extends`, `implements`, trait `use`, type hints, `instanceof`, `catch`, static access) for each visibility level, including the ancestor/descendant/sibling namespace cases from [Visibility semantics](#visibility-semantics).

