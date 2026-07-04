---
title: Security
---

# Security

Security policy and per-release audit reports for the `artisanpack-ui/performance` package.

## Reference Pages

- [[security/audit-1.0.0]] — Pre-release security audit for v1.0.0 (input validation, output escaping, authorization, file security, cache security, configuration security, code hygiene)

## Reporting a Vulnerability

If you discover a security issue, please email **me@jacobmartella.com** directly rather than opening a public GitHub issue. You will receive an acknowledgement within two business days and a fix or mitigation plan in the following release.

## Audit Cadence

Every major and minor release goes through a manual security review covering:

- Input validation at every ingress (HTTP endpoints, Blade directive arguments, Livewire actions)
- Output escaping in all Blade templates (both `{{ }}` and `{!! !!}` cases)
- Authorization gates on admin surfaces (the performance dashboard is gate-protected out of the box)
- File-path handling in image and cache pipelines
- Cache key composition (no user-controlled cache keys without normalization)
- Configuration surface — every option that touches an executable path or query is documented in the config file itself

Audit results are recorded per release under `security/`.

## Known Advisories

None as of v1.0.0.
