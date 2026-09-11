# Skola — Attendance & SPP · Planning Document

**Status:** Planning stage (v1 scope defined)
**Last updated:** 2026-09-10
**Product:** Multi-tenant school information system (SaaS) for student & teacher attendance and SPP (tuition) payments.

---

## 1. Product Summary

A multi-tenant SaaS. Each school is one tenant. A single deployment serves many schools; every school's data is isolated from every other school's.

Two functional pillars:
1. **Attendance** — record whether a student or teacher was present on a given day, verified by face recognition at a gate kiosk.
2. **Finance (SPP)** — issue tuition/fee bills, accept online payment, auto-reconcile, notify parents.

WhatsApp notifications and Excel reports support both pillars.

---

## 2. Users & Roles

Three tiers. The middle and bottom tiers split into sub-roles because the real actors need different access.

| Tier | Sub-role | Scope | Capabilities |
|------|----------|-------|--------------|
| **Super Admin** (us — platform operator) | — | All schools | Provision schools, manage subscriptions/billing, create school admins, system-wide configuration. |
| **Admin** | School Admin | One school | Manage students, classes, staff; enroll faces; generate bills; view all reports; perform manual attendance override. |
| **Admin** | Teacher | One school | Mark/view attendance for own classes; view student info. **No access to billing.** |
| **User** | Parent | One school | View own child's attendance; pay SPP; receive WhatsApp notifications. |
| **User** | Student | One school | Log in to the mobile app; view own attendance. (Attendance is *captured* at the kiosk, not on the phone.) |

**Notes:**
- **One school has many Admins and many Users.**
- **A Teacher is also an attendee.** A teacher both *marks/reviews* student attendance (an admin action) and *records their own* attendance at the kiosk (an attendee action). One account, two hats.
- **School Admin and Teacher are kept separate** so a teacher cannot touch billing. This is a permission distinction, not extra code.

---

## 3. Multi-Tenancy & Data Isolation *(non-negotiable)*

- Tenant = school. Every table carries a `school_id`.
- **Every query filters by `school_id`.** One school must never read another school's students, bills, attendance, or face embeddings.
- Enforce isolation at the data layer (row-level scoping / a mandatory tenant filter), not per-endpoint. A cross-tenant leak is a product-killing bug in a school SaaS.
- Super Admin is the only role that operates across tenants.

---

## 4. Attendance

### 4.1 v1 Scope

**v1 records presence only: did the student/teacher come, or not.** Status is effectively `present` / `absent` for the day. No lateness enforcement in v1.

However, **the check-in timestamp is captured on every record from day one**, so lateness/on-time can be derived later without a data migration. (See §4.5 Real Consequences.)

### 4.2 Primary method — Face recognition at a gate kiosk

Attendance is captured on a **school-controlled tablet kiosk at the gate**, not on personal phones. This is a deliberate choice: face recognition is the only non-token method that closes buddy-punching, because identity is bound to the person's body rather than to a shareable token (card, QR, login, PIN — all transferable).

**Flow:**
1. Student/teacher steps up to the gate kiosk. **1:N walk-up** — no card, no ID entry.
2. The tablet runs **Google ML Kit** to detect and align the face, and runs a **liveness challenge** (see §4.3).
3. On passing liveness, the tablet runs **MobileFaceNet (TFLite) on-device** to produce a face **embedding** (a numeric vector).
4. The tablet sends only `{school_id, embedding, timestamp}` to the VPS. **No raw photo leaves the tablet.**
5. The VPS performs a **vector search (pgvector)** against that school's enrolled embeddings, applies a match threshold, resolves the person, records attendance, and triggers a WhatsApp notice to the parent.

**Why this split:**
- The expensive step (running the CNN to make the embedding) happens on the tablet; the VPS only does cheap vector math. A 2 GB VPS is sufficient.
- Only embeddings cross the wire and get stored — never raw face images. Better privacy, smaller footprint.

