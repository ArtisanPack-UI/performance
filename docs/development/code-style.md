# Code Style

The `artisanpack-ui/performance` package follows the ArtisanPack UI coding
standard, which combines PHP-CS-Fixer for auto-fixable formatting with
PHP_CodeSniffer for rules that cannot be safely auto-corrected.

## Tooling

Two configuration files live at the package root:

| File | Purpose |
| ---- | ------- |
| `.php-cs-fixer.dist.php` | PHP-CS-Fixer rules (spacing, alignment, ordering, quoting, etc.) |
| `phpcs.xml` | PHP_CodeSniffer ruleset that pins us to the `ArtisanPackUIStandard` sniffs we care about |

The custom fixers `SpacesInsideParenthesisFixer` and
`SpacesInsideBracketsFixer` from
[`artisanpack-ui/code-style-pint`](https://github.com/ArtisanPack-UI/code-style-pint)
enforce the WordPress-style spacing convention used across the ecosystem
(spaces inside `(` `)` and `[` `]`).

## Running Checks

```bash
# Report style violations without changing anything
composer lint

# Auto-fix everything PHP-CS-Fixer can handle
composer fix

# PHPCS only
composer cs

# PHPCBF (auto-fix what PHPCS itself can fix — rare in practice)
composer cs:fix
```

`composer lint` runs both tools sequentially and exits non-zero if either
tool reports issues. It is the exact command the CI pipeline runs.

## What the Standard Enforces

The rules mirror the wider ArtisanPack UI ecosystem. The most visible ones:

- Real tabs for indentation
- Spaces inside parentheses and brackets: `if ( $condition ) { $array[ 0 ] }`
- Aligned `=` and `=>` operators for consecutive assignments
- Yoda conditions: `if ( true === $condition )`
- Single quotes for strings without interpolation
- Short array syntax `[]`
- Trailing commas in multi-line arrays, arguments, and parameters
- Ordered class elements (traits → constants → properties → constructor → methods)
- Ordered imports (class, function, const), alphabetical
- No `die()`, `exit()`, `var_dump()`, or `print_r()` in package code

Refer to the fixer config for the full list.

## Continuous Integration

The `.github/workflows/ci.yml` pipeline runs a dedicated **Code Style** job
that executes both tools on every push to `main`, every push to a
`release/*` branch, and every pull request. A failing lint step blocks the
build.

## Pre-commit Hook (optional but recommended)

You can catch style issues before they hit CI by installing a
`pre-commit` git hook. Save the snippet below to
`.git/hooks/pre-commit` and make it executable
(`chmod +x .git/hooks/pre-commit`):

```bash
#!/usr/bin/env bash

set -e

# Only run against staged PHP files under src/ or tests/.
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- 'src/*.php' 'tests/*.php')

if [ -z "$STAGED_FILES" ]; then
    exit 0
fi

echo "Running PHP-CS-Fixer on staged files..."
./vendor/bin/php-cs-fixer fix --dry-run --diff --path-mode=intersection -- $STAGED_FILES

echo "Running PHP_CodeSniffer..."
./vendor/bin/phpcs -- $STAGED_FILES

echo "Code style OK."
```

The `--dry-run` flag ensures the hook only reports problems; run
`composer fix` yourself to auto-correct them. If you prefer the hook to
auto-fix and re-stage, drop `--dry-run` from the PHP-CS-Fixer command and
add `git add $STAGED_FILES` at the end.

Teams that want the hook installed automatically for every clone can use a
solution like [pre-commit.com](https://pre-commit.com/) or a
`composer.json` `post-install-cmd` script that symlinks the file into
`.git/hooks/`. This package intentionally does not do that, so contributors
remain free to choose their own workflow.

## When Sniffs Disagree

If a PHP-CS-Fixer rule and a PHPCS sniff give conflicting feedback,
PHP-CS-Fixer wins — the phpcs config in this repository explicitly disables
formatting sniffs that PHP-CS-Fixer already covers. See the comments at the
top of `phpcs.xml` for the current list and the reasoning behind each
exclusion.
