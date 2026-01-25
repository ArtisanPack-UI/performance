# Implement ScriptManager service

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Managing JavaScript loading strategies across an application is complex. A central service is needed.

## Proposed Solution

Create `ScriptManager` service for registering and managing script loading.

## Acceptance Criteria

- [ ] Create `src/JavaScript/ScriptManager.php`
- [ ] Register scripts with names and sources
- [ ] Support loading strategies: defer, async, module, inline
- [ ] Priority ordering for scripts
- [ ] Conditional loading configuration
- [ ] Get all registered scripts
- [ ] Render scripts as HTML
- [ ] Facade method: `Performance::script($src)`
- [ ] Unit tests for script management

## Use Cases

1. Developer registers analytics script with defer
2. Developer registers critical script with high priority
3. Package renders all scripts in proper order

## Additional Context

```php
use ArtisanPackUI\Performance\Facades\Performance;

// Register scripts with strategies
Performance::script('/js/app.js')->defer();
Performance::script('/js/analytics.js')->async();
Performance::script('/js/polyfill.js')->inline();

// Conditional loading
Performance::script('/js/editor.js')
    ->loadOn('interaction')
    ->target('#editor');

// Priority ordering
Performance::script('/js/critical.js')->priority(1);
Performance::script('/js/non-critical.js')->priority(10);

// Get scripts for rendering
$scripts = Performance::getScripts();
```

---

**Related Issues:**
- #003 (Performance Facade)
