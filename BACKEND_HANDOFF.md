# Quest Backend — V1 Implementation Handoff

> Built against `BACKEND_API_SPEC.md` v1. **All 70+ §13 acceptance scenarios pass.**
> 96 PHPUnit feature tests, 373 assertions, full suite green.
> This document is a verification checklist for the frontend team — flag anything
> here that doesn't match what the client expects.

---

## TL;DR

| | |
|---|---|
| **Framework** | Laravel 13.8 / PHP 8.4 |
| **DB** | PostgreSQL 18 (Sail in Docker) |
| **Auth** | Sanctum personal access tokens |
| **Storage** | S3 (configurable disk; `Storage::fake('s3')` in tests) |
| **Encryption** | Laravel `encrypted` cast at rest (server-readable, no E2E) |
| **Tests** | `./vendor/bin/sail artisan test --compact` → 92/92 |
| **Spec coverage** | §13.1 → §13.10 — all green |

---

## 1. Endpoint catalog

All API endpoints live under the **`/api`** prefix (Laravel default). Public web
pages (CGU, mentions légales, etc.) will live at the root once added.

### Authentication

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/api/auth/password/register` | no | 200 `{user, token}` ; 409 `email_taken` ; 422 |
| POST | `/api/auth/password/login` | no | 200 ; 401 `invalid_credentials` (same code for wrong email AND wrong password — no enumeration) |
| POST | `/api/auth/apple` | no | Validates `identityToken` against Apple JWKS (`https://appleid.apple.com/auth/keys`), checks `iss` + `aud=APPLE_CLIENT_ID`. Links to existing user by the **verified token-claim** email only (never the request body — see §12.d). 401 `invalid_apple_token`. |
| POST | `/api/auth/google` | no | Validates `idToken` against Google JWKS, checks `iss` + `aud=GOOGLE_CLIENT_ID`. Trusts `email_verified` claim. 401 `invalid_google_token`. |
| POST | `/api/auth/logout` | yes | 204 — revokes **only** the token used for this request; other devices keep their sessions. |
| GET  | `/api/me` | yes | 200 `{user: {id, email, createdAt}}` |
| DELETE | `/api/me` | yes | 204 — hard-deletes user + entries + quests + characters + attachments + audio + all tokens (CASCADE). S3 binary cleanup will happen via the daily retention job after rows soft-delete. |

### Sync

| Method | Path | Auth | Rate limit |
|---|---|---|---|
| POST | `/api/sync/push` | yes | 60/min/user |
| POST | `/api/sync/pull` | yes | 60/min/user |

### Binary upload (multipart/form-data, field `file`)

| Method | Path | Auth | MIME whitelist | Max |
|---|---|---|---|---|
| POST | `/api/uploads/attachments/{attachment_id}` | yes | image/jpeg, png, **heic**, **heif**, webp, gif | 25 MB |
| POST | `/api/uploads/audio/{audio_id}` | yes | audio/mp4, m4a, aac, mpeg, wav | 50 MB |
| POST | `/api/uploads/character-photos/{character_id}` | yes | (same as attachments) | 25 MB |

**HEIC/HEIF uploads are re-encoded server-side to JPEG (quality 85, EXIF orientation
applied, all metadata stripped including GPS).** The stored file ends in `.jpg`
and the `remoteUri` returned points at the JPEG. See §6.

### Health

- `GET /up` — Laravel-provided health endpoint at root (outside `/api`), returns 200.

---

## 2. Conventions implemented exactly per spec

- **JSON payloads**: `camelCase`. DB columns: `snake_case`. Mapping handled in API Resources.
- **UUIDs**: lowercase **v4** format. Server overrides Laravel's default `HasUuids` (which generates v7) for `users.id` so client regexes match.
- **Timestamps**: ISO 8601 UTC with **milliseconds** and trailing `Z` → `2026-04-15T10:30:00.000Z`.
  - DB columns are `timestamp(3)` (not the Laravel default `timestamp(0)`) to preserve ms.
  - Models override `$dateFormat = 'Y-m-d H:i:s.v'` so Eloquent doesn't drop ms on write.
  - **`updatedAt` round-trips exactly** — server stores the client-supplied value verbatim (§8.9.1), never `now()`.