**Kiosk connectivity:** the kiosk is **online-only** (it needs the VPS to match). For reliability, give each gate kiosk a **4G failover** (mifi/SIM router) so a WAN blip doesn't stop attendance. Extended outages fall back to the audited manual override (§4.4).

**Identification mode (to confirm):** default is **1:N walk-up** (no card; smoothest UX; fine at school scale <5000). Alternative is **1:1 verify** (student taps a card / picks their name first, then face verifies) — more accurate, scales indefinitely, and also kills card-sharing, at the cost of one extra step. Defaulting to 1:N.

### 4.3 Anti-spoofing — liveness is mandatory

Face recognition alone does not distinguish a real face from a photo of one. Without liveness, a friend holds up an absent student's photo and buddy-punching reopens. Therefore:

- **Active liveness challenge** using ML Kit signals — eye-open probability (blink) and head Euler angle (turn head) — verified across frames **before** the embedding is accepted. Uses ML Kit only; no extra model or dependency.
- Match **threshold** tuned to minimise false accepts; log low-confidence attempts for review.
- The **server sets the attendance timestamp**, not the tablet (anti-replay).

Passive/advanced anti-spoofing (screen-reflection, depth) is out of scope for v1; active challenge is sufficient for a semi-supervised gate.

### 4.4 Failure handling — two tiers

When a face scan fails at the gate (poor lighting, changed appearance, bad enrolment, injury, outage), two mechanisms keep the gate moving without weakening the record:

**Tier 1 — Gate-teacher bypass (on the spot).**
The gate-duty teacher (guru piket) can immediately bypass a failed scan so the student/teacher isn't stuck and the queue doesn't back up.
- The bypass is an action on the kiosk that **requires the teacher to authenticate** (PIN/login) so it is attributable.
- It records attendance as `method = bypass`, with `bypassed_by` = the teacher, plus reason + timestamp.
- This is acceptable *because the teacher is physically present and visually confirms the person* — the same human verification used in roll call. It is **not** an anonymous "skip" button.

> **Known soft spot:** the bypass reintroduces human trust — a careless or complicit teacher could bypass the wrong person, reopening buddy-punching. Mitigation: every bypass is audited and attributable, and an unusually high bypass rate per teacher is flagged for review. Do not allow un-attributed bypass.

**Tier 2 — Admin-scheduled re-enrolment (root-cause fix).**
If scans keep failing for someone, the School Admin calls them in at another time to **re-scan their face** (see §4.6). This fixes the underlying bad/stale embedding rather than relying on daily bypasses.

Extended kiosk/internet outages are handled by the same Tier-1 bypass plus, if prolonged, a School Admin marking attendance from the dashboard (also `method = bypass`, audited).

### 4.5 Real Consequences *(documented now, enforced later)*

v1 only tracks presence, but attendance data is known to feed high-stakes processes. These must be designed for even though they are not enforced in v1:

- **Students — class promotion.** Monthly attendance percentage (Hadir / Sakit / Izin / Alpa) is a requirement for `kenaikan kelas`. Because a student can be held back based on this data, attendance records must be accurate, auditable, and correctable only through the audited override.
- **Teachers — payroll.** Teacher attendance (and, in a later version, accumulated lateness) is the basis for `pemotongan atau pencairan tunjangan`. Because pay depends on it, teacher attendance records carry the same integrity and audit requirements.

**Implication:** even in v1, attendance rows are treated as consequential academic/financial records — immutable except via the audited manual override, with timestamps preserved for later lateness derivation. Face recognition + liveness is what makes these records trustworthy enough to carry those consequences.

### 4.6 Enrolment & re-enrolment lifecycle

**Account first, face second.** Every student/teacher has an account created by the School Admin *before* any face is captured. A person moves through these states:

1. **Account created** by School Admin (name, NIS/NIP, class, guardian link, etc.). Status: `pending_enrolment`. Parental biometric consent (§8) must be granted before step 2 — no consent, no enrolment.
2. **Initial face scan (enrolment).** The person scans their face (one or more captures). The tablet/app generates the embedding on-device; the system stores the **embedding** (never a raw photo) in `face_embeddings`, tagged with who enrolled them and when. Status: `enrolled`.
3. **Daily recognition at the gate** matches the live scan against the stored embedding(s) (§4.2).

