# Skola Backend (frame)

Laravel 13 API. See `../PLANNING.md` for the product/architecture decisions.

## What exists (v1 frame)

- **Multi-tenancy** — `App\Models\Concerns\BelongsToTenant`: global scope + auto-stamp of `school_id`. Super admin (`school_id = null`) bypasses. Enforced at the model layer (tested in `tests/Feature/TenantScopingTest.php`).
- **Roles** — `App\Enums\Role` (super_admin, school_admin, teacher, parent, student). Route guard: `role:` middleware (`App\Http\Middleware\EnsureRole`).
- **Auth** — Sanctum token auth. `POST /api/login`, `/api/logout`, `GET /api/me`.
- **Schema** — schools, users(+tenant cols), teachers, school_classes, students, student_guardian, face_embeddings, consents, attendances, audit_logs.
- **Enrolment** (`§4.6`) — `POST /api/enrolment/consent`, `POST /api/enrolment/face`. Consent is gated before an embedding is stored; re-enrolment deactivates prior embeddings.
- **Attendance** — `POST /api/attendance/bypass` (Tier-1 gate bypass, audited). `POST /api/attendance/check-in` is a **stub (501)** — face matching is the next feature.

## Stubbed / deferred

- **Face matching** — `AttendanceController@checkIn` returns 501. Needs vector match + threshold; use **pgvector** in production. `face_embeddings.embedding` is JSON for now (portable); switch to a `vector` column on Postgres.
- **Payment gateway** — `App\Contracts\PaymentGateway` bound to `LogPaymentGateway`. Swap for Midtrans/Xendit in v2. Webhook must verify signature + be idempotent.
- **WhatsApp** — `App\Contracts\WhatsAppGateway` bound to `LogWhatsAppGateway`. Swap for Cloud API / Fonnte / Wablas in v2.
- **Finance tables** (bills/student_bills/payments) and notifications — deferred to v2 with the gateway choices.

## Local dev

Uses SQLite (`database/database.sqlite`) out of the box. Production targets PostgreSQL + pgvector.

```bash
php artisan migrate
php artisan test
php artisan serve
```
