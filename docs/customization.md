---
title: Customization
---

# Customization

Every user-facing view, prop, and stylesheet in the performance package is designed to be overridden or tuned in place. The pages in this section document the three levers you have — publishing templates for direct edits, tweaking component props and slots without touching templates, and layering CSS custom properties on top of the base stylesheet.

## Reference Pages

- [[customization/views]] — Publishing Blade templates, the full prop reference for every component and Livewire view, CSS custom properties, and Alpine.js data hooks

## By Concern

| If you need to… | See |
|---|---|
| Change the DOM of a component | [[customization/views]] — publish `performance-views` |
| Change component props or defaults | [[customization/views]] — prop tables per component |
| Restyle without touching templates | [[customization/views]] — CSS custom properties + `performance-css` publish tag |
| Fork the RUM collector JS | [[customization/views]] — `artisanpack-performance-js` publish tag |

## Conventions

- Published views land under `resources/views/vendor/artisanpack-ui/performance/`, and Laravel prefers the published copy at render time.
- Republishing overwrites your local edits, so keep them under source control and re-publish deliberately (or omit `--force`).
- Component prop tables list every attribute; anything not listed passes through the attribute bag onto the outer element.
