# MediaCP live station reference screenshots

**Source:** https://stream.chriskendall.media/ (HTTPS; panel proxies — do not require `:2020`)  
**Captured:** 2026-11-16 (Europe/London)  
**Login persona:** admin (`ADMINISTRATOR`)  
**Demo service:** `DemoRadio` (Icecast 2 + Liquidsoap AutoDJ), service id `1`  
**IA map:** [`../mediacp-live-ia-map.md`](../mediacp-live-ia-map.md)

Credentials visible in live UI (stream/admin passwords) were redacted in `service-configure.webp`, `admin-create-service-step3-config.webp`, and `service-widgets-links.webp`. Login secrets are never stored here.

## Admin / fleet

| File | Surface |
| --- | --- |
| `admin-dashboard.webp` | Superadmin Dashboard — online/offline, customers/resellers, Getting Started, CPU/mem/disk, connections chart |
| `admin-services-empty.webp` | Media Services list (pre-demo) |
| `admin-services-with-demo.webp` | Media Services list with DemoRadio |
| `admin-customers.webp` | Customers table (Overview / Manage / Login-as) |
| `admin-statistics.webp` | Global Statistics |
| `admin-api-key.webp` | API Key (disabled until configured) |
| `admin-system-config.webp` | System Config → GENERAL |
| `admin-system-config-services.webp` | System Config → SERVICES |
| `admin-reseller-plans.webp` | Reseller Plans |
| `admin-email-templates.webp` | Email Templates |
| `admin-announcements.webp` | Announcements |
| `admin-software-health.webp` | Software Health checks |
| `admin-software-updates.webp` | Software Updates |
| `admin-create-service-step1-customer.webp` | Create Service wizard — pick customer |
| `admin-create-service-step2-types.webp` | Create Service wizard — service type cards |
| `admin-create-service-step3-config.webp` | Create Service wizard — quotas/ports/AutoDJ (secrets redacted) |

## Station / service console (`DemoRadio`)

| File | Surface |
| --- | --- |
| `service-console-menu.webp` | Service left-nav (all operator + admin actions) |
| `service-overview.webp` | Overview — Service Online toggle, Start AutoDJ, quotas, map, listeners chart |
| `service-configure.webp` | Configure tabs: GENERAL / LIMITS / FEATURES / ICECAST 2 / AUTODJ / RELAY / ADVANCED |
| `service-media.webp` | Media Tracks — folders, + ADD MEDIA, drag-drop / FTP |
| `service-playlists.webp` | Playlists / AutoDJ rotation list |
| `service-jingles.webp` | Jingles |
| `service-schedule.webp` | Schedule / events |
| `service-widgets-links.webp` | Widgets & Links — public page, service URL, embeds |
| `service-public-page.webp` | Public Page / station branding config |
| `service-mount-points.webp` | Mount Points (`/stream` default, AutoDJ enabled) |
| `service-dj-manager.webp` | DJ Manager + encoder connect hints |
| `service-log-manager.webp` | Log Manager |
| `service-reporting.webp` | Historical Reporting (Summary + exports) |

## Not a separate page

- **Update Song Title** — prompt from Overview nav (`BTNUpdateTitle`), not a full page.
- **Suspend / Delete** — admin actions in service nav; not activated (read-only policy).

## Gaps / caveats

- Only the **Administrator** account exists on this host — no pure customer login was available to screenshot a customer-scoped shell.
- Panel `app-url` is still HTTP while access is HTTPS; some XHR/links are absolute `http://…`. Screenshots here used HTTPS routes + insecure-content allowance.
- Demo service created during crawl for console coverage; not used for Suspend/Delete/billing mutations.
