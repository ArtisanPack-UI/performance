---
title: Development
---

# Development

Guides for contributors and anyone working on the package itself. For end-user documentation, see the [[guides]] and [[api]] sections.

## Reference Pages

- [[development/code-style]] — The `artisanpack-ui/performance` code standard, the tools that enforce it (PHP-CS-Fixer + PHP_CodeSniffer), and the composer scripts that ship with the package

## Related Documents at Package Root

- [CONTRIBUTING.md](../CONTRIBUTING.md) — How to open issues, propose changes, and file pull requests
- [CHANGELOG.md](../CHANGELOG.md) — Release history

## Conventions

- **Testing:** `composer test` runs the full Pest suite; `composer bench` runs the opt-in benchmark suite (see [[benchmarks/server-side]]).
- **Linting:** `composer lint` runs PHP-CS-Fixer + PHPCS in report mode; `composer fix` auto-fixes.
- **Docs are code:** any user-facing change lands with its own doc update. The `docs/` tree mirrors the surface area of the package — services get `docs/api/services.md`, features get a `docs/guides/…` walk-through.