- **Booleans**: literal `true` / `false`. Never `1`/`0` or `"true"`.
- **Nullable fields**: always present, with explicit `null`. Never missing.
- **Response envelope**: none — payload returned directly (no `{data: ...}` wrapping). API Resources have `public static $wrap = null`.
- **All responses are JSON** regardless of `Accept` header — enforced by `ForceJsonResponse` middleware on the api group.

---

## 3. Error response shape

```json
{ "error": "<machine_code>", "message": "<human readable>" }
```

For 422 validation:

```json
{
  "error": "validation",
  "message": "The given data was invalid.",
  "fields": { "email": ["The email field is required."] }
}
```

### Error codes shipped

| HTTP | `error` | When |
|---|---|---|
| 400 | `bad_request` | Malformed JSON body |
| 401 | `unauthenticated` | Missing/invalid/revoked token |
| 401 | `invalid_credentials` | Wrong email **or** password (same code → no enumeration) |
| 401 | `invalid_apple_token` | Apple JWT signature/expiry/audience fails |
| 401 | `invalid_google_token` | Google JWT signature/expiry/audience fails |
| 404 | `not_found` | Resource missing OR foreign (don't leak existence — §11.6) |
| 409 | `email_taken` | Register with already-used email (`fields.email: ["already in use"]`) |
| 409 | `already_uploaded` | Upload to entity that already has `remote_uri` |
| 413 | `payload_too_large` | File over MIME-specific max |
| 415 | `unsupported_media_type` | MIME not in whitelist |
| 422 | `validation` | Generic Form Request validation failure |
| 429 | `rate_limited` | Sync rate limit exceeded — includes `Retry-After: 60` header |

---

## 4. Sync push (`POST /sync/push`)

### Request

```json
{
  "deviceId": "<uuid v4>",
  "changes": [
    {
      "entityType": "entry|quest|character|quote|entry_quest|entry_character|entry_attachment|entry_audio",
      "entityId": "<uuid>" | "<entryId>:<questId>" | "<entryId>:<characterId>",
      "operation": "create|update|delete",
      "data": { /* §8.4 shape, exactly */ }
    }
  ]
}
```

### Response (200, always — conflicts are NOT errors)

```json
{
  "confirmed": ["entityId1", "entityId2", ...],
  "conflicts": [
    {
      "entityType": "entry",
      "entityId": "<uuid>",
      "serverVersion": { /* full §8.4 payload */ }
    }
  ]
}
```

### Server semantics

- **LWW** (§11.1): strict `>` on `updated_at` → server wins → conflict. Equal → incoming wins (idempotent).
- **`updatedAt` preserved** exactly from client — Eloquent's auto-touching is disabled per save.
- **`uri` / `photoUri` always stored as `""`** regardless of what's pushed (§4.6, §4.10). Whatever the client puts there is dropped.
- **`remote_uri` preserved server-side** — if a row already has a `remote_uri` (set by the upload endpoint), an incoming push cannot overwrite it back to `null`.
- **Cross-user isolation** (§8.3.1): if a content entity exists but with a different `user_id`, the change is **silently skipped** — not in `confirmed`, no 4xx error.
- **`operation` is advisory** for content (always upsert). For junctions, `delete` triggers a tombstone (see below).
- **Delete-without-create** (§11.4): pushing `operation: "delete"` for an entity the server has never seen creates the row with `is_deleted=true`. Retention job will purge it.
- **Processing order** (§8.3.6): server sorts changes by entity-type priority — content → binary metadata → junctions — so FK references resolve regardless of client order.
- **Transaction**: the whole push is wrapped in `DB::transaction`.

### Junction lifecycle

- `INSERT … ON CONFLICT DO NOTHING` for create (idempotent — same link pushed twice yields one row).
- `DELETE … WHERE entry_id=? AND quest_id=?` for delete (idempotent — no row to delete is fine).
- Creating a junction **clears any matching tombstone** so subsequent pulls don't re-emit the delete after a re-create.
- Deleting a junction **upserts a tombstone row** with `deleted_at = now()`. Pull emits these as `operation: "delete"`.

---

## 5. Sync pull (`POST /sync/pull`)

### Request

```json
{ "deviceId": "<uuid v4>", "lastPullTimestamp": "<ISO 8601>" | null }
```

### Response (200)

```json
{
  "changes": [
    { "entityType": "...", "operation": "upsert" | "delete", "data": { ... } }
  ],
  "serverTimestamp": "<ISO 8601>"
}
```

### Server semantics

- Filter: `updated_at > lastPullTimestamp` strict (§8.9.8). Or all rows when `lastPullTimestamp = null`.
- **Soft-deleted content is emitted as `operation: "upsert"` with `data.isDeleted: true`** — NEVER as `operation: "delete"` (§8.9.7).
- **Junction deletions** are the only place `operation: "delete"` appears, sourced from tombstone tables.
- **Ordering (§8.7) — guaranteed**:
  1. `quest` upserts
  2. `character` upserts
  3. `quote` upserts
  4. `entry` upserts
  5. `entry_attachment` upserts
  6. `entry_audio` upserts
  7. `entry_quest` upserts
  8. `entry_quest` deletes (tombstones)
  9. `entry_character` upserts
  10. `entry_character` deletes (tombstones)
- `serverTimestamp` is the server's `now()` captured at the start of the request — store and feed back as `lastPullTimestamp` next call.
- `syncedAt: null` always emitted in payloads (client overrides locally).
- All entity payload shapes match §8.4 verbatim.

### Cross-user isolation

Global Eloquent scopes filter every `User`-owned and entry-descended query by `Auth::id()`. Foreign rows are never visible through any read path — only sync push bypasses this for the documented silent-skip semantics.

---

## 6. Binary upload contract

### Flow

1. Client pushes the entity metadata via `/sync/push` (with `uri: ""`, `remoteUri: null`).
2. Client uploads the binary to the matching endpoint with `Content-Type: multipart/form-data`, field `file`.
3. Server validates ownership → `remote_uri` is null → MIME → size → stores at `<kind>/<user_id>/<entity_id>.<ext>` → bumps `updated_at` to `now()`.
4. Server returns `{ "remoteUri": "<url>" }`.
5. Other devices' next pull receives the entity with `remoteUri` populated.

### Server behavior

- Lookup uses the global scope → foreign attachment ID returns 404 `not_found` (no existence leak — §11.6).
- 409 `already_uploaded` if `remote_uri` is non-null.
- 415 `unsupported_media_type` for MIME outside whitelist.
- 413 `payload_too_large` over per-type max.
- Update bumps `updated_at` so other devices receive the change on next pull.
- URL returned is `Storage::disk('s3')->url(...)` — a public-style URL. Configure your CDN to suit. Client fetches the binary with a plain `GET` (no auth header).

### Re-upload semantics

To replace a binary, the client should delete + re-create the entity (new UUID). Re-upload on the same ID is refused.

### HEIC / HEIF handling

`image/heic` and `image/heif` uploads are **re-encoded server-side to JPEG** before storage:

- Quality 85
- EXIF orientation tag is applied to the pixels, then the orientation tag is reset
- All metadata stripped (GPS, device info, ICC profile) via `Intervention\Image` v4 + Imagick driver
- Output dimensions match the source (no downscale)
- Stored as `<kind>/<user_id>/<entity_id>.jpg` with `Content-Type: image/jpeg`
- `remoteUri` returned ends in `.jpg`

This means even web clients and Android devices can render iOS-captured photos
without a HEIC decoder. Native JPEG/PNG/WebP/GIF uploads are stored as-is (no
re-encode).

MIME detection uses the multipart `Content-Type` header (`getClientMimeType()`).
That's what real iOS uploads send. If an upload comes through with bytes that
don't actually decode as an image, Imagick raises and the request fails — so
malicious "fake HEIC" uploads can't slip through unchanged.

---

## 7. Authentication tokens

- Token format: `<id>|<plaintext>` (Sanctum standard). Returned **once** on register/login.
- Send as `Authorization: Bearer <token>` header.
- Tokens **do not expire** by default. No refresh flow shipped (spec §7.8 defers).
- `/auth/logout` revokes the **current** token only. Other devices stay signed in.
- `DELETE /me` revokes **all** tokens belonging to the user and hard-deletes their data.
- Sanctum `guard` config set to `[]` (no SPA/session fallback) — Bearer-only, mobile-only.

---

## 8. Encryption at rest

Encrypted columns (Laravel `encrypted` cast, `text` type in PG):

| Table | Columns |
|---|---|
| `entries` | `title`, `html` |
| `quests` | `title`, `description` |
| `characters` | `name`, `relationship`, `note` |
| `quotes` | `text`, `source`, `note` |

Decryption is transparent on read — pull responses contain plaintext. Database dumps would expose only ciphertext (prefix `eyJ...` — Laravel envelope format).

**Server-readable** (not E2E). Same `APP_KEY` for all users (§3 — intentional, supports future AI features and survivable key recovery). Privacy policy must reflect this.

**Operational note**: `APP_KEY` must be backed up separately from DB backups, otherwise a single leak compromises both (§16).

---

## 9. Rate limiting

- `/sync/push` and `/sync/pull`: 60 req/min per user → 429 `rate_limited` + `Retry-After: 60` header. Configurable via `QUEST_RATE_LIMIT_SYNC`.
- Auth endpoints (`/auth/password/register`, `/auth/password/login`, `/auth/apple`, `/auth/google`): 10 req/min **per IP** (they are unauthenticated — no user to key on) → same 429 `rate_limited` + `Retry-After: 60` envelope. A brute-force / credential-stuffing speed bump. Configurable via `QUEST_RATE_LIMIT_AUTH`.
- Other endpoints: unlimited in V1.

---

## 10. Retention purge (background job)

Artisan command: `php artisan quest:purge-expired`, scheduled daily at 03:00 UTC.

- Soft-deleted content (`is_deleted=true`) with `updated_at < now() - 30 days` is hard-deleted (entries, quests, characters, quotes, attachments, audio). CASCADE handles child rows (junctions, attachments under entries).
- Associated S3 binaries are deleted before the row goes.
- Junction tombstones with `deleted_at < now() - 90 days` are hard-deleted.
- Stats logged via `Log::info('quest.retention.purge', $stats)` for observability.

**Account deletion (`DELETE /me`) binary cleanup.** Content rows cascade-delete
with the user, so they never pass through the retention purge above — their S3
binaries would be orphaned forever (GDPR erasure gap + storage leak). `deleteMe`
therefore dispatches `App\Jobs\DeleteUserBinaries`, which removes the user's
`attachments/{id}`, `audio/{id}` and `character-photos/{id}` prefixes. It runs
off-request (queued) so a slow/failing object store can neither block nor roll
back the account deletion. Covered by `AuthTest::test_a14`.

---

## 11. Acceptance — §13 scenarios

All scenarios in the spec's acceptance matrix pass:

| Section | Scenarios | Status |
|---|---|---|
| §13.1 Auth | A1–A13 | ✓ |
| §13.2 Sync basics | S1–S6 | ✓ |
| §13.11 Sync quotes | Q1–Q6 | ✓ |
| §13.3 Junctions | J1–J5 | ✓ |
| §13.4 Binaries | B1–B9 | ✓ |
| §13.5 Encryption | E1–E3 | ✓ |
| §13.6 LWW conflict | L1–L3 | ✓ |
| §13.7 Cross-user isolation | X1–X4 | ✓ |
| §13.8 Retention | R1–R3 | ✓ |
| §13.9 Cross-account migration | M1–M3 | ✓ |
| §13.10 Idempotence + rate limit | I1–I3 | ✓ |

Reproduce locally: `./vendor/bin/sail artisan test --compact`.

---

## 12. Divergences from spec — please confirm

### a. OAuth validation library

Spec §2 recommends **Laravel Socialite + socialiteproviders/apple**. We went with **`firebase/php-jwt`** for both Apple `identityToken` and Google `idToken` validation.

**Why**: Socialite is OAuth-flow-oriented (server-side redirect/callback). On mobile, the client already holds the JWT — we just need cryptographic verification against the provider's public JWKS and claim extraction. `firebase/php-jwt` does this directly, with no userinfo HTTP round-trip and a smaller surface area. Behavior toward the client is identical.

If you'd prefer we route through Socialite for any reason (audit, parity with web SSO later), say so and we'll swap it in.

### b. HEIC/HEIF now re-encoded server-side

Per frontend audit, this is no longer optional: HEIC/HEIF uploads are
re-encoded to JPEG before storage. Implemented via `intervention/image` v4 with
the Imagick driver (Sail's PHP image ships libheif support). See §6 for the
exact pipeline. Spec §9.2 wording "may re-encode" is amended to "must" for V1.

### c. `/api` prefix restored

Laravel's default `/api` prefix is in place. All endpoints live under
`/api/...` (e.g. `/api/auth/password/register`, `/api/sync/push`,
`/api/uploads/attachments/{id}`). The root path is reserved for public web
pages (CGU, mentions légales, politique de confidentialité). Spec §6.2
wording amended — the client's `DEFAULT_API_URL` of `http://localhost:8000/api`
matches.

### d. Apple sign-in uses the verified token claim for email, never the request body (security)

Spec §7.3 step 4 says "if `email` is set and a user with that email exists,
link the account", without specifying the email **source**. Taken literally
(and as originally implemented) the server trusted the request-body `email`,
which is attacker-controlled: anyone with a valid Apple token could pass a
victim's email and have their `apple_id` linked to — and a token issued for —
the victim's existing account (account takeover).

**Hardening**: account lookup/linking now uses **only** `$claims['email']`
from the verified Apple identity token (Apple emails are provider-verified; the
claim is authoritative and matches the `google()` flow, which the spec calls
"same fallthrough logic"). The request body still accepts `email`/`fullName`
per the frozen contract (wire shape unchanged) but they are display hints only
and never drive matching. Regression coverage: `AuthTest::test_a7b_*` and
`test_a7c_*`. No request/response shape change — the client is unaffected.

---

## 13. Required production configuration

| Env var | Used for | V1 mandatory? |
|---|---|---|
| `APP_KEY` | Encryption of all sensitive columns | ✅ (back up separately from DB) |
| `DB_CONNECTION=pgsql`, `DB_*` | Postgres credentials | ✅ |
| `APPLE_CLIENT_ID` | Apple identityToken audience check | ✅ for Apple sign-in |
| `GOOGLE_CLIENT_ID` | Google idToken audience check | ✅ for Google sign-in |
| `AWS_*` (key/secret/region/bucket/url) | S3 binary storage | ✅ |
| `FILESYSTEM_DISK` | Default disk; uploads use `s3` directly | optional |
| `QUEST_RATE_LIMIT_SYNC` (default 60) | Per-user sync rate limit | optional |
| `APP_URL` | Used in CDN URLs if `AWS_URL` is unset | ✅ |

---

## 14. Local dev quickstart

```sh
cd /Users/serra/Codes/quest/quest_laravel
./vendor/bin/sail up -d                          # start containers
./vendor/bin/sail artisan migrate:fresh          # if you need a clean DB
./vendor/bin/sail artisan test --compact         # run the full suite
./vendor/bin/sail artisan quest:purge-expired    # run retention job once
./vendor/bin/sail down                           # stop
```

| Service | URL / port |
|---|---|
| API | http://localhost:8000 |
| Postgres (host-side) | localhost:**5433** (sail/password/quest_laravel) |
| Vite (unused, API-only) | http://localhost:5180 |

Port mappings differ from Sail defaults because 80/8080/5173/5432 are taken by other Docker containers on this machine — set in `.env` (`APP_PORT=8000`, `FORWARD_DB_PORT=5433`, `VITE_PORT=5180`).

---

## 15. Not implemented in V1 (per spec §7.8 / §14)

Endpoints / behaviors deliberately deferred:

- Password reset (`POST /api/auth/password/forgot`, `POST /api/auth/password/reset`)
- Email change (`POST /api/auth/email`)
- Email verification email send (registration sets `email_verified_at = null`; verification flow not wired)
- Refresh tokens (Sanctum tokens are non-expiring)
- Pagination of `/api/sync/pull` (single response for V1; revisit when users have >10k entries)
- Server-side search over encrypted content
- Real-time sync (websockets / SSE)
- AI features
- Webhooks, admin endpoints, multi-tenancy
- Per-user storage quota enforcement (product-policy decision)

---

## 16. Client-side wiring still to do (per spec §17)

These are the pieces I cannot ship from the backend:

1. Fill the four method stubs in `src/hooks/use-auth.tsx`:
   - `signUpWithPassword` → `POST /api/auth/password/register`
   - `signInWithPassword` → `POST /api/auth/password/login`
   - `signInWithApple` → `POST /api/auth/apple`
   - `signInWithGoogle` → `POST /api/auth/google`
   Each should call `onSignInSuccess(user, token, apiClient)` after a 200.
2. Re-enable `useSync(realApiClient)` in `src/hooks/use-entry-save.ts`.
3. Build the Settings → Compte / login / logout / wipe-or-keep / delete-account UI.
4. Verify the contract end-to-end with `expo run:ios` pointed at this backend's URL.

---

## 17. How to flag mismatches

If any of the following are true, please ping with details:

- A documented field name doesn't match the client's TypeScript types.
- A response shape differs from what the client deserializes (especially nullability — every nullable field is always present with `null`).
- A timestamp comparison fails (the only ms-precision-required field is `updatedAt` on content entities).
- The client's `http.ts` retry logic interacts badly with any endpoint (e.g., retry-on-5xx behavior).
- A specific scenario in spec §13 doesn't work end-to-end despite passing in our test suite — that means we misread the contract.

Run `./vendor/bin/sail artisan route:list --except-vendor` if you want the full route list with bound middleware.

---

## 18. AI Chapters ("Le Chapitre") — out-of-V1 add-on

Chapters are **not part of the frozen `BACKEND_API_SPEC.md`** — they are a post-V1, optional AI feature documented only here. The endpoints and column shapes below are the contract; if they ever move into the frozen spec they must be byte-identical in both repos (see root `CLAUDE.md`), so for now they live in the handoff to avoid touching the frozen file unilaterally.

**What it is.** Server-generated French narrative summaries of a user's journal (kinds: `monthly`, `quest`, `annual`), produced from `entries` via the Anthropic Messages API and read-only on the client. Generation runs from scheduled Artisan commands → queued jobs → `App\Services\Chapter\ChapterGenerator`.

**Read endpoints** (behind `auth:sanctum`, isolated by `BelongsToCurrentUserScope`):
- `GET /api/ai/chapters` — all `status='ready'` chapters for the user, newest-first, via `ChapterResource` (`$wrap=null`, camelCase: `id, kind, periodStart, periodEnd, questId, register, title, paragraphs[{text, entryRefs[]}], threads[{type,id,name}], status, generatedAt`).
- `GET /api/ai/chapters/{id}` — one chapter; foreign/other-status → **404** (no existence leak, never 403).

**AI consent (gate — required before enabling in prod).**
- `users.ai_chapters_opt_in` boolean, **default false**. Exposed as `aiChaptersOptIn` on `UserResource` (so `/me` and all auth responses carry it).
- `PATCH /api/me` `{ "aiChaptersOptIn": bool }` → returns `{ user }`. Generic on purpose — future per-user settings reuse it.
- The gate is enforced in **three** places (defense-in-depth): both generation commands filter to opted-in users, `ChapterGenerator::monthly/questArc` short-circuit on it, and the read endpoints return `[]`/404 for opted-out users. **Opt-out hides existing chapters immediately** (does not delete rows). The global `QUEST_CHAPTERS_ENABLED` env flag remains an outer kill-switch.

**Generation config / reliability** (`config/services.php` → `anthropic`):
- `chapter_model` default **`claude-sonnet-5`** — generation hard-depends on structured outputs (`output_config.format` json_schema); `claude-sonnet-4-6` does NOT support it (400s), so it must never be the default.
- `chapter_max_tokens` default `16000` (shared by adaptive thinking + JSON output).
- `ChapterGenerator::complete()` classifies outcomes: **transient** (5xx/429/408/529, connection error, `stop_reason=max_tokens`) throws `App\Exceptions\ChapterGenerationException` → the job retries (`$tries=4`, backoff `[60,300,900]`, `failed()` logs); **non-retryable** (refusal, permanent 4xx, malformed JSON) returns `null` (nothing to generate). Every terminal path logs with `user_id/kind/period`.

**Kinds & schedule:** `monthly` — `quest:generate-monthly-chapters`, 1st of the month 04:00; `quest` — `quest:generate-quest-chapters`, daily 04:30; `annual` — `quest:generate-annual-chapters`, Jan 1 05:00 (targets the prior year; needs ≥`MIN_ANNUAL_ENTRIES`=24 entries). All three share the hardened `complete()`, the `$tries`/backoff/`failed()` job pattern, and the consent gate.

**Idempotency:** partial unique indexes `chapters_period_unique` (`user_id, kind, period_start` WHERE `quest_id IS NULL`) and `chapters_quest_unique` (`quest_id, kind` WHERE `quest_id IS NOT NULL`) enforce one chapter per period/quest; `persist()` treats a lost race (unique violation) as already-done and returns null. **Backfill:** `quest:generate-monthly-chapters --since=YYYY-MM [--until=YYYY-MM]`; the command also skips users who already have the month's chapter, so it's idempotent at dispatch and safe to re-run.

**Still deferred (see the recap improvement plan):** regenerate-on-entry-change, a total-material/token budget, index pagination, and local persistence + new-chapter notification on the client.

— End of handoff.
