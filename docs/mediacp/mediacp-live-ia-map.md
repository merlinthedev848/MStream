# MediaCP live IA map (authoritative UX target)

**Host:** https://stream.chriskendall.media/ (HTTPS; do not use `:2020` — nginx proxies)  
**Captured:** 2026-11-16 · Europe/London  
**Login:** admin (`ADMINISTRATOR`) — OK  
**Screenshots:** [`docs/mediacp/live-station/`](live-station/README.md)  
**Compared to:** MStream current-state screens (project store) and menus in `frontend/components/{Admin,Stations}/menu.ts`

No application code in this pass. Goal: replicate **MediaCP-simple setup** next.

---

## 1. Verdict

MediaCP’s radio happy path is a **wizard-provisioned service** with auto ports/passwords, then a **flat service console** where on-air is essentially: **upload media → playlist → Start AutoDJ → copy listener URL**. Admin fleet work (customers, packages/plans, ports, suspend/delete, host metrics) stays outside that operator loop.

MStream already mirrors much of the **label order** of the service console, but still *feels* more complex: denser nested menus, multi-card Overview, weaker “one Start + obvious URL” focus, and admin home that is menu-card heavy rather than KPI/fleet-first.

---

## 2. Admin vs User / Customer matrix

| Surface / control | Admin (hoster) | Station operator (customer) | Notes from live admin session |
| --- | --- | --- | --- |
| Dashboard KPIs (online/offline, customers, resellers, CPU/mem/disk, connections chart) | Yes | No | Admin home |
| Customers CRUD + **Login** (impersonate) | Yes | No | Only `Administrator` present on this host |
| Reseller Plans | Yes | No | Administration submenu |
| Create Service wizard (customer → type → quotas/ports) | Yes | Typically no (admin assigns) | Auto Port Base, auto passwords |
| Global Statistics / Software Health / Updates / System Config / Email Templates / Announcements / API Key | Yes | No | Administration + top nav |
| Service Overview (Start AutoDJ, quotas, map, charts) | Yes | Yes | Core operator home |
| Configure (Publish Name, passwords…) | Yes (full) | Partial expected | Live Configure shows **Port Base**, Owner, Limits, Features — **ports/quotas are admin-shaped**; customer view not verified |
| Media / Playlists / Jingles / Schedule | Yes | Yes | Operator content path |
| Widgets & Links / Public Page | Yes | Yes | Listener URL + embeds live here |
| Mount Points / DJ Manager / Reporting / Logs | Yes | Yes | Ops |
| Update Song Title | Yes | Yes | Prompt, not a page |
| **Suspend Service** / **Delete Service** | Yes (orange/red in service nav) | No (admin-only intent) | Visible while logged in as admin on service console |
| Host metrics / billing packages | Admin | No | Reseller Plans present; no separate “billing invoices” nav seen |

**Customer login still needed:** this install has **no non-admin customer user**. Exact ask: create (or share) a **customer-level username/password** with one assigned Icecast service so we can screenshot the pure operator shell (confirm Configure hides Port Base / Suspend / Delete / Owner).

---

## 3. Navigation IA

### 3.1 Admin global nav (exact order)

1. **Dashboard**
2. **Customers**
3. **Services**
4. **Statistics**
5. **API Key**
6. **Administration** ▾
   1. System Config  
   2. Reseller Plans  
   3. Email Templates  
   4. Announcements  
   5. Software Health  
   6. Software Updates  

Icon rail mirrors the same top-level items.

### 3.2 Service console nav (exact order) — `DemoRadio`

1. Overview  
2. Configure  
3. Media  
4. Playlists  
5. Jingles  
6. Schedule  
7. Widgets & Links  
8. Public Page  
9. Mount Points  
10. DJ Manager  
11. Update Song Title  
12. Log Manager  
13. Reporting  
14. **Suspend Service** (admin)  
15. **Delete Service** (admin)  

Breadcrumb pattern: `Media Services → {owner} → {Publish Name} → {page}`.

Clock + timezone under service name (`Europe/London`).

### 3.3 MStream current nav (contrast)

**Admin** (`Admin/menu.ts`): System · People & Access · Stations (Media Services, Resellers, Customers, Hosting Packages, …) · Broadcast Software — deeper grouping, less “Dashboard KPI first.”

