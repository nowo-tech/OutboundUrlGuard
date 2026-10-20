# Code inventory — 001-baseline

**Last audited:** 2026-10-20  
**Package:** `nowo-tech/outbound-url-guard-bundle`  
**Production units:** 14 (every file tracked under `src/`)  
**Mapped:** 14  
**Unmapped:** 0

Audit: `git ls-files src | wc -l` must equal the mapped count below. Files added by [002](../002-runtime-allow-private/) are listed here too (the baseline inventory is canonical).

## Bundle

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/NowoOutboundUrlGuardBundle.php` | Bundle | FR-BUNDLE-001 |

## Dependency injection

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/DependencyInjection/Configuration.php` | Dependency injection | FR-DI-002 |
| `src/DependencyInjection/NowoOutboundUrlGuardExtension.php` | Dependency injection | FR-DI-001 |
| `src/Resources/config/services.yaml` | Dependency injection | FR-DI-001 |

## Decision

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/Guard/OutboundUrlResult.php` | Decision | FR-DECISION-001 |
| `src/Guard/OutboundUrlDecision.php` | Decision | FR-DECISION-002 |
| `src/Exception/UnsafeOutboundUrlException.php` | Decision | FR-DECISION-003 |

## Guard policy

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/Guard/OutboundUrlGuard.php` | Guard policy | FR-GUARD-001, FR-GUARD-002, FR-GUARD-003, FR-GUARD-004, FR-GUARD-005, FR-GUARD-006, FR-WORKER-001, FR-WORKER-002 |
| `src/Guard/AllowPrivateResolverInterface.php` | Guard policy (002) | FR-RES-001 |
| `src/Guard/ConfiguredAllowPrivateResolver.php` | Guard policy (002) | FR-RES-001, FR-RES-002 |
| `src/Guard/OutboundUrlGuardFactory.php` | Guard policy (002) | FR-RES-003 |
| `src/Guard/RuntimeOutboundUrlGuard.php` | Guard policy (002) | FR-RES-004 |

## DNS and network

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `src/Dns/HostnameDnsLookup.php` | DNS and network | FR-DNS-001, FR-WORKER-001, FR-WORKER-002, FR-WORKER-003 |
| `src/Network/PrivateNetworkTarget.php` | DNS and network | FR-NET-001, FR-WORKER-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle | 1 | 1 |
| Dependency injection | 3 | 3 |
| Decision | 3 | 3 |
| Guard policy | 5 | 5 |
| DNS and network | 2 | 2 |
| **Total production files (`git ls-files src`)** | **14** | **14** |
