# Quest — Backend API Specification

> **Reader**: you are implementing the Laravel backend for the Quest mobile app. The React Native client is already built, tested, and frozen on the contract described in this document. Your job is to implement endpoints that satisfy every contract here — when the acceptance tests in §13 pass, the backend is shippable.
>
> This document is the **single source of truth**. It is intentionally exhaustive. Wherever it says "the client expects", "the client sends", or "the client treats", that is non-negotiable — the client code already enforces those behaviors and cannot be changed without coordinating with the client team.

---

## 0. Document conventions

- **JSON casing**: all API payloads use `camelCase`. The database uses `snake_case`. Map between the two in your serializers / API resources.
- **Timestamps**: every timestamp is an ISO 8601 UTC string with millisecond precision and a trailing `Z`, e.g. `2026-04-15T10:30:00.000Z`. No timezones, no local times, no Unix epochs.
- **UUIDs**: every entity ID is a UUID v4, lowercase, with hyphens: `f47ac10b-58cc-4372-a567-0e02b2c3d479`. Entity IDs are **generated client-side** — the server respects them and never reassigns. User IDs are generated server-side.
- **Field names match exactly**: do not abbreviate, do not pluralize, do not nest differently. The client deserializes by literal field name match.
- **No nulls vs missing distinction**: a `null` field and a missing field are treated identically by the client. Prefer explicit `null` for clarity.
- **HTTP**: REST conventions. JSON request/response unless explicitly multipart for uploads.
- **Authentication**: every endpoint except those marked "unauthenticated" requires `Authorization: Bearer <token>`. The token is a Sanctum personal access token.
- **Errors**: see §6.4 — unified error format across all endpoints.
- **Idempotence**: every write endpoint must be idempotent on entity ID (see §11.2).

---

## 1. Context

### 1.1 What is Quest

Quest is a privacy-first journaling app for iOS and Android. The user's life is structured as a story: **main quest** (a year-long theme), **side quests** (ongoing projects, relationships, transitions), and **characters** (recurring people). Entries (rich-text journal pages) can be linked to one or more quests and to characters. Replay views show every entry that touched a given quest or character, in chronological order — the moat is multi-year accumulation that becomes irreproducible elsewhere.

The product is local-first. Auth is optional. The backend exists to enable multi-device sync, cloud backup, and (later) AI features.

### 1.2 The client architecture (already built)

- **Stack**: React Native 0.83, Expo SDK 55, React 19, TypeScript strict.
- **Local storage**: SQLite via `expo-sqlite` (10 migrations applied, schema is stable).
- **State**: React Query for every read/write; mutations are direct CRUD calls + cache invalidation (no `useMutation` except a few places).
- **Sync engine** (`src/data/sync-engine.ts`):
  - Push-first then pull on every sync.
  - Mutex prevents concurrent runs; a `syncAgainAfter` flag handles the "second sync requested while first was running" case.
  - Conflict resolution: server version wins (last-write-wins on `updatedAt`).
  - Push request shape, response shape, pull request shape, pull response shape are all locked (see §8).
- **Sync queue** (`src/data/sync-queue.ts`): every local write enqueues into `sync_queue`. The queue is **deduplicated** at read time per entity:
  - `create + update(s) → create` with the latest payload
  - `anything + delete → delete` with the latest payload
  - `update(s) → update` with the latest payload
  - The queue is ordered by insertion (`id ASC`); deduplicated items preserve the order of their FIRST occurrence.
- **Auth-sync bridge** (`src/data/auth-sync-bridge.ts`): `onSignInSuccess(user, token, client?)` orchestrates cross-account detection, credential persistence, cache update, and triggers an initial sync via the `client`. Three exits at the cross-account prompt: `merge` (silent push of local queue into the new account), `wipe` (`wipeAllData()` then sign-in), `cancel` (throw `SignInCanceledError`, no persistence).
- **HTTP wrapper** (`src/data/api/http.ts`): single fetch wrapper, injects auth header, applies 15s timeout, retries 2x with exponential backoff on network/timeout/5xx (4xx never retries), normalizes errors into `NetworkError | HttpError | AuthError`.
- **Auth strategies wired client-side (stubs today)**: email/password, Sign in with Apple, Sign in with Google. Each is a thin wrapper that calls the relevant endpoint then delegates to `onSignInSuccess`.

### 1.3 What is NOT yet on the client

- Real implementations of the four `signInWith*` / `signUpWithPassword` methods (they currently throw).
- Account screen UI (Settings → Compte).
- Logout dialog (keep vs wipe local data).
- Binary upload triggers (attachments and audio currently push metadata via `/sync/push` but no binary upload mechanism exists yet — see §9).
- `remotePhotoUri` field on the `Character` type (see §4.6).

The backend should expose endpoints for all of the above so the client can wire them in a later pass. If the client doesn't call an endpoint in V1, that's fine — the endpoint should still exist and be tested.

---

## 2. Stack requirements

| Layer | Required | Notes |
|---|---|---|
| Framework | Laravel **11+** | 12 preferred |
| PHP | **8.3+** | |
| Auth | **Sanctum** | Personal access tokens, not SPA cookies. The client is a mobile app, not a SPA. |
| Database | **PostgreSQL 14+** | MySQL 8+ acceptable. PostgreSQL preferred for native UUID type and JSONB. |
| Storage | **S3-compatible** | AWS S3, Cloudflare R2, DigitalOcean Spaces, Backblaze B2 — any S3-API-compatible backend. For local dev, the `local` driver is fine. |
| OAuth | **Laravel Socialite** | + `socialiteproviders/apple` for Sign in with Apple |
| Encryption | Native Laravel `encrypted` cast | See §5 |
| Migrations | Standard Laravel migrations | One migration per logical change, no monolithic schema dump |
| Validation | Form Requests | One Form Request class per endpoint |
| Testing | Pest or PHPUnit | Feature tests are MANDATORY for §13 acceptance criteria |
| Queue (optional) | None required for V1 | If used for binary upload processing, Redis-backed queue |
| Mail | Any provider | Required for password reset + email verification (V1) |

---

## 3. Product decisions (frozen)

These were taken during client design. Do NOT propose alternatives without coordinating with the client team.

| Decision | Choice |
|---|---|
| Account requirement | **Optional**. The client works fully offline-local with no account. Sync is an opt-in add-on. |
| Auth methods | **Email + password** (Sanctum) **+ Sign in with Apple** + **Sign in with Google**. All three create a `User` row in the same table. Apple is mandatory on iOS because Google OAuth is offered. |
| Encryption | **Server-side at rest, server-readable**. Use Laravel `encrypted` casts. **No E2E**. **No per-user key**. The `APP_KEY` encrypts every user's content uniformly. This is intentional: future AI features need server-readable content, and per-user-key recovery is complex/risky. The privacy policy must explicitly state that Quest can technically read user content. |
| Logout data wipe | Client-orchestrated. The client shows a modal at logout asking "keep data on this device or wipe?" and calls `wipeAllData()` locally if user opts in. Server-side, logout just revokes the token. |
| Migration local→cloud | **Silent merge by default**. The client's `auth-sync-bridge` handles this: on first sign-in on a device, all local queued changes are pushed to the new account. If a different user previously signed in on this device, the client prompts the user (merge / wipe / cancel). |
| Account deletion | **Required by Apple**. Endpoint `DELETE /me` purges the user and all their data. |