**Station** (`Stations/menu.ts`): Overview → Configure → Media (many children) → Playlists → Jingles → Commercials → Schedule → Public Page (children) → Podcasts → Mount Points (children) → DJ Manager → Alerts & Integrations → Logs → Reporting — same spine as MediaCP, but **more nesting + extras** (Commercials, Podcasts, Alerts, Advanced AutoDJ Config under mounts for admins).

---

## 4. Happy path to on-air (live)

### 4.1 Admin provisions service (~8 clicks)

1. Services  
2. **+ CREATE A NEW SERVER**  
3. Wizard step 1 — Customer (admin) → CONTINUE  
4. Step 2 — pick **ICECAST 2** card → CONTINUE  
5. Step 3 — set **Publish Name** only (Port Base = auto; passwords generated; bitrate/connections/disk/transfer sliders; AutoDJ = liquidsoap) → CONTINUE  
6. Relay / IP binding (defaults) → **CREATE SERVICE**  
7. Lands on service Overview (`/controller/MediaService/{id}`)

**Friction MediaCP avoids here:** no manual port picking; strong passwords generated; visual type picker; quotas as sliders; Liquidsoap AutoDJ defaulted.

### 4.2 Operator goes live (~12–15 clicks total)

Observed empty DemoRadio state after create:

8. **Media** → **+ ADD MEDIA** (or drag-drop; FTP details available)  
9. **Playlists** → **+ CREATE PLAYLIST** → add tracks / enable general rotation  
10. **Overview** → **Start AutoDJ** (primary CTA; also “HOW TO CONNECT?” for encoder)  
11. Copy listener URL:
    - Overview / How to Connect: mount `/stream`, port **8000**  
    - Widgets & Links: `…:8000/stream`, public page `/public/demoradio`  
    - Mount Points: play icon on `/stream` (DEFAULT, AutoDJ ENABLED)

**On Overview when Service Online:** status pill `SOURCE: NOT CONNECTED` until AutoDJ/encoder connects; quotas: Listeners `0/100`, Bandwidth, Storage; Recently Played; geo map; 12h listeners chart.

**Not required for first on-air:** Configure tabs, Mount manage, DJ Manager, Reporting, Public Page branding.

### 4.3 Click / friction contrast vs MStream current state

| Step | MediaCP | MStream (from current-state + menus) |
| --- | --- | --- |
| Create | 3-step wizard, auto port/password | Admin station form / billing provision; more fields historically |
| Empty station | Overview shows one **Start AutoDJ** + How to Connect | Multi-card Overview (Get On The Air, Quotas, On the Air, Public Pages, Requests, Streamers, Icecast, AutoDJ) — helpful but noisier |
| Media | Single **Media Tracks** list (+ folders) | Media submenu: Music Library, Duplicates, Needs Attention, Not in Playlist, On-Demand, SFTP, Import/Export |
| Playlist | Flat Playlists page + Create | Playlists OK; plus Jingles/Commercials split |
| Start | One **Start AutoDJ** | Start Station / Start Broadcasting + separate service status cards |
| Listener URL | Widgets & Links + How to Connect + mount play icon | Listener Streams / Public Pages / Show Credentials — more places, less “copy this URL” primacy |

---

## 5. Key page anatomy (live)

### Overview
- Service Online toggle + **Start AutoDJ** / encoder path  
- Quotas: Listeners, Bandwidth, Storage  
- Recently Played, listeners chart, geo map  
- Screens: `service-overview.webp`

### Configure (tabs)
GENERAL · LIMITS · FEATURES · ICECAST 2 · AUTODJ · RELAY · ADVANCED  
Fields include Owner, Publish Name, **Port Base**, stream passwords, mount/AutoDJ counts (creation-time), crossfade, relay, burst, expiry.  
Screens: `service-configure.webp` (secrets redacted)

### Media
Media Tracks; storage bar; **+ CREATE FOLDER** / **+ ADD MEDIA**; search; default folders `intro`, `ondemand`; drag-drop + FTP.

### Playlists / Jingles / Schedule
Playlists = AutoDJ rotation table (Title, Items, Duration, General Rotation, Playback).  
Jingles = interval-oriented playlist UI.  
Schedule = Add Event calendar/list.

