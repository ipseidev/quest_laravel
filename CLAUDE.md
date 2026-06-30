# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project: Quest backend

Mobile-only, Sanctum-authenticated JSON API for the Quest journal app. The frontend (separate Expo/React Native repo) sends offline-first edits through a sync push/pull protocol; the backend persists them, mediates conflicts, stores binaries on S3, and enforces cross-user isolation.

The contract this server implements is `BACKEND_API_SPEC.md` (frozen V1 spec). The current implementation status, divergences from the spec, deferred items, and prod-config env vars are documented in `BACKEND_HANDOFF.md`. Read those two files before touching the API surface — they describe behavior that is load-bearing for the iOS client and cannot be silently changed.

## Common commands

Local dev runs in Docker via Laravel Sail. The host ports are non-default because 80/5173/5432 are taken on this machine — see `.env` (`APP_PORT=8000`, `FORWARD_DB_PORT=5433`, `VITE_PORT=5180`).

```sh
./vendor/bin/sail up -d                          # start containers
./vendor/bin/sail artisan migrate:fresh          # reset the DB
./vendor/bin/sail artisan test --compact         # full feature suite
./vendor/bin/sail artisan test --compact tests/Feature/SyncPushTest.php
./vendor/bin/sail artisan test --compact --filter=testApplePushesAndPulls
./vendor/bin/sail artisan quest:purge-expired    # retention job (scheduled daily at 03:00 UTC)
./vendor/bin/sail artisan route:list --except-vendor
./vendor/bin/sail down
```

Outside Sail, the equivalents are `php artisan ...`. After modifying PHP files, run `vendor/bin/pint --dirty --format agent` before finalizing.

## Architecture

### API surface (`routes/api.php`)

All routes are under `/api`. Public routes: `auth/password/{register,login}`, `auth/apple`, `auth/google`. Everything else is behind `auth:sanctum`. Authenticated identity routes: `POST /auth/logout` (revoke current token), `GET /me` (current user), `DELETE /me` (delete account + all tokens, cascades content). The two sync endpoints (`/sync/push`, `/sync/pull`) additionally pass through `throttle:sync` (60 req/min/user, configurable via `QUEST_RATE_LIMIT_SYNC`). Binary uploads land at `/uploads/{attachments,audio,character-photos}/{id}`.

The api middleware stack appends `ForceJsonResponse` (every response is JSON regardless of Accept) and `ValidateJsonBody` (malformed JSON → 400 `bad_request`). Exception renderers in `bootstrap/app.php` translate validation, auth, 404, and bad-request errors into the spec's `{error, message, fields?}` envelope.

### Controllers → Services

Controllers (`app/Http/Controllers/{Auth,Sync,Upload}Controller.php`) are thin: parse form-request, delegate to a service, return an API Resource. The real logic lives in `app/Services/`:

- `Services/Sync/SyncPushService` — sorts incoming changes by entity-type priority (content → binary metadata → junctions), runs the whole batch in a single `DB::transaction`, returns `{confirmed, conflicts}`. Conflict = strict `>` on `updated_at` (server wins). Equal timestamps are idempotent (incoming wins). `uri`/`photoUri` from clients is always coerced to `""`; `remote_uri` is preserved server-side and cannot be cleared via push.
- `Services/Sync/SyncPullService` — strict `updated_at > lastPullTimestamp` filter, emits content with `isDeleted: true` as `operation: upsert`, emits junction deletions from tombstone tables as `operation: delete`. Output is ordered by entity type so the client can apply in dependency order without forward references.
- `Services/Upload/BinaryUploadService` — MIME whitelist + size cap per kind, 409 on already-uploaded, stores at `<kind>/<user_id>/<entity_id>.<ext>`, bumps `updated_at` so other devices receive the change on next pull. HEIC/HEIF are re-encoded to JPEG via `Intervention\Image` v4 (Imagick driver) with EXIF orientation applied and all metadata stripped.
- `Services/Auth/{Apple,Google}TokenVerifier` — `firebase/php-jwt` against each provider's JWKS, checks `iss` + `aud`. We intentionally chose this over Socialite (see `BACKEND_HANDOFF.md` §12.a).