---

## 4. Database schema

All tables use these defaults unless specified otherwise:

- Primary key: `id` UUID (server-generated for `users`, client-generated for all others).
- Timestamps `created_at` and `updated_at`: type `timestamp with time zone` (PostgreSQL) / `datetime` (MySQL), NOT NULL.
- `is_deleted`: boolean, NOT NULL, default `false`. Used for soft-delete. Soft-deleted rows survive in DB for 30 days then are hard-purged by a daily job (§11.5).
- Foreign keys: `ON DELETE CASCADE` unless noted otherwise.
- Indexes: every foreign key gets an index. Additional indexes specified per table.

### 4.1 `users`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | UUID | no | Server-generated at registration |
| `email` | string(255) | yes | Unique when not null. Nullable because Apple/Google may not provide email. |
| `email_verified_at` | timestamp | yes | Set after the user clicks the verification email link |
| `password` | string(255) | yes | Bcrypt hash. NULL for OAuth-only users. |
| `apple_id` | string(255) | yes | Unique when not null. Apple's stable `sub` claim. |
| `google_id` | string(255) | yes | Unique when not null. Google's stable `sub` claim. |
| `created_at` | timestamp | no | |
| `updated_at` | timestamp | no | |

**Indexes**: `email`, `apple_id`, `google_id` (each unique partial index where not null).

