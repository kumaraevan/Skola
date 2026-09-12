# Skola

Multi-tenant school information system — **face-recognition attendance** and **SPP (tuition) management** — built as a SaaS where one deployment serves many schools, each isolated from the others.

This repository contains the **v1 backend API** (Laravel) and the **admin web app** (React). The gate kiosk and parent apps are separate native targets (see Roadmap).

> Full product/architecture rationale: [PLANNING.md](PLANNING.md) · VPS deployment runbook: [VPS-DEPLOYMENT.md](VPS-DEPLOYMENT.md)

## What works today (v1)

- **Multi-tenancy** — every record scoped to a school; enforced at the model layer.
- **Roles** — super admin (platform), school admin, teacher, plus parent/student accounts.
- **Auth** — Sanctum token auth.
- **Attendance**
  - **Face check-in (1:N)** — the kiosk sends an on-device embedding; the server matches it (cosine + configurable threshold) and records attendance.
  - **Roster by date** — present / absent / bypass, with counts.
  - **Audited gate bypass** — gate staff can mark a failed scan present, attributably.
- **Face enrolment** — biometric-consent gating + embedding storage (re-enrolment deactivates old embeddings). *No raw photos are stored — embeddings only.*
- **Students / Teachers / Classes** — full CRUD, cross-linked (homeroom teacher, class rosters).
- **Dashboard** — live KPI counts (present/absent today, pending enrolment, totals).

59 backend tests, all green.

## Tech stack

| Layer | Choice |
|-------|--------|
| Backend | Laravel 13, PHP 8.4 |
| Database | SQLite (dev) → PostgreSQL + pgvector (prod) |
| Auth | Laravel Sanctum (tokens) |
| Admin web | Vite + React + TypeScript + Tailwind v4 |
| Kiosk (planned) | Flutter / React Native + Google ML Kit + MobileFaceNet (TFLite) |

## Repository layout

```
backend/            Laravel API (models, controllers, migrations, tests)
frontend-admin/     Vite + React admin SPA
design-system/      Design tokens & guidance (skola-admin)
PLANNING.md         Product & architecture decisions
VPS-DEPLOYMENT.md   Production deployment runbook
```

## Getting started

Prerequisites: **PHP 8.4**, **Composer**, **Node 22**.

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve                # http://127.0.0.1:8000
```

Uses SQLite out of the box (`database/database.sqlite` is created automatically). For production, point `.env` at PostgreSQL + pgvector — see [VPS-DEPLOYMENT.md](VPS-DEPLOYMENT.md).

### Admin web

```bash
cd frontend-admin
npm install
npm run dev                      # http://localhost:5173
```

The dev server proxies `/api` to `http://127.0.0.1:8000`, so run the backend alongside it.

### Seeded logins

All use password `password`:

| Email | Role |
|-------|------|
| `super@skola.test` | Super admin |
| `admin@skola.test` | School admin |
| `guru@skola.test` | Teacher |

## Testing

```bash
cd backend
php artisan test
```

## Configuration notes

- **Face-match threshold** — `FACE_MATCH_THRESHOLD` (default `0.6`). Cosine-similarity cutoff for a scan to count as a match; tune against the real model and gate hardware.
- **Liveness** is a client (kiosk) responsibility — the server trusts that the tablet passed its blink/turn challenge before sending an embedding.

## Roadmap

- **v2** — SPP billing + payment gateway (Midtrans / Xendit) with signed, idempotent webhooks; WhatsApp gateway (official Cloud API or Fonnte/Wablas); digital receipts.
- **v3** — lateness enforcement, teacher payroll report, treasurer ledger, Excel reports.
- **Native apps** — gate kiosk (camera + ML Kit + MobileFaceNet) and parent/student app.
- **Production DB** — migrate face embeddings from JSON to a pgvector `vector` column + ANN index.