### Widgets & Links / Public Page / Mounts / DJ / Logs / Reporting
- Widgets: public page URL, service URL, HTML5 embed, mount links  
- Public Page: branding, colors, autoplay, album covers  
- Mounts: `/stream` DEFAULT, AutoDJ ENABLED (e.g. 128 AAC), Manage  
- DJ Manager: Add DJ + encoder connect (separate DJ port shown live as **6800**)  
- Logs: access / error / playlist  
- Reporting: Summary KPIs + SoundExchange-class exports  

---

## 6. Prioritized MStream gaps — “MediaCP-simple setup”

Priority for **setup simplicity** (not full feature parity):

| P | Gap | Why it makes MStream feel complex | MediaCP pattern to copy |
| --- | --- | --- | --- |
| **P0** | **One primary Start + one primary Listener URL** on Overview | MStream Overview stacks many cards/CTAs (Start Station, Start Broadcasting, Listener Streams, Show Credentials) | Overview: Start AutoDJ + How to Connect + quota tiles |
| **P0** | **Provision wizard with auto Port Base + passwords** | Creating/editing stations still feels form-heavy vs 3-step MediaCP wizard | Customer → Type card → sliders/defaults → Create |
| **P0** | **Strict admin vs operator Configure** | Live MediaCP still shows Port Base to admin; MStream must ensure operators never see ports/suspend/delete (preferences) | Hide Port Base / Owner / Suspend / Delete from customer shell |
| **P1** | **Flatten Media IA** | Media submenu (7+ leaves) vs one Media Tracks surface | Single library + folders; bury SFTP/import under advanced |
| **P1** | **Admin Dashboard = fleet KPIs first** | MStream admin leads with menu blocks; MediaCP leads with Online/Offline/Customers + connections chart + Getting Started | Reorder Admin Index to MediaCP KPI composition |
| **P1** | **Getting Started checklist for hosters** | MediaCP Dashboard checklist (branding, email, create customer/service) | Same pattern on MStream admin empty state |
| **P1** | **Widgets & Links as URL hub** | Listener/embed URLs scattered | Dedicated Widgets & Links (or Overview strip) with copyable stream + public + embed |
| **P2** | **Default playlist on create** | DemoRadio playlists started **empty** (MediaCP); MStream AutoDJ-ready default is actually *better* — keep it, but make Overview say “upload → already in default playlist → Start” | Keep MStream default playlist; copy MediaCP wording simplicity |
| **P2** | **Mount Points density** | MStream Mounts nest HLS/remotes/fallback/queue/restart | MediaCP: one Mount Points table + Manage; move advanced under Configure/Limits |
| **P2** | **Reporting export clarity** | MediaCP names SoundExchange / Track Timeline exports in plain language | Match export labels + Summary-first tabs |
| **P2** | **Customer Login-as verification** | Cannot prove customer shell until a customer account exists | Need customer credentials from Chris |

---

## 7. What we could / could not see

| Seen as admin | Not seen (blocked) |
| --- | --- |
| Full admin nav + Administration children | Pure customer left-nav (no customer user) |
| Create Icecast service wizard end-to-end | Reseller-owned customer journey |
| Full DemoRadio console pages listed above | Production third-party station (none assigned) |
| Start AutoDJ affordance (not left running as a soak test) | Billing invoice/payment UI (not in nav) |

**Caveat:** Panel `app-url` meta is `http://…` while users open `https://…`. Absolute HTTP links caused mixed-content flakiness in the browser; screenshots used HTTPS controller routes. Recommend Chris set **MediaCP Full URL** to `https://stream.chriskendall.media/` (config fix, not explored further here).

---

## 8. Replicate next (suggested order)

1. Overview simplification — Start AutoDJ analogue + listener URL + quotas (drop card stack noise).  
2. Admin create-station wizard parity (type → auto ports → quotas).  
3. Customer shell audit with a real customer login (Configure field gate + Suspend/Delete absence).  
4. Flatten Media + Mounts menus to MediaCP depth.  
5. Admin Dashboard KPI composition.

---

## 9. Screenshot index

See [`live-station/README.md`](live-station/README.md) (29 surfaces).
