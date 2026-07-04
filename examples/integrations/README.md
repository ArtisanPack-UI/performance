# Integrations

Snippets for integrating with the ArtisanPack UI media library, adding
the package to an existing Laravel app, and running in a multi-tenant
setup.

## Examples

- [`media-library.php`](media-library.php) — auto-optimize every upload that flows through `artisanpack-ui/media-library`
- [`existing-app.md`](existing-app.md) — a phased rollout plan for adding the package to a live app without breaking cache-invalidation or SEO
- [`multi-tenant.php`](multi-tenant.php) — per-tenant config overrides, cache prefixing, and dashboard gating
