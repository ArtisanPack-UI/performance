# Implement script loading strategies

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Different scripts need different loading strategies (defer, async, module) based on their purpose.

## Proposed Solution

Create strategy classes for each loading approach.

## Acceptance Criteria

- [ ] Create `src/JavaScript/DeferStrategy.php`
- [ ] Create `src/JavaScript/AsyncStrategy.php`
- [ ] Create `src/JavaScript/ModuleStrategy.php`
- [ ] Create `src/JavaScript/InlineStrategy.php`
- [ ] Each strategy generates appropriate HTML
- [ ] Configurable default strategy via config
- [ ] Unit tests for each strategy

## Use Cases

1. Defer: Load after HTML parsing, maintain order
2. Async: Load independently, execute when ready
3. Module: ES modules with automatic defer
4. Inline: Embed script content directly

## Additional Context

```php
// Defer - loads after HTML, maintains order
<script src="/js/app.js" defer></script>

// Async - loads independently
<script src="/js/widget.js" async></script>

// Module - ES modules
<script type="module" src="/js/app.mjs"></script>

// Inline - embedded content
<script>/* inlined content */</script>
```

**When to use:**
- `defer`: Most scripts, maintains execution order
- `async`: Independent scripts (analytics, widgets)
- `module`: Modern ES module scripts
- `inline`: Critical, small scripts

---

**Related Issues:**
- #018 (ScriptManager)
