# Code inventory — 001-baseline

**Last audited:** 2026-09-24  
**Package:** `nowo-tech/outbound-url-guard-bundle`  
**Production units:** 10  
**Mapped:** 10  
**Unmapped:** 0

Audit: `find src -type f | sort` must match the rows below.

## Bundle

| File | Requirement |
| --- | --- |
| `src/NowoOutboundUrlGuardBundle.php` | FR-BUNDLE-001 |

## Dependency injection

| File | Requirement |
| --- | --- |
| `src/DependencyInjection/Configuration.php` | FR-DI-002 |
| `src/DependencyInjection/NowoOutboundUrlGuardExtension.php` | FR-DI-001 |
| `src/Resources/config/services.yaml` | FR-DI-001 |

## Decision

| File | Requirement |
| --- | --- |
| `src/Guard/OutboundUrlResult.php` | FR-DECISION-001 |
| `src/Guard/OutboundUrlDecision.php` | FR-DECISION-002 |
| `src/Exception/UnsafeOutboundUrlException.php` | FR-DECISION-003 |

## Guard policy

| File | Requirement |
| --- | --- |
| `src/Guard/OutboundUrlGuard.php` | FR-GUARD-001, FR-GUARD-002, FR-GUARD-003, FR-GUARD-004, FR-GUARD-005, FR-GUARD-006, FR-WORKER-001, FR-WORKER-002 |

## DNS and network

| File | Requirement |
| --- | --- |
| `src/Dns/HostnameDnsLookup.php` | FR-DNS-001, FR-WORKER-001, FR-WORKER-002, FR-WORKER-003 |
| `src/Network/PrivateNetworkTarget.php` | FR-NET-001, FR-WORKER-002 |

## Coverage summary

| Category | Files |
| --- | --- |
| Bundle | 1 |
| Dependency injection | 3 |
| Decision | 3 |
| Guard policy | 1 |
| DNS and network | 2 |
| **Total** | **10** |