### Models, scopes, and cross-user isolation

Models live flat in `app/Models/` (`User`, `Entry`, `Quest`, `Character`, `EntryAttachment`, `EntryAudio`). Two global scopes in `app/Models/Scopes/` enforce isolation:

- `BelongsToCurrentUserScope` — applied to content models, filters by `Auth::id()` when a user is authenticated.
- `ThroughEntryToCurrentUserScope` — applied to entry-descendant models (attachments, audio) that don't have `user_id` themselves; joins to entry's owner.

**Sync push deliberately bypasses these scopes** for the documented silent-skip semantics (a cross-user write must not 4xx — it must be silently dropped so the client doesn't reveal that the foreign ID exists). All other read paths must go through the scopes. If you write a query that needs to see all users (background job, etc.), use `withoutGlobalScope(...)` explicitly.

### Millisecond timestamps

Per spec, `updatedAt` is the LWW key and must round-trip with millisecond precision. This is implemented globally:

- Migrations declare `timestamp(3)` columns, not Laravel's default `timestamp(0)`.
- Every model that participates in sync sets `protected $dateFormat = 'Y-m-d H:i:s.v'`.
- Sync push **does not** auto-touch `updated_at` — the service writes the client-supplied value verbatim. If you add a new write path for a content entity, replicate that pattern; don't let Eloquent's auto-touching overwrite the client value.

When converting between ISO 8601 (with the trailing `Z` and ms) and DB datetimes, use `App\Support\IsoDate`.

### Encryption at rest

Title/html/description/name/relationship/note columns on `entries`, `quests`, `characters` use Laravel's `encrypted` cast (stored as `text`). Decryption is transparent on read. The same `APP_KEY` encrypts every user's data — this is server-readable, not E2E, and was an intentional product decision (supports future AI features and key recovery). `APP_KEY` must be backed up separately from DB backups.

### Junction tombstones

`entry_quest_tombstones` and `entry_character_tombstones` exist because junction deletes need to propagate through pull. Push handles them: create junction → delete matching tombstone; delete junction → upsert tombstone with `deleted_at = now()`. Pull emits tombstones as `operation: delete`. The retention command purges tombstones older than 90 days.

### Retention

`app/Console/Commands/PurgeExpiredCommand.php` (registered as `quest:purge-expired`, scheduled in `routes/console.php` daily at 03:00 UTC): hard-deletes soft-deleted content older than 30 days (CASCADE removes child rows), deletes the associated S3 binaries first, then purges junction tombstones older than 90 days. Stats are logged via `Log::info('quest.retention.purge', ...)`.

### Tests

All meaningful tests are feature tests under `tests/Feature/`, organized by domain (`AuthTest`, `SyncPushTest`, `SyncPullTest`, `UploadTest`, `IsolationTest`, `EncryptionTest`, `RateLimitTest`, `RetentionTest`, `CrossAccountTest`, `MalformedRequestTest`, `SchemaTest`). They map to the spec's §13 acceptance matrix — when adding behavior, find the corresponding section in `BACKEND_API_SPEC.md` and add the scenario to the matching test class.

Uploads tests use `Storage::fake('s3')`. Don't mock at the service layer — the suite is designed to exercise real DB + filesystem behavior through HTTP.

## Notes for non-obvious behavior

- The default Laravel `HasUuids` trait generates UUID v7. Quest requires UUID v4 (the iOS client's regex assumes v4). `users.id` overrides this; new models that need server-generated IDs must do the same.
- Sanctum `guard` is `[]` — Bearer-only, no session/SPA fallback. Don't add `web` middleware to API routes.
- API Resources have `public static $wrap = null` — responses are not wrapped in `{data: ...}`. Match that on any new resource.
- DB columns are `snake_case`; JSON payloads are `camelCase`. API Resources do the mapping. Don't add camelCase columns.
- `/up` is the health endpoint at the root (outside `/api`), provided by Laravel via `bootstrap/app.php`'s `health: '/up'`.
- `routes/web.php` also serves three public legal Blade pages outside `/api` via `LegalController`: `/privacy`, `/terms`, `/support` (the App Store / Play Store require them; en/fr locale resolved from `?lang=` or `Accept-Language`).

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