**Re-enrolment.** If recognition keeps failing (appearance changed, poor initial capture, glasses/haircut, growth in young students), the School Admin schedules the person to re-scan at another time. A new embedding is added and the stale one deactivated (kept for audit, flagged inactive). This is the Tier-2 fix in §4.4.

**Leavers.** When a student/teacher leaves, their embeddings are purged per the retention policy (§8).

---

## 5. Finance — SPP / Bills

### 5.1 Flow
1. School Admin creates a master bill (e.g. "SPP October") and issues it in bulk to a batch or class.
2. The system creates a per-student bill line for each affected student.
3. Parent views the bill in the app/web and chooses a payment method (Virtual Account, QRIS, retail).
4. Payment gateway issues the VA/QRIS.
5. Parent pays via m-banking / e-wallet.
6. Gateway sends a **webhook** to the server on payment.
7. Server marks the student bill `PAID` and triggers a WhatsApp digital receipt.

### 5.2 Webhook security *(mandatory — do not ship without)*
The webhook flips a bill to PAID, so it is a money path. It must:
- **Verify the gateway signature** on every callback (Midtrans `signature_key` / Xendit callback token). Reject unsigned/invalid callbacks.
- **Be idempotent** — the same webhook can fire more than once; never double-process.
- **Confirm the amount and status against the gateway's own API** — never trust values from the request body alone.

Without these, anyone can POST a forged callback and mark bills paid for free.

---

## 6. WhatsApp Notifications

- Triggered on: successful attendance check-in (arrival notice to parent) and successful payment (digital receipt).
- The gateway (official Meta Cloud API vs. unofficial Fonnte/Wablas) is an **open decision** — see §11.
- Implement a **send queue with retry** regardless of provider, to absorb rate limits and transient failures.

---

## 7. Reports (Excel export)

- **Student report:** Name, NIS, Class, monthly recap of Sakit / Izin / Alpa / Hadir, and auto-computed attendance percentage.
- **Teacher report:** daily arrival records, total teaching days, and — once lateness is enforced — accumulated monthly lateness for payroll.
- **Treasurer ledger:** daily cash-in overview and a list of students in arrears.

---

## 8. Security & Privacy

- **Biometric data / PDP (personal-data protection) — critical.** Face embeddings of minors are sensitive personal data. Required controls:
  - **Explicit parental consent before enrolment** — a real onboarding step, not an afterthought. No consent → no enrolment.
  - **Store embeddings only, never raw face images.** Enrolment may briefly process photos to generate embeddings, then discards the photos.
  - **Retention & erasure policy** — right to delete/re-enrol; purge embeddings when a student/teacher leaves.
  - Embeddings are tenant-scoped (§3) and encrypted at rest.
- **Tenant isolation:** see §3 — the top data-layer priority.
- **Webhook integrity:** see §5.2.
- **Audit trail:** gate bypasses (with `bypassed_by`), face enrolment/re-enrolment, bill issuance, and payment status changes are logged with actor + timestamp. Per-teacher bypass rates are monitored for abuse.
- **Log capacity:** use standard PostgreSQL log rotation so internal logs cannot fill the disk.

---

## 9. Tech Stack

- **Server:** Ubuntu Server 24.04 LTS, ~2 vCPU / 2 GB RAM / 40 GB NVMe. *Fits — the CNN runs on the tablet, the VPS only does vector search.*
- **Database:** PostgreSQL + **pgvector** extension (face embedding search). Brute-force cosine is instant at school scale; add an ANN index only if it ever gets slow.
- **Gate kiosk (tablet):** Flutter or React Native + **Google ML Kit** (face detect/align + liveness signals) + **MobileFaceNet TFLite** (on-device embedding). Camera-facing, kiosk mode, online-only with 4G failover.
- **Mobile app (parents & students):** Flutter or React Native — login, view attendance, pay SPP. No attendance capture, no face SDK.
- **Web admin (School Admin / Teacher):** React.js + Tailwind CSS. Includes the face-enrolment workflow.
- **Payment gateway:** Midtrans **or** Xendit — see §11.
- **WhatsApp gateway:** official Cloud API **or** Fonnte/Wablas — see §11.