**Constraint**: at least one of `password`, `apple_id`, `google_id` must be non-null. Enforce in app logic (Laravel doesn't natively express this).

### 4.2 `personal_access_tokens`

Standard Sanctum table — use `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` and run the published migration unchanged.

Token format used by client: `Bearer <plain-text-token>`. The token comes from `$user->createToken('quest-mobile')->plainTextToken` and is returned **once** to the client.

### 4.3 `entries`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | UUID | no | Client-generated |
| `user_id` | UUID | no | FK → `users.id` ON DELETE CASCADE |
| `title` | text | no | **Encrypted**. Empty string allowed. |
| `html` | text | no | **Encrypted**. The rich-text body. Empty string allowed. |
| `mood` | string(50) | yes | One of: `empty`, `sad`, `stressed`, `angry`, `anxious`, `calm`, `grateful`, `joyful`. NOT encrypted (used for analytics). |
| `latitude` | double | yes | |
| `longitude` | double | yes | |
| `entry_date` | timestamp | yes | User-set "date of the entry". Falls back to `created_at` if null. |
| `is_deleted` | boolean | no | Default false |
| `created_at` | timestamp | no | |
| `updated_at` | timestamp | no | Client-supplied; server stores as-is unless the client's value is older than the server's existing value (conflict — see §11.1) |

**Indexes**:
- `user_id, entry_date DESC` (timeline reads)
- `user_id, updated_at DESC` (pull-changes-since)
- `user_id, is_deleted, updated_at DESC` (active vs trash)

### 4.4 `quests`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | UUID | no | Client-generated |
| `user_id` | UUID | no | FK CASCADE |
| `type` | string(10) | no | Enum: `main`, `side`, `daily` |
| `title` | string(255) | no | **Encrypted** |
| `description` | text | no | **Encrypted**. Default empty string. |
| `status` | string(15) | no | Enum: `active`, `completed`, `archived`. Default `active`. |
| `color` | string(20) | yes | Hex color e.g. `#7B6BD4`. NOT encrypted. |
| `icon` | string(20) | yes | Emoji or short text. NOT encrypted. |
| `started_at` | timestamp | yes | |
| `completed_at` | timestamp | yes | |
| `is_deleted` | boolean | no | Default false |
| `created_at` | timestamp | no | |
| `updated_at` | timestamp | no | |

**Indexes**: `user_id, status`, `user_id, type, status`, `user_id, is_deleted, updated_at DESC`.

### 4.5 `characters`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | UUID | no | Client-generated |
| `user_id` | UUID | no | FK CASCADE |
| `name` | string(255) | no | **Encrypted** |
| `relationship` | string(255) | yes | **Encrypted** |
| `note` | text | no | **Encrypted**. Default empty string. |
| `photo_uri` | string(2048) | no | **Always stored as `''` server-side.** See §4.6. Default `''`. |
| `remote_photo_uri` | string(2048) | yes | The cloud URL once a photo is uploaded via `POST /uploads/character-photos/{character_id}`. |
| `color` | string(20) | yes | |
| `is_deleted` | boolean | no | Default false |
| `created_at` | timestamp | no | |
| `updated_at` | timestamp | no | |

**Indexes**: `user_id, name`, `user_id, is_deleted, updated_at DESC`.

### 4.6 `photo_uri` semantics for characters

The client today sends `photoUri` in character push payloads with a value like `file:///var/mobile/Containers/.../Documents/attachments/abc.jpg`. **This is a local device URI and is meaningless on any other device.** The server **must always** store `photo_uri = ''` regardless of what the client sends. When pulling, the server **must always** emit `photoUri: ''`.

The cloud URL (if a photo has been uploaded) lives in `remote_photo_uri` / `remotePhotoUri`. The client today does not read `remotePhotoUri`, so cross-device character photos are degraded in V1 — the schema is forward-compatible for when the client team adds the upload trigger.

### 4.7 `entry_quests`

Composite junction table.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `entry_id` | UUID | no | FK → `entries.id` ON DELETE CASCADE |
| `quest_id` | UUID | no | FK → `quests.id` ON DELETE CASCADE |
| `created_at` | timestamp | no | |

**Primary key**: `(entry_id, quest_id)` composite.

**Indexes**: index on `entry_id`, index on `quest_id`.

**No `user_id`**: the user is implied by the entry. Enforce in queries (joins on the user-owned entry).

### 4.8 `entry_characters`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `entry_id` | UUID | no | FK CASCADE |
| `character_id` | UUID | no | FK CASCADE |
| `created_at` | timestamp | no | |

**Primary key**: `(entry_id, character_id)`. **Indexes**: same as `entry_quests`.

### 4.9 `entry_attachments`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | UUID | no | Client-generated |
| `entry_id` | UUID | no | FK → `entries.id` ON DELETE CASCADE |
| `uri` | string(2048) | no | **Always stored as `''` server-side.** See §4.10. Default `''`. |
| `remote_uri` | string(2048) | yes | Set by `POST /uploads/attachments/{attachment_id}` |
| `width` | integer | no | Pixels |
| `height` | integer | no | Pixels |
| `is_deleted` | boolean | no | Default false |
| `created_at` | timestamp | no | |
| `updated_at` | timestamp | no | |

**Indexes**: `entry_id`, `is_deleted, updated_at`.

**Implicit user_id**: same approach as junctions — enforce via joins to the owning entry.

### 4.10 `uri` semantics for attachments and audio

Identical to §4.6 for character photos: the `uri` in client payloads is a local device path. Server **must always** store `uri = ''` and emit `uri = ''`. The cloud URL lives in `remote_uri` / `remoteUri`, set by the upload endpoint.

### 4.11 `entry_audio`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | UUID | no | Client-generated |
| `entry_id` | UUID | no | FK CASCADE |
| `uri` | string(2048) | no | **Always stored as `''`.** Default `''`. |
| `remote_uri` | string(2048) | yes | Set by `POST /uploads/audio/{audio_id}` |
| `duration_ms` | integer | no | |
| `waveform` | JSON | no | Array of floats 0..1, max 200 samples. PostgreSQL: JSONB. MySQL: JSON. Always emit as a JSON array (not a stringified array) in pull payloads. |
| `is_deleted` | boolean | no | Default false |
| `created_at` | timestamp | no | |
| `updated_at` | timestamp | no | |

**Indexes**: `entry_id`, `is_deleted, updated_at`.

### 4.12 No `sync_meta`, no `devices`, no `synced_at` server-side

The server does NOT track per-device sync state. The `deviceId` in sync requests is informational only (useful for logging / debugging). The client maintains its own `last_pull_timestamp` and `device_id` locally — the server doesn't store these.

The server-side equivalent of "have I been synced" is implicit: if a row exists in the table for this user, it has been pushed.

---

## 5. Encryption

### 5.1 Fields to encrypt

Use Laravel's native `encrypted` cast (`$casts = ['title' => 'encrypted', ...]`).

| Table | Encrypted columns |
|---|---|
| `entries` | `title`, `html` |
| `quests` | `title`, `description` |
| `characters` | `name`, `relationship`, `note` |

Everything else is plaintext. Specifically: `mood`, `latitude`, `longitude`, `entry_date`, `status`, `type`, `color`, `icon`, timestamps, IDs, foreign keys, `width`, `height`, `duration_ms`, `waveform`, `is_deleted` — all plaintext.

### 5.2 Key management

- `APP_KEY` is a 32-byte random string stored in the production environment (EAS secret manager, AWS Secrets Manager, or equivalent). Never committed.
- `APP_KEY` rotation is out of scope for V1. Document the rotation procedure for V2.
- All encrypted columns must be `text` type in PostgreSQL / `LONGTEXT` in MySQL — encrypted values can grow ~30% larger than plaintext.

### 5.3 What encryption does NOT do

- **Does not prevent server-side access**: Laravel can read every encrypted field with its `APP_KEY`. The encryption is for "DB dump leak" protection, not "rogue admin" protection.
- **Does not prevent search**: encrypted columns cannot be `WHERE`'d, `LIKE`'d, or indexed for full-text search. The client does its own search locally — the server is not expected to expose a server-side search endpoint in V1.
- **Does not work on JSON internals**: if you ever need to encrypt `waveform`, encrypt the whole JSON string, not individual values. (V1: don't encrypt waveforms.)

---

## 6. Conventions

### 6.1 Authentication header

```
Authorization: Bearer <sanctum-token>
```

Every endpoint except those marked **"unauthenticated"** requires this header. Missing or invalid → 401.

### 6.2 Request format

Content-Type: `application/json` for everything except upload endpoints (multipart). UTF-8 always.

### 6.3 Success response shape

Endpoint-specific. No envelope (`{ "data": ... }`) — return the payload directly. JSON.

### 6.4 Error response shape

```json
{
  "error": "<machine_readable_code>",
  "message": "<human readable message in English>"
}
```

For 422 validation errors, include a `fields` object:

```json
{
  "error": "validation",
  "message": "The given data was invalid.",
  "fields": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

Standard error codes:

| HTTP | `error` value | When |
|---|---|---|
| 400 | `bad_request` | Malformed JSON, missing required headers |
| 401 | `unauthenticated` | Missing/invalid/revoked token |
| 401 | `invalid_credentials` | Wrong email or password on `/auth/password/login` |
| 403 | `forbidden` | Authenticated but not allowed (e.g. accessing another user's entity) |
| 404 | `not_found` | Resource doesn't exist or doesn't belong to this user |
| 409 | `conflict` | Reserved for future use (currently sync conflicts return 200 with a body) |
| 422 | `validation` | Validation error, include `fields` |
| 429 | `rate_limited` | Rate limit hit. Include `Retry-After` header. |
| 500 | `server_error` | Generic 500 — log the actual trace server-side, do NOT leak it to the client |
| 503 | `unavailable` | Maintenance mode |

### 6.5 ID format

UUID v4 lowercase: `[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}`.

Validate format on every endpoint that accepts an ID. Invalid format → 422.

### 6.6 Time format

ISO 8601 UTC with milliseconds and trailing `Z`:

```
2026-04-15T10:30:00.000Z
```

When deserializing client payloads, accept the milliseconds form. When serializing for client responses, **always** emit with milliseconds and `Z`. The client compares timestamps as strings — lexical order on this format equals chronological order, so do NOT vary the format (e.g. omit milliseconds for whole-second times — emit `.000`).

### 6.7 Boolean serialization

Always literal `true` / `false` in JSON. Never `1` / `0`. Never `"true"` / `"false"`.

### 6.8 Null vs missing fields

Always emit explicit `null` for nullable fields with no value. Never omit a key. The client's TypeScript types expect every documented field to be present.

### 6.9 CORS

Mobile clients don't trigger CORS, but a future web client might. Configure CORS to allow:

- `Authorization`, `Content-Type`, `Accept` headers
- `POST`, `GET`, `PUT`, `DELETE`, `PATCH` methods
- Origins: `https://questing.app` and configured dev origins

### 6.10 Rate limiting

Apply Laravel's default rate limiting:

- Unauthenticated endpoints: 60 requests/minute per IP
- Authenticated endpoints: 600 requests/minute per user

Sync endpoints (`/sync/push`, `/sync/pull`) get a higher allowance: 60 requests/minute per user. The client retries with exponential backoff on 429 — return `Retry-After: 60` in the response header.

---

## 7. Auth endpoints

### 7.1 `POST /auth/password/register`

**Authentication**: unauthenticated.

**Request body**:

```json
{
  "email": "user@example.com",
  "password": "at-least-8-chars",
  "deviceId": "abc-uuid-v4"
}
```

| Field | Type | Constraints |
|---|---|---|
| `email` | string | Required. Valid email. Unique across users. |
| `password` | string | Required. Min 8 chars. No max. |
| `deviceId` | string | Required. UUID v4. Informational only. |

**Response 200**:

```json
{
  "user": {
    "id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "email": "user@example.com",
    "createdAt": "2026-04-15T10:30:00.000Z"
  },
  "token": "1|abc...long-opaque-string"
}
```

**Side effects**:
- Create `users` row with `password = bcrypt(payload.password)`, `email_verified_at = null`.
- Create a Sanctum token via `$user->createToken('quest-mobile')`.
- Send an email verification email (out of scope for this spec but expected behavior).

**Errors**:
- 422 `validation` (`email` invalid, `password` too short, `deviceId` not UUID).
- 409 `email_taken` (`fields.email: ['already in use']`).

### 7.2 `POST /auth/password/login`

**Authentication**: unauthenticated.

**Request body**:

```json
{
  "email": "user@example.com",
  "password": "the-password",
  "deviceId": "abc-uuid-v4"
}
```

**Response 200**: same shape as register.

**Errors**:
- 401 `invalid_credentials` (wrong email or wrong password — do NOT differentiate; same error for both to avoid email enumeration).
- 422 `validation`.

### 7.3 `POST /auth/apple`

**Authentication**: unauthenticated.

**Request body**:

```json
{
  "identityToken": "eyJ...JWT-from-Sign-in-with-Apple",
  "authorizationCode": "c123...",
  "fullName": { "givenName": "Jane", "familyName": "Doe" },
  "email": "user@example.com",
  "deviceId": "abc-uuid-v4"
}
```

| Field | Type | Constraints |
|---|---|---|
| `identityToken` | string | Required. The JWT from Apple. Server validates signature against Apple's JWKS. |
| `authorizationCode` | string | Required. Used for token exchange (optional — can rely on identityToken alone). |
| `fullName` | object | Optional. Apple only provides this on first sign-in. |
| `email` | string | Optional. Apple may provide a real email, a relay address, or nothing. |
| `deviceId` | string | Required. |

**Server behavior**:

1. Validate the `identityToken` (signature, expiry, audience = your bundle ID).
2. Extract the stable `sub` claim. Look up `users WHERE apple_id = sub`.
3. If found: that's the user.
4. If not found AND `email` is set AND a user with that `email` exists: link the existing account by setting `apple_id = sub`.
5. If not found AND no link: create a new `users` row with `apple_id = sub`, `email = <provided or null>`, `password = null`, `email_verified_at = now()` (Apple-verified emails are trusted).
6. Issue a Sanctum token.

**Response 200**: same shape as register.

**Errors**:
- 401 `invalid_apple_token` (signature, expiry, audience mismatch).
- 422 `validation`.

### 7.4 `POST /auth/google`

**Authentication**: unauthenticated.

**Request body**:

```json
{
  "idToken": "eyJ...Google-ID-token",
  "deviceId": "abc-uuid-v4"
}
```

| Field | Type | Constraints |
|---|---|---|
| `idToken` | string | Required. The Google ID token (JWT). |
| `deviceId` | string | Required. |

**Server behavior**:

1. Validate the `idToken` against Google's JWKS, check `aud` matches your Google OAuth Client ID.
2. Extract `sub`. Same fallthrough logic as Apple (link by email if present, otherwise create).
3. Trust `email` and `email_verified` from the Google token.

**Response 200**: same shape as register.

**Errors**: 401 `invalid_google_token`, 422.

### 7.5 `POST /auth/logout`

**Authentication**: required.

**Request body**: empty.

**Response 204**: empty.

**Side effects**: revoke the currently-used Sanctum token (`request()->user()->currentAccessToken()->delete()`). Do NOT revoke other tokens (other devices keep their sessions).

**Errors**: 401 if no valid token.

### 7.6 `GET /me`

**Authentication**: required.

**Request body**: none.

**Response 200**:

```json
{
  "user": {
    "id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "email": "user@example.com",
    "createdAt": "2026-04-15T10:30:00.000Z"
  }
}
```

**Errors**: 401.

### 7.7 `DELETE /me`

**Authentication**: required.

**Request body**: empty.

**Response 204**: empty.

**Side effects** (in this order, in a single transaction where possible):

1. Delete all `entry_audio` rows belonging to the user's entries.
2. Delete all `entry_attachments` rows belonging to the user's entries.
3. Delete all `entry_quests` rows belonging to the user's entries.
4. Delete all `entry_characters` rows belonging to the user's entries.
5. Delete all `entries` rows belonging to the user.
6. Delete all `quests` rows belonging to the user.
7. Delete all `characters` rows belonging to the user.
8. Delete all binaries from S3 (audio, attachments, character photos) belonging to the user. Do this AFTER the DB transaction commits so a failure to delete files doesn't roll back the user deletion.
9. Revoke all Sanctum tokens for the user.
10. Delete the `users` row itself.

Apple requires the user to be completely deleted, not soft-deleted. Hard-delete everything.

**Errors**: 401.

### 7.8 Auth-related future endpoints (V1+)

For V1, do NOT implement:

- Password reset (`POST /auth/password/forgot`, `POST /auth/password/reset`) — the email infrastructure for it is the same as for verification, but the V1 client doesn't ship the "forgot password" UI yet. Spec ready, defer implementation.
- Email change (`POST /auth/email`) — same.
- Refresh token — Sanctum tokens don't expire by default. If you set them to expire, add this. Otherwise skip.

---

## 8. Sync endpoints

### 8.1 Overview

Two endpoints, both POST, both authenticated. The client calls them in this order on every sync: push first, then pull. The client never combines them into one call.

### 8.2 `POST /sync/push`

**Authentication**: required.

**Request body**:

```json
{
  "deviceId": "abc-uuid-v4",
  "changes": [
    {
      "entityType": "entry",
      "entityId": "<uuid>",
      "operation": "create",
      "data": { /* see §8.4 per entity */ }
    },
    {
      "entityType": "entry_quest",
      "entityId": "<entryId>:<questId>",
      "operation": "create",
      "data": { "entryId": "<uuid>", "questId": "<uuid>" }
    }
    // ...up to ~500 changes in a single request (no hard limit but client batches)
  ]
}
```

| Field | Type | Notes |
|---|---|---|
| `deviceId` | string | Informational. |
| `changes` | array | Ordered by the client's queue insertion order. Server processes in array order. |

**Per-change fields**:

| Field | Type | Values |
|---|---|---|
| `entityType` | string | `entry`, `quest`, `character`, `entry_quest`, `entry_character`, `entry_attachment`, `entry_audio` |
| `entityId` | string | UUID for entities, `"<entryId>:<questId>"` or `"<entryId>:<characterId>"` for junctions |
| `operation` | string | `create`, `update`, `delete`. The client's queue dedup may collapse these — see §8.3. |
| `data` | object | The full entity payload — see §8.4 |

**Response 200**:

```json
{
  "confirmed": ["entityId1", "entityId2", ...],
  "conflicts": [
    {
      "entityType": "entry",
      "entityId": "<uuid>",
      "serverVersion": { /* the server's current version, full payload */ }
    }
  ]
}
```

**`confirmed`**: every entityId that the server successfully accepted (created, updated, or marked deleted). Junction IDs use the `<entryId>:<questId>` composite form.

**`conflicts`**: entities where the server's existing `updatedAt` is **strictly greater** than the client's pushed `updatedAt`. Server kept its version; client must adopt it. Only entries / quests / characters / entry_attachments / entry_audio can conflict — junctions never conflict (they exist or they don't).

**Error response shape on conflict**: do NOT return 409. Return 200 with the conflicts in the body. The client treats conflicts as expected outcomes, not errors.

### 8.3 Server behavior on push

Process each change in array order. For each:

1. **Verify ownership**: the entity must belong (directly or transitively) to the authenticated user. If not, **silently skip** (do NOT 403 — the client may be replaying stale state; isolate cross-user contamination at the server). Do NOT include the skipped id in `confirmed`.

2. **For content entities (`entry`, `quest`, `character`, `entry_attachment`, `entry_audio`)**:
   - Look up the existing row by `id`.
   - If the existing row's `updated_at` is **strictly greater** than the incoming `data.updatedAt`: this is a conflict. Append to `conflicts` with the server's full current payload. Do NOT add to `confirmed`. Continue to next change.
   - Otherwise: upsert. Replace every column with the incoming data. **Important field handling**:
     - `uri` for attachments / audio / character photo: always stored as `''` regardless of incoming value (§4.10).
     - `is_deleted`: copy from `data.isDeleted`.
     - `synced_at`: not a column on the server — ignore.
     - All other fields: copy verbatim.
   - On successful upsert, add to `confirmed`.
   - Treat `operation` as **advisory**: a `create` for an existing id is just an upsert; a `delete` is an upsert with `is_deleted = true` (the `data` payload already has `isDeleted: true`). The server does not branch on `operation` for content entities.

3. **For junctions (`entry_quest`, `entry_character`)**:
   - Verify both referenced entities exist and belong to this user. If either is missing or foreign, silently skip.
   - If `operation === 'delete'`: `DELETE FROM <junction> WHERE entry_id = ? AND quest_id = ?`. Always succeeds (idempotent — no row → no error). Add `entityId` to `confirmed`.
   - Otherwise: `INSERT INTO <junction> ON CONFLICT DO NOTHING` (PostgreSQL) or `INSERT IGNORE` (MySQL). Idempotent — pre-existing row → no error. Add to `confirmed`.

4. **For entities where the client sends `operation: 'delete'`**: the `data` payload has `isDeleted: true` already. Treat it as a soft-delete upsert (same as point 2). The server does not need to special-case this.

5. **For entities the server has never seen, where `operation: 'delete'`**: the client's queue dedup may have collapsed a `create + delete` into a single `delete`. The server should `update_or_create` — the entity gets created with `is_deleted = true` and will be purged by the retention job. Do NOT error.

6. **Foreign key violations**: if a junction or attachment references an entry that doesn't exist yet (push order issue), defer or retry. Simplest implementation: process content entities first, then junctions and binaries. Easiest to do this by sorting the changes server-side by entity type priority before processing.

### 8.4 Entity payload shapes (in `data`)

These are the **exact** shapes the client sends in push requests and expects in pull responses. Field names are camelCase. Types are TypeScript-style.

#### 8.4.1 Entry

```json
{
  "id": "string (UUID)",
  "title": "string",
  "html": "string",
  "mood": "string | null (one of: empty, sad, stressed, angry, anxious, calm, grateful, joyful)",
  "latitude": "number | null",
  "longitude": "number | null",
  "entryDate": "string | null (ISO 8601 UTC)",
  "isDeleted": "boolean",
  "createdAt": "string (ISO 8601 UTC)",
  "updatedAt": "string (ISO 8601 UTC)",
  "syncedAt": "string | null"
}
```

The client sends `syncedAt: null` on push. Server ignores it. Server emits `syncedAt: null` on pull (the client overrides locally).

#### 8.4.2 Quest

```json
{
  "id": "string (UUID)",
  "type": "string (one of: main, side, daily)",
  "title": "string",
  "description": "string",
  "status": "string (one of: active, completed, archived)",
  "color": "string | null",
  "icon": "string | null",
  "startedAt": "string | null (ISO 8601)",
  "completedAt": "string | null (ISO 8601)",
  "isDeleted": "boolean",
  "createdAt": "string (ISO 8601)",
  "updatedAt": "string (ISO 8601)",
  "syncedAt": "string | null"
}
```

#### 8.4.3 Character

```json
{
  "id": "string (UUID)",
  "name": "string",
  "relationship": "string | null",
  "note": "string",
  "photoUri": "string (always emit '' on pull; server stores '' regardless of push)",
  "remotePhotoUri": "string | null (the cloud URL or null)",
  "color": "string | null",
  "isDeleted": "boolean",
  "createdAt": "string (ISO 8601)",
  "updatedAt": "string (ISO 8601)",
  "syncedAt": "string | null"
}
```

Note: the current client's `Character` type does not have `remotePhotoUri`. When the client adds it, the server's payload already provides it. For V1: the server still emits `remotePhotoUri` (null until the client adds upload triggers).

#### 8.4.4 EntryQuestLink

```json
{
  "entryId": "string (UUID)",
  "questId": "string (UUID)"
}
```

#### 8.4.5 EntryCharacterLink

```json
{
  "entryId": "string (UUID)",
  "characterId": "string (UUID)"
}
```

#### 8.4.6 Attachment

```json
{
  "id": "string (UUID)",
  "entryId": "string (UUID)",
  "uri": "string (always emit '' on pull; server stores '' regardless of push)",
  "remoteUri": "string | null",
  "width": "number (positive integer)",
  "height": "number (positive integer)",
  "isDeleted": "boolean",
  "createdAt": "string (ISO 8601)",
  "updatedAt": "string (ISO 8601)",
  "syncedAt": "string | null"
}
```

#### 8.4.7 AudioNote

```json
{
  "id": "string (UUID)",
  "entryId": "string (UUID)",
  "uri": "string (always emit '' on pull; server stores '' regardless of push)",
  "remoteUri": "string | null",
  "durationMs": "number (positive integer)",
  "waveform": "number[] (array of floats 0..1, max 200 elements)",
  "isDeleted": "boolean",
  "createdAt": "string (ISO 8601)",
  "updatedAt": "string (ISO 8601)",
  "syncedAt": "string | null"
}
```

### 8.5 `POST /sync/pull`

**Authentication**: required.

**Request body**:

```json
{
  "deviceId": "abc-uuid-v4",
  "lastPullTimestamp": "2026-04-15T10:30:00.000Z"
}
```

| Field | Type | Notes |
|---|---|---|
| `deviceId` | string | Informational. |
| `lastPullTimestamp` | string \| null | `null` means "give me everything" (first pull on this device). |

**Response 200**:

```json
{
  "changes": [
    {
      "entityType": "entry",
      "operation": "upsert",
      "data": { /* see §8.4 */ }
    }
    // ...
  ],
  "serverTimestamp": "2026-04-15T10:35:00.000Z"
}
```

**`changes`**: every row across all entity tables belonging to this user where `updated_at > lastPullTimestamp` (or all rows if `lastPullTimestamp` is null). Includes soft-deleted rows (with `isDeleted: true` in the payload).

**`serverTimestamp`**: the server's "now" at the moment the query started. The client stores this as its new `lastPullTimestamp` for the next pull.

### 8.6 Server behavior on pull

For each table belonging to this user:

1. Select all rows where (`lastPullTimestamp == null` OR `updated_at > lastPullTimestamp`).
2. For content tables (`entries`, `quests`, `characters`, `entry_attachments`, `entry_audio`): emit each row as `{ entityType: <type>, operation: 'upsert', data: <row payload> }`. **Never emit `operation: 'delete'` for content** — the soft-delete is encoded in the `data.isDeleted` field. The client treats `operation: 'delete'` as a no-op for content (it's reserved for junctions).
3. For junction tables (`entry_quests`, `entry_characters`):
   - Existing rows → `{ entityType, operation: 'upsert', data: <link> }`.
   - To communicate a junction removal, the server needs a tombstone trail. Two options — pick one:
     - **(A) Simple, recommended for V1**: keep a per-user junction history table (`entry_quest_tombstones`, `entry_character_tombstones`) with `entry_id`, `quest_id` (or `character_id`), `deleted_at`. On junction delete, insert a tombstone row. On pull, emit `{ operation: 'delete' }` for every tombstone with `deleted_at > lastPullTimestamp`. Tombstones are purged after the retention window (90 days — junctions don't have a UI for restoration so we just need enough overlap to cover offline devices).
     - **(B) Complex, V1+**: track all junction lifecycle via a single table with `is_deleted` flag instead of hard-delete. Pull emits both upsert and delete based on the flag.
   - Go with (A). Simpler, performant, easy to reason about.

### 8.7 Pull ordering for FK safety

The client's `applyPullChange` doesn't reorder — it processes the array in the order received. To prevent FK violations on the client (an attachment referencing an entry that hasn't been inserted yet), the server **must** emit changes in this order:

1. `quest` rows
2. `character` rows
3. `entry` rows
4. `entry_attachment` rows
5. `entry_audio` rows
6. `entry_quest` rows (upserts)
7. `entry_quest` deletions (tombstones)
8. `entry_character` rows (upserts)
9. `entry_character` deletions (tombstones)

Within each group, ordering is irrelevant.

### 8.8 Pagination of pull (V1+)

For V1, return all matching rows in one response. The expected upper bound for normal users is in the thousands, which is fine in a single response.

If a future user has >10k entries unsynced (cross-device migration after years of local-only), revisit with cursor-based pagination. Spec: add `?cursor=<id>` query param and a `nextCursor` field in the response. **Defer this until needed**.

### 8.9 Sync invariants the server must preserve

These are NOT optional. Violating any of these breaks the client's correctness.

1. **`updatedAt` round-trip**: when the server upserts a row, the row's `updated_at` must equal exactly the incoming `data.updatedAt`. Do NOT auto-set `updated_at = now()` in your update query. Use `$model->forceFill($data)->save()` or equivalent that preserves the timestamp. The client compares timestamps as strings — a server-side override would break LWW.

2. **Conflict response includes the full server payload**: not just the ID. The client uses it to overwrite local state immediately.

3. **Idempotence on entity IDs**: pushing the same change twice must produce the same end state. The second push gets `confirmed` with the same id (the server's row already matches).

4. **Junction idempotence**: `link` then `link` then `link` is one row. `unlink` on a non-existent row is a no-op.

5. **Cross-user isolation**: no endpoint exposes another user's data. If `users.B` pushes a change referencing `users.A`'s entry, silently skip — do not 404, do not 403.

6. **Order preservation in `confirmed`**: the order of IDs in `confirmed` mirrors the order they appeared in the request. Not strictly required by the client but useful for debugging.

7. **Never emit `operation: 'delete'` for content entities on pull**: only for junctions.

8. **Never emit a row in pull whose `updated_at <= lastPullTimestamp`**: even if `is_deleted` flipped. Use `updated_at`, not `created_at` or `deleted_at`.

---

## 9. Binary upload endpoints

### 9.1 The flow

For each binary type (attachment, audio, character photo), the flow is:

1. Client pushes the entity metadata via `/sync/push` first. The server stores the row with `remote_uri = null` (or `remote_photo_uri = null`). The entity exists.
2. Client uploads the binary to `POST /uploads/<kind>/<entity_id>`.
3. Server validates: entity exists, belongs to this user, no existing `remote_uri`. Server stores the binary in S3. Server updates the row: `remote_uri = <url>`, `updated_at = now()`.
4. Server returns `{ remoteUri: <url> }` to the client.
5. Other devices pulling next will receive the entity with `remoteUri` populated, and can fetch the binary at that URL.

### 9.2 Endpoints

#### `POST /uploads/attachments/{attachment_id}`

**Authentication**: required.

**Path param**: `attachment_id` — UUID.

**Request**: `multipart/form-data` with field `file` containing the binary.

**Content constraints**:
- MIME types accepted: `image/jpeg`, `image/png`, `image/heic`, `image/heif`, `image/webp`, `image/gif`.
- Max size: 25 MB.
- The server may re-encode HEIC/HEIF to JPEG to ensure web compatibility. If it does, update `width`/`height` accordingly. Document the choice.

**Response 200**:

```json
{
  "remoteUri": "https://cdn.questing.app/attachments/<attachment_id>.jpg"
}
```

**Side effects**:
- Upload binary to S3 at a path like `attachments/<user_id>/<attachment_id>.<ext>`.
- Update `entry_attachments` row: `remote_uri = <url>`, `updated_at = now()`.
- The `updated_at` bump means other devices will receive this change on their next pull (with the new `remoteUri`).

**Errors**:
- 401 `unauthenticated`.
- 404 `not_found` (attachment doesn't exist or belongs to another user).
- 409 `already_uploaded` (`remote_uri` already set — refuse overwrite. The client should be deleting + re-creating to replace binaries).
- 413 `payload_too_large`.
- 415 `unsupported_media_type`.
- 422 `validation`.

#### `POST /uploads/audio/{audio_id}`

Same flow.

**Content constraints**:
- MIME types: `audio/mp4`, `audio/m4a`, `audio/aac`, `audio/mpeg`, `audio/wav`. M4A is the iOS recorder's native output.
- Max size: 50 MB (a couple minutes of voice memo).
- No re-encoding required.

**Response 200**:

```json
{
  "remoteUri": "https://cdn.questing.app/audio/<audio_id>.m4a"
}
```

#### `POST /uploads/character-photos/{character_id}`

Same flow as attachments. **Not used by the V1 client** but spec it for future use.

### 9.3 URL format

The `remoteUri` returned should be:

- A long-lived public CDN URL if the storage is configured for public access (R2 with public bucket, CloudFront in front of S3). Simpler client logic.
- A presigned URL if the storage requires per-request authentication (private S3). The client doesn't refresh — emit URLs with multi-year expiry, or use a proxy endpoint that issues short-lived signed URLs.

**Recommendation for V1**: use a public CDN URL. Quest content is per-user but the URL contains a UUID so there's no enumeration risk. The user's privacy is protected because nobody else has the URL. (For V2, consider proxied delivery for stricter access control.)

### 9.4 Deleting binaries

When an entity is hard-purged (the 30-day retention job runs), delete the binary from S3 as well. The Laravel queue / scheduled command pattern works well here.

Do NOT delete on soft-delete — the user may restore from trash within 30 days.

### 9.5 Binary download

There is no dedicated download endpoint. The client uses the `remoteUri` directly with a plain GET request (no `Authorization` header — the URL is the credential).

---

## 10. Account deletion (already specified in §7.7)

Brief reminder of the contract:

- `DELETE /me` returns 204.
- Server hard-deletes everything: rows, binaries, tokens.
- Apple requires this be self-service in the app. Implementation note: provide it via a confirmation flow in Settings on the client. Server just exposes the endpoint.

---

## 11. Server-side rules (cross-cutting)

### 11.1 Last-write-wins (LWW)

- On push: if the existing server row's `updated_at` is strictly greater than the incoming `updatedAt`, server wins → conflict.
- On equal timestamps: incoming wins (it's the same logical state anyway — overwriting is idempotent).
- For junctions: no LWW. They exist or they don't.

### 11.2 Idempotence

Every write endpoint must be idempotent. Same request twice = same end state. The client retries on network errors — non-idempotent endpoints would corrupt data.

Tactic: always upsert by ID. Never auto-increment, never generate IDs server-side for client-originated entities.

### 11.3 Soft-delete encoding

- Server stores `is_deleted = true` for soft-deleted rows.
- Server keeps them in pull responses (with `isDeleted: true` in payload) so other devices learn about the deletion.
- Server purges soft-deleted rows after 30 days (matches client retention window in `CLAUDE.md`).
- The client treats `operation: 'delete'` in pull as a **no-op** for content entities — the soft-delete must be encoded as `operation: 'upsert', data: { ..., isDeleted: true }`. Junctions are different — they use `operation: 'delete'` and rely on tombstones (§8.6).

### 11.4 The "delete-without-create" edge case

The client's queue dedup may collapse `create + delete` into a single `delete` push for an entity the server has never seen. Handle gracefully:

- For content entities: upsert anyway. The row gets created with `is_deleted = true`. The retention job purges it within 30 days.
- For junctions: the DELETE has no row to delete. Idempotent no-op. Return success.

Do NOT error.

### 11.5 Retention purge job

A daily scheduled command (`php artisan schedule:run` via cron):

1. For each soft-deleted content entity (`entries`, `quests`, `characters`, `entry_attachments`, `entry_audio`): if `updated_at < now() - 30 days`, hard-delete the row.
2. CASCADE deletes handle child rows (junctions, attachments under entries).
3. Delete associated binaries from S3.
4. For junction tombstones (§8.6): if `deleted_at < now() - 90 days`, hard-delete.

Log purge stats for observability.

### 11.6 Cross-user isolation

Every query MUST filter by `user_id`. Use a global Eloquent scope on the user-owned models, OR a middleware that injects `$query->where('user_id', auth()->id())` automatically.

When a request references an entity by ID, verify ownership before reading or writing. If foreign, return 404 (not 403 — don't leak existence).

Sync push silently skips foreign-owned entities (§8.3) rather than 404'ing, because a stale client may push state from an old account.

### 11.7 Time clock skew

The client sends `updatedAt` from its local clock. The server doesn't second-guess it. If the user's phone clock is wrong, the resulting LWW is also wrong, but that's an acceptable failure mode (and rare).

Exception: the `serverTimestamp` returned in pull responses is the **server's** clock. The client trusts the server's clock for the `lastPullTimestamp` cursor. This is important: it means the server's clock advances monotonically (use UTC, NTP-synced).

### 11.8 Concurrency on the server

Wrap each push request's processing in a DB transaction. If two devices push simultaneously, normal row-level locking handles it. Sanctum doesn't serialize requests per user — that's fine, the LWW comparison handles concurrent writes naturally.

---

## 12. Client contract summary

Quick reference for fields and values the client emits / expects. If you find yourself uncertain, default to the field name, type, and casing here.

### 12.1 Mood enum

`empty`, `sad`, `stressed`, `angry`, `anxious`, `calm`, `grateful`, `joyful`. Other values → reject as invalid.

### 12.2 Quest enums

- `type`: `main`, `side`, `daily`.
- `status`: `active`, `completed`, `archived`.

### 12.3 Entity types in sync

- `entry`, `quest`, `character`, `entry_quest`, `entry_character`, `entry_attachment`, `entry_audio`.

### 12.4 Operations in sync push

- `create`, `update`, `delete`. Advisory only — server treats all as upsert for content entities, and `create`/`delete` for junctions.

### 12.5 Operations in sync pull

- For content: only `upsert`. **Never `delete`** — the client ignores `delete` on content.
- For junctions: `upsert` or `delete`.

### 12.6 Conflict entity types

Only content entities can conflict: `entry`, `quest`, `character`, `entry_attachment`, `entry_audio`. Junctions never conflict.

### 12.7 Required vs optional fields

Every documented field MUST be present in every emitted payload. Null is valid; missing is not. The client's deserializer is strict.

---

## 13. Acceptance criteria (test scenarios)

When all of the following pass, the backend is V1-shippable. These are end-to-end scenarios — write integration tests that exercise them through the HTTP layer (not unit tests of the Laravel models).

### 13.1 Auth

| # | Scenario | Expected |
|---|---|---|
| A1 | Register with valid email + 8+ char password | 200, returns `{ user, token }`, user row created with bcrypt password |
| A2 | Register with same email twice | First: 200. Second: 409 `email_taken`. |
| A3 | Login with valid creds | 200, `{ user, token }` |
| A4 | Login with wrong password | 401 `invalid_credentials` |
| A5 | Login with nonexistent email | 401 `invalid_credentials` (same error — no enumeration) |
| A6 | Apple sign-in with valid token, no prior user | 200, creates user with `apple_id` set, no password |
| A7 | Apple sign-in with token whose email matches existing email/password user | 200, links: existing user gets `apple_id` set |
| A8 | Apple sign-in with invalid signature | 401 `invalid_apple_token` |
| A9 | Google sign-in equivalents (mirror A6-A8) | |
| A10 | `GET /me` with valid token | 200, returns the user |
| A11 | `GET /me` with revoked token | 401 `unauthenticated` |
| A12 | `POST /auth/logout` | 204, the token used is revoked, other tokens of same user remain valid |
| A13 | `DELETE /me` with valid token | 204, user + all their data + all binaries gone, all their tokens revoked |

### 13.2 Sync — basics

| # | Scenario | Expected |
|---|---|---|
| S1 | Push 1 entry to empty server | 200, `confirmed: [id]`, conflicts: []. Server has the row. |
| S2 | Push then pull from same device, no changes | First push: 200. Second pull: 200 with `changes: []`. |
| S3 | Push then pull from a fresh "device B" (different `deviceId`, same user) | Pull returns the entry pushed by device A |
| S4 | Push the same entry twice | Second push: 200, `confirmed: [id]`. Idempotent. |
| S5 | Push an entry with an older `updatedAt` than server's | 200, that entity in `conflicts` with `serverVersion` |
| S6 | Push a soft-deleted entry (`isDeleted: true`, `operation: 'delete'`) | 200, server marks row deleted. Pull from device B sees the entity with `isDeleted: true`. |

### 13.3 Sync — junctions

| # | Scenario | Expected |
|---|---|---|
| J1 | Push `entry_quest` create, then push another `entry_quest` create with same ids | Both succeed (idempotent). Server has one row. |
| J2 | Push `entry_quest` delete on a non-existent junction | 200, `confirmed: [<entryId>:<questId>]`. No error. |
| J3 | Push `entry_quest` create + delete (in same request, different changes — should not happen with dedup but client may send both if dedup is bypassed) | Both confirmed. Server ends in deleted state (or doesn't have the row). |
| J4 | Device A pushes entry + quest + entry_quest. Device B pulls. | B receives all 3. The junction is visible in B's `entry_quests`. |
| J5 | Device A unlinks a previously-synced junction. Device B pulls. | B's pull includes a `{ entityType: 'entry_quest', operation: 'delete', data: { ... } }` change. After applying, B doesn't have the junction anymore. |

### 13.4 Sync — binaries

| # | Scenario | Expected |
|---|---|---|
| B1 | Push an `entry_attachment` payload (metadata). | 200, `confirmed`. Server row has `remote_uri = null`, `uri = ''`. |
| B2 | Push an `entry_attachment` payload with a non-empty `uri` (client-side bug, should be empty but defensively the server must strip it). | Server still stores `uri = ''`. |
| B3 | Upload binary to `/uploads/attachments/{id}` with valid JPEG. | 200, `remoteUri` returned. S3 has the file. Row updated. |
| B4 | Upload binary to a non-existent attachment id. | 404. |
| B5 | Upload binary twice (re-upload). | First: 200. Second: 409 `already_uploaded`. |
| B6 | Upload an unsupported MIME type (PDF). | 415 `unsupported_media_type`. |
| B7 | Device A pushes attachment metadata + uploads binary. Device B pulls. | B receives the attachment payload with `uri = ''`, `remoteUri = <url>`. B can GET the binary at `remoteUri`. |
| B8 | Soft-delete attachment on A, push. B pulls. | B's pull payload has `isDeleted: true`. B's getAttachments filters it out. |
| B9 | Audio equivalents (mirror B1-B8) | |

### 13.5 Sync — encryption

| # | Scenario | Expected |
|---|---|---|
| E1 | Push an entry with `title: "Secret thoughts"` and `html: "<p>Sensitive content</p>"`. | DB-level inspection: `title` and `html` columns contain ciphertext (Laravel encrypted format starts with `eyJ...`). |
| E2 | Pull after the push. | Returns the plaintext title and html. (Laravel's `encrypted` cast auto-decrypts on read.) |
| E3 | Mood, latitude, longitude in the DB are NOT encrypted. | DB-level inspection: plaintext. |

### 13.6 Sync — LWW + conflict

| # | Scenario | Expected |
|---|---|---|
| L1 | Device A creates entry at T1, pushes. Device B pulls → has T1. Device B updates to T2 > T1, pushes. Device A pulls → has T2. | A's local copy ends at T2. |
| L2 | Device A creates entry at T1, pushes (server now at T1). Device A then updates locally to T0 (T0 < T1 — clock skew or malicious). | Push response: that entry in `conflicts` with `serverVersion = T1 payload`. Server stays at T1. |
| L3 | Device A pushes entry at T1, device B has the entry at T2 > T1 from a prior pull. Device B pushes its T2 version. | A's next pull receives T2. |

### 13.7 Cross-user isolation

| # | Scenario | Expected |
|---|---|---|
| X1 | User A's token pushes an entry with `id = X`. User B's token tries to push an update for `id = X`. | B's push silently skips (X is not in B's `confirmed`). A's data unchanged. |
| X2 | User B's token tries to fetch `GET /me` and gets B's data, never A's. | Always B. |
| X3 | User B's token uploads to `/uploads/attachments/<A's attachment id>`. | 404 `not_found`. |
| X4 | User A deletes their account. User B is unaffected. | A's data + binaries gone. B intact. |

### 13.8 Sync — retention

| # | Scenario | Expected |
|---|---|---|
| R1 | Push a soft-deleted entry with `updated_at = 31 days ago`. Run the retention job. | Entry row hard-deleted. Associated attachments + audio binaries deleted from S3. |
| R2 | Push a soft-deleted entry with `updated_at = 29 days ago`. Run the retention job. | Entry row still present. |
| R3 | Junction tombstone with `deleted_at = 91 days ago`. Run the retention job. | Tombstone hard-deleted. |

### 13.9 Cross-account migration (client-orchestrated)

These exercise the client's `auth-sync-bridge`. The backend's role: provide the auth + sync endpoints the bridge calls.

| # | Scenario | Expected |
|---|---|---|
| M1 | New user signs up on a device with no prior account, has 10 local entries in queue. | After register: client calls `/sync/push` with the 10 entries. Server: all 10 accepted. |
| M2 | Same user logs out, logs back in (same account, same device). | Server: nothing in queue, `/sync/pull` no-op. |
| M3 | Same user logs out, a different user logs in on same device. Client prompts merge/wipe/cancel. | If client chose merge: local data flows up into the new account. Server: receives the items, attributes them to the new user's `user_id`. |

### 13.10 Idempotence and retry

| # | Scenario | Expected |
|---|---|---|
| I1 | Push 100 entries. Network drops mid-response. Client retries same push. | Server: idempotent. Final state: 100 entries on server. No duplicates. |
| I2 | Client sends a malformed JSON body. | 400 `bad_request`. No partial state changes. |
| I3 | Client hits `/sync/push` 100 times/minute. | After 60 requests, 429 `rate_limited` with `Retry-After: 60`. |

---

## 14. Out of scope for V1 (do NOT implement)

- E2E encryption (per-user keys, key recovery flows)
- Real-time sync via websockets / SSE
- Server-side search endpoint (full-text search on encrypted content is non-trivial)
- AI features (summaries, pattern detection, "ask Quest" queries)
- Pagination of pull (defer until needed)
- Refresh tokens / sliding token expiry (Sanctum default: non-expiring)
- Password reset / email change endpoints (defer until client UI exists)
- Multi-account on one user (linking multiple email accounts)
- Server-side compression of binaries beyond standard image re-encoding
- Audit log of sync operations
- Admin endpoints
- Web client (CORS prepared but no dedicated endpoints)
- Server-side rate limiting per binary (covered by overall rate limiting)
- Webhooks
- Multi-tenancy / orgs

---

## 15. Implementation checklist

A linear order in which to build. Each step has acceptance tests under §13 referenced by code.

1. **Project setup**: Laravel 11+, PostgreSQL, Sanctum, Socialite (+ Apple provider). Configure `APP_KEY`, S3 driver (or local for dev). [no tests yet]
2. **Database migrations**: every table from §4. Each migration is one logical change. [A test that hits `\Schema::hasTable` for every required table.]
3. **Models + casts + relationships**: `User`, `Entry`, `Quest`, `Character`, `Attachment`, `AudioNote` + the two junction pivot configurations. Apply `encrypted` casts per §5.1. Test E1, E3.
4. **Auth: password register + login + logout**: A1–A5, A11, A12.
5. **Auth: `GET /me`, `DELETE /me`**: A10, A13.
6. **Auth: Apple + Google**: A6–A9.
7. **Sync push (no junctions, no binaries yet)**: S1, S2, S4, S5, S6.
8. **Sync push: junctions**: J1, J2, J3.
9. **Sync push: binaries (metadata only)**: B1, B2.
10. **Sync pull**: S3, J4, J5, B7, B8, E2.
11. **Conflicts**: L1, L2, L3.
12. **Cross-user isolation middleware / scopes**: X1–X4.
13. **Binary upload endpoints**: B3–B6, B9.
14. **Retention purge command**: R1–R3.
15. **Cross-account migration end-to-end**: M1–M3.
16. **Rate limiting + error handling polish**: I2, I3.

---

## 16. Operational notes

- **Logging**: log every sync push and pull with `user_id`, `device_id`, change counts. Useful for support tickets.
- **Monitoring**: track 4xx and 5xx rates per endpoint. Spike on `/sync/push` = client bug or backend issue.
- **Binary storage growth**: each user can theoretically push GBs of binaries. Set a per-user quota (e.g. 5 GB free, paid tier for more) — but that's product policy, not V1.
- **Backups**: encrypted columns are encrypted at rest in the DB. The backup of the DB is also encrypted (Laravel's `APP_KEY` is needed to decrypt). Back up `APP_KEY` separately, in a different store than the DB backup, so a single leak doesn't compromise both.
- **Migration of `APP_KEY`**: rotation is out of scope. If you ever rotate, you'll need a re-encryption job. Plan it before you need it.

---

## 17. When you're done

You're done with V1 when:

- Every endpoint in §7, §8, §9, §10 is implemented and tested.
- Every scenario in §13 passes.
- The client (this repo, `src/data/api/http.ts` + `src/data/sync-engine.ts` + `src/data/auth-sync-bridge.ts`) can talk to your backend end-to-end with no client-side changes beyond:
  - filling in the four `signInWith*` and `signUpWithPassword` method stubs in `src/hooks/use-auth.tsx`
  - wiring `useSync(realApiClient)` back into `src/hooks/use-entry-save.ts`
  - building the account / login / logout UI

If any acceptance test fails, you're not done. If you needed to change the client to make a test pass, you misread this document — re-read it and reconsider.

Good luck. Be exhaustive, be conservative, prefer "obviously correct" over "clever".