---

## 10. Data Model Sketch

Key tables (every non-super-admin table carries `school_id`):

- **schools** — id, name, timezone, subscription info.
- **users** — id, school_id (null for super admin), role, name, email/phone, password_hash.
- **classes** — id, school_id, name, grade, homeroom_teacher_id.
- **students** — id, school_id, user_id (their login), nis, name, class_id, enrolment_status (pending_enrolment/enrolled/inactive), status.
- **guardians** (join) — links parent users to students (a student may have more than one guardian).
- **teachers** — id, school_id, user_id, nip, enrolment_status.
- **face_embeddings** — id, school_id, subject_type (student/teacher), subject_id, embedding (vector), model_version, active (bool), enrolled_at, enrolled_by. *(Multiple rows per person allowed; re-enrolment adds a new row and deactivates the old; no raw image stored.)*
- **consents** — id, school_id, subject_id, type (biometric), granted_by (parent/guardian), granted_at, revoked_at.
- **attendance** — id, school_id, subject_type (student/teacher), subject_id, date, status (present/absent), check_in_at (timestamp, nullable), method (face/bypass), match_score (nullable), bypassed_by (nullable — teacher/admin who bypassed), reason (nullable), created_at. *(Timestamp captured now for later lateness derivation.)*
- **bills** — id, school_id, title, type (SPP/gedung/…), period, amount, scope (class/batch), due_date, created_by.
- **student_bills** — id, school_id, bill_id, student_id, amount, status (unpaid/paid), paid_at, payment_id.
- **payments** — id, school_id, student_bill_id, gateway, gateway_ref, method (va/qris/retail), amount, status, verified_at.
- **notifications** — id, school_id, to_number, template, payload, status, sent_at, error.
- **audit_log** — id, school_id, actor_user_id, action, target, detail, created_at.

---

## 11. Open Decisions (TODO)

1. **Identification mode:** 1:N walk-up (default — no card, smoothest) vs 1:1 verify (card/name first, then face — more accurate, also kills card-sharing). *Leaning 1:N.*
2. **Payment gateway:** Midtrans or Xendit — pick **one** for v1 (different webhook signature schemes; two = double integration + test surface). Add the second later only if needed.
3. **WhatsApp gateway:** official Meta Cloud API (stable; needs business verification + template approval) vs. unofficial Fonnte/Wablas (cheap, fast; risk of number ban). For daily messages to hundreds of parents, weigh the ban risk seriously.

---

## 12. Suggested Phasing

- **v1** — Attendance (presence only; face kiosk + liveness + audited manual override) + core roles/multi-tenancy + face enrolment + student report.
- **v2** — SPP billing + payment gateway + webhook + WhatsApp receipts.
- **v3** — Lateness enforcement (on-time/late derivation), teacher payroll report, treasurer ledger, polish.

Shipping all four original modules at once is the classic overreach; phasing keeps each release testable.

---

## Appendix — Rejected attendance methods (why we landed on face)

- **QR-by-phone (fresh/TOTP QR scanned by student phone):** rejected — identity is the logged-in account (shareable → silent buddy-punching), plus relay and GPS-spoofing holes. Token-based, can't bind to the person.
- **RFID/NFC card at gate reader:** rejected — cheaper and no biometric, but a card is transferable (absent student hands card to a present friend → buddy-punching). No token method closes this.
- **Digitized classroom roll call / photo-on-tap + gate staff:** viable non-biometric closure, but relies on continuous human attentiveness. Kept only as the manual-override path, not the primary.
- **Conclusion:** buddy-punching is only closed by binding identity to the body — a biometric — so face recognition (with mandatory liveness) is the primary method.
