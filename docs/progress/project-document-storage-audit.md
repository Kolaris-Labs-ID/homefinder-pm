# Project Document Storage — Readiness Audit

**Scope:** Read-only audit before implementing internal file storage for documents attached to a Project (= one client/company). **No production code changed. No migrations, models, resources, routes, or tests created.**

**Date:** 2026-06-13
**Repo:** `homefinder-pm` (Laravel 12 + Filament 3)
**Hard MVP rule:** project documents are **internal-only**; they must never appear on the external client dashboard.

---

## 1. Project Domain Audit

| Q | Finding |
|---|---|
| 1. What is a Project today? | A PM container: `name`, `description` (RichEditor HTML), `ticket_prefix`, `start_date`/`end_date`, pin/archive/done state, `created_by`. `app/Models/Project.php`. Has tickets, epics, ticketStatuses, members (`project_members` pivot), notes, one `externalAccess`. |
| 2. Client/company concept? | **No first-class client/company model.** "One Project = one client/company" is an implicit product convention, not modeled. There is no `company_id`, no `Client` model. (Note: an unrelated `HiringCompany` exists in the recruitment/ATS context — not the PM client.) |
| 3. Does Project have documents/files today? | **No.** No `documents()`/`attachments()` relation, no file columns. |
| 4. Files on Ticket/Comment/Note? | No dedicated attachment models for PM. `TicketsRelationManager` uses `FileUpload::make` for **CSV import only** (not stored attachments). PM notes/comments have no file storage. |
| 5. Soft-deletable? | **No `SoftDeletes`.** `ProjectResource` imports `SoftDeletingScope` but the model does not use the trait. |
| 6. Archive/closed state? | Yes — **`archived_at`/`archived_by`** (archive, with `scopeActive`/`scopeArchived`) and **`done_at`/`is_auto_done`** (completion). Archiving is the project's "soft delete" analog, via `ProjectArchiveService`. API `destroy()` archives rather than deletes. |
| 7. Relationships affected by adding `ProjectDocument`? | Additive only: a new `documents(): HasMany`. Touch points to review on add: PM API `formatProject()`, Filament `ProjectResource::getRelations()`, and the external `ExternalDashboard` Livewire component (`public $project`). |
| 8. Existing Project activity/audit logging? | Yes — **`ActivityLog`** (polymorphic `subject` morphTo + denormalized `project_id` + `user_id` + `event_type` + JSON `meta`). `Project::booted()` fires `ActivityLog::logProjectCreated`. Dispatches `ActivityLogCreated` event for push-notify. Directly reusable for document events. |

---

## 2. Storage Infrastructure Audit

`config/filesystems.php` disks:

| Disk | Driver | Root | Public? |
|---|---|---|---|
| `local` | local | `storage/app/private` | `serve=true` (Livewire temp/preview) |
| **`private`** | local | `storage/app/private` | **`serve=false` — not web-served** |
| `public` | local | `storage/app/public` | public, symlinked to `public/storage` |
| `s3` | s3 | env-driven | only if AWS_* set |

| Q | Finding |
|---|---|
| 1. Disks | local, private, public, s3 (above). |
| 2. Which disk for project docs? | **`private`.** It is the established convention for all sensitive files (resumes, finance attachments). |
| 3. Private disk exists? | **Yes** (`private`, `serve=false`). |
| 4. Object storage? | s3 block exists but **driven entirely by env**; default `FILESYSTEM_DISK=local`. Treat as local-only for MVP; the `Storage::disk('private')` indirection makes a later S3 swap transparent **provided no `temporaryUrl`/public-URL assumptions are baked in**. |
| 5. Files exposed publicly? | Project-relevant files: none. Only the `public` disk (general assets) is web-served. Resumes & finance attachments are private + streamed. |
| 6. Temporary URLs? | **No `temporaryUrl()` usage anywhere.** All downloads stream through authorized Laravel routes. |
| 7. Should preview/download stream through Laravel? | **Yes** — matches `ResumeController` and `FinanceAttachmentController`. |
| 8. Filename sanitization conventions? | Stored filenames are **randomized** (`Str::uuid()`, `Str::random(40)`, or `$file->store()` hash names); original filename kept only in a metadata column. On download, `ResumeController` uses `addslashes()` in the `Content-Disposition` header. |
| 9. File hash conventions? | **sha256** everywhere (`hash('sha256', …)` / `hash_file('sha256', …)`). |
| 10. Max file-size conventions? | Per-feature: resume = **1 MB** (`ResumeStorageService::MAX_BYTES`). No global constant. Finance attachments rely on Filament/validation upstream. |

Searches: `temporaryUrl` → 0 hits. `Storage::disk` → 10+ files, all `local`/`private`. `storage:link` symlink → only `public` disk. No `asset('storage'` for sensitive files.

---

## 3. Existing File/Attachment Pattern Audit

Two archetypes exist. **They sit at opposite ends of the design space.**

### A. `ResumeStorageService` (denormalized, exactly-one-file)
- Storage: columns on `Applicant` (`resume_disk`, `resume_file_path`, `resume_original_filename`, `resume_mime_type`, `resume_size_bytes`, `resume_file_hash`, `resume_uploaded_at`).
- Disk: `private`. Private only.
- Metadata: original filename, mime, size, sha256, uploaded_at. (No `uploaded_by` — single HR context.)
- Download: `ResumeController@view` (inline) / `@download` (attachment), web routes behind `auth` + `ApplicantPolicy::viewResume/downloadResume`.
- Validation: **magic-byte** check (`%PDF`), not extension-only; 1 MB cap.
- Cleanup: `delete($disk,$path)`.
- Reuse fit: **No** — one-file-per-record shape; projects need many.

### B. `FinanceAttachment` (polymorphic, many-per-owner) ← **design template**
- Model `app/Models/Hris/FinanceAttachment.php`: `attachable_type/id` (morphTo), `attachment_role`, `file_path`, `file_hash`, `file_size`, `mime_type`, `original_filename`, `uploaded_by`, `uploaded_at`, `locked_at`, `locked_reason`, `metadata` (JSON).
- Service `FinanceAttachmentService`: sha256 hash, **duplicate detection** (warn on matching hash), private disk, immutable **lock** mechanism, audit on every upload/lock/delete via `FinanceAuditLogger`.
- Controller `FinanceAttachmentController`: `download()` authorizes via `FinanceAttachmentPolicy` then `Storage::disk('private')->download()`; `destroy()` policy-gated, 422 on locked.
- Reuse fit: **design = ideal; the class itself = avoid.** It is namespaced to and semantically bound to finance (`attachment_role` constants are all finance/payment-bundle). Reusing it for project docs would couple PM to the finance domain and pollute its audit channel.

**Recommendation:** create a dedicated **`ProjectDocument`** model that mirrors `FinanceAttachment`'s security design (private disk, sha256, `uploaded_by`, `original_filename` only as metadata, randomized stored name, policy-gated streaming, audit) rather than reusing either existing class. Do **not** use `FinanceAttachment` for project documents.

---

## 4. Filament UI Audit

`ProjectResource` (`app/Filament/Resources/ProjectResource.php`, 356 lines):
- Form: TextInput/RichEditor/DatePicker/members Select. **No tabs, no infolist.**
- Relation managers (`getRelations()`): TicketStatuses, Members, Epics, Tickets, **Notes** — 5 total. `NotesRelationManager` is the closest precedent for a simple per-project sub-resource.
- Pages: `ListProjects`, `CreateProject`, `ViewProject`, `EditProject`.
- `getEloquentQuery()` already scopes projects to super_admin/C-Level or members — **a documents RM inherits this scoping automatically.**

| Q | Finding |
|---|---|
| 1. Where should documents live? | **A new `DocumentsRelationManager` on `ProjectResource`** (mirrors `NotesRelationManager`). Internal Filament admin only. A separate top-level resource is unnecessary for MVP. |
| 2. Tabs/infolists/RMs already? | Relation managers yes; tabs/infolists no. |
| 3. Clean place for upload/preview/download/archive/filter/search? | Yes — a relation-manager table with a `FileUpload` create form, table columns (filename, category, size, uploaded_by, date), a custom **download** action (route to a streaming controller), **delete** action, category `SelectFilter`, and filename search. |
| 4. File-preview UI conventions? | Streaming controllers with `inline` disposition (resume PDF preview). No in-Filament viewer component. |
| 5. Spatie Media Library or FileUpload? | **No Spatie Media Library installed** (no `spatie/laravel-medialibrary`, `HasMedia`, or `InteractsWithMedia` anywhere). The codebase uses Filament `FileUpload::make` (11 occurrences) + manual `Storage`. |

---

## 5. Authorization & Policy Audit

| Q | Finding |
|---|---|
| 1. Who can view Project? | `ProjectPolicy::view` → `view_project` permission (Filament Shield). |
| 2. Manage Project? | `update_project`, `delete_project`, `archive_project`, etc. (full Shield CRUD + archive set). |
| 3. Filament access? | Shield permissions + `getEloquentQuery()` member/role scoping. |
| 4. API access? | `Api\Pm\ProjectController` — 3-tier scope: super_admin/C-Level → all; division head → division members' projects; staff → own membership. Mutations gated by `authorizeAccess()` (super_admin or project member). |
| 5. External clients = users/tokens/records? | **`ExternalAccess` records** (`external_access` table): `access_token` (32-char), plaintext `password` (8-char), `is_active`. **Not Laravel users.** Auth is session-flag based (`external_authenticated_{token}`), no guard/User. |
| 6. Internal vs external distinction? | Sharp: internal = authenticated `User` + Shield; external = anonymous session holding an `ExternalAccess` token. Different layouts, different routes, no shared auth. |
| 7. `ProjectDocumentPolicy` methods needed? | `viewAny`, `view`, `create` (upload), `delete` — plus a `download` ability used by the streaming controller (mirrors `FinanceAttachmentPolicy`). |
| 8. Inherit Project permission? | **Yes** — document access should derive from the user's access to the parent Project (member/role scoping), gated additionally by a `*_project_document` Shield permission set. |
| 9. Client-visible permission concept? | **None today.** No "client-visible" flag exists on any PM entity. MVP must not introduce one. |

**Required:** project documents are **internal-only** in MVP; `ProjectDocumentPolicy` grants nothing to external access.

---

## 6. External Client Dashboard Audit — **CRITICAL**

| Q | Finding |
|---|---|
| 1. How does the external dashboard access project data? | `App\Livewire\ExternalDashboard` (Livewire full-page component, `layouts.external`). Resolves `ExternalAccess` by token → `$this->project = $externalAccess->project`. |
| 2. Endpoints | Web only: `GET external/{token}` (login) and `GET external/{token}/dashboard`. **No external API.** `routes/api.php` `public/v1` is the unrelated job portal; no project endpoints there. |
| 3. Project fields exposed | name, description, dates, progress %, remaining days, ticket counts, members count. Built explicitly in `loadWidgetData()`/`loadDashboardData()`. |
| 4. Tickets/tasks/comments exposed? | **Tickets yes** (paginated list, gantt, calendar — incl. name, description, uuid, status, priority, assignee name+email). Ticket **history** exposed via `getRecentActivitiesProperty`. Comments: not rendered. |
| 5. Notes exposed? | No. |
| 6. Existing visibility flag for external data? | **None.** External exposure is implicit in what the component chooses to query. |
| 7. Raw `toArray()` serializer? | The component does **not** call `$project->toArray()`. **However** `public $project` is a Livewire-managed model property → Livewire **dehydrates the model (and any *loaded* relations) into the browser payload.** This is the real leak surface, not `toArray()`. |
| 8. Could a `documents()` relation accidentally leak? | **Only if** something eager-loads `documents` onto `$this->project`, or a Blade view lazy-iterates `$project->documents`. The component currently loads only `statuses`/`priorities` and queries `tickets()` directly, so a plain `documents()` relation would **not** auto-serialize. Risk is **latent, not active** — must be guarded explicitly. |
| 9. Relationship counts exposed? | Ticket/member/status counts yes; no document count today. |
| 10. Eager-loaded relations auto-serialized? | Via Livewire model dehydration — **yes, if loaded.** Hence the guardrail below. |

**Hard rule (MVP):** the external dashboard must expose **none** of: documents, document count, filenames, categories, descriptions, file paths, disk names, hashes, preview URLs, download URLs, `uploaded_by`, document audit events.

**Concrete guardrails:**
1. Never call `->load('documents')` / `->withCount('documents')` / `->with('documents')` on any Project instance reachable by `ExternalDashboard` or its Blade views.
2. Do not add `documents` to `Project::$with`.
3. Add a regression test asserting the rendered external dashboard payload contains no document data (see §10).
4. Keep document routes behind `auth` middleware + policy; the external session is not an authenticated user and cannot reach them.

---

## 7. API Serialization Leak Audit

| Q | Finding |
|---|---|
| 1. Allowlist or raw? | **Explicit allowlist.** `Api\Pm\ProjectController::formatProject()` returns a hand-built array. No `JsonResource`/`Http\Resources` layer (per CLAUDE.md). No `return $project;`. |
| 2. Relationship counts included? | `tickets_count` only. |
| 3. `$hidden` configured on Project? | No `$hidden`/`$visible` on `Project`. Safe today **because** responses are allowlisted, not because of hidden fields. |
| 4. Risk a future `documents` relation leaks via API? | **Low** — `formatProject()` won't include it unless edited. The risk is a future dev returning `$project` raw or adding `->load('documents')` to a response. |
| 5. `ProjectDocument` `$hidden` fields? | Defense-in-depth: hide `file_path`, `file_hash`, `disk` on the model so any accidental serialization omits internals. |
| 6. External resources explicit allowlist? | The external dashboard is Livewire (not JSON Resource); enforce via the §6 guardrails. Any future external JSON must use explicit allowlists. |

---

## 8. Security Audit

Current platform support (✓ available / pattern exists):

| Control | Status | Source of truth |
|---|---|---|
| Private storage | ✓ | `private` disk |
| Random stored filenames | ✓ | `Str::uuid/random`, `$file->store()` |
| Original filename escaping | ✓ | `addslashes()` in disposition (ResumeController) |
| MIME validation | ◑ | magic-byte for resume; finance trusts client mime — **strengthen for docs** |
| File size validation | ◑ | per-feature only; **define a 10 MB const** |
| File hash storage | ✓ | sha256 |
| Orphan file cleanup | ✓ | service `delete()` removes file + row |
| Protected download | ✓ | streaming controllers + policy |
| Protected inline preview | ✓ | `inline` disposition (PDF/image) |
| Route authorization | ✓ | `$this->authorize(...)` / policy |
| Audit logging | ✓ | `ActivityLog` (PM) / `FinanceAuditLogger` (finance) |
| Soft delete / archive | ◑ | Project archives; ProjectDocument should soft-delete or hard-delete+audit |
| Formula-injection (CSV) | ✓ pattern | ATS CSV sanitize exists (recruitment); apply if docs are exported |
| XSS on filenames/descriptions | ⚠ | must escape on render; Blade `{{ }}` escapes by default — **do not use `{!! !!}`** for doc fields |
| Public URL prevention | ✓ | no `temporaryUrl`, private disk `serve=false` |

**Recommended MVP file policy:** private disk only · no public URL · **max 10 MB** · allow PDF/DOC/DOCX/XLS/XLSX/PNG/JPG/JPEG · validate by content where feasible (PDF/image magic bytes), not extension alone · stream through an authorized Laravel route · inline preview for PDF/image, download-only for Office formats.

**Out-of-scope pre-existing weakness (flag only):** `ExternalLogin::login()` compares `$externalAccess->password !== $this->password` — **plaintext password, non-constant-time, no hashing/rate-limit**. Not part of this feature, but relevant because it guards the surface documents must never reach. Recommend a separate ticket.

---

## 9. Audit / Activity Logging Audit

| Q | Finding |
|---|---|
| 1. Existing Project activity log? | Yes — `ActivityLog` (polymorphic + `project_id`), already logs `project_created`, ticket events. |
| 2. Reusable for document events? | **Yes.** `ActivityLog::recordForSubject($projectDocument, 'project_document_*', $user, $meta)` — the morphTo `subject` + `project_id` resolution handles it (extend the `match` in `recordForSubject` to map `ProjectDocument → project_id`, or pass project_id via the subject). |
| 3. Events to log | `project_document_uploaded`, `_viewed`, `_downloaded`, `_archived`, `_deleted`. |
| 4. Download/view logged anywhere today? | No — resume/finance downloads are not activity-logged (finance logs upload/lock/delete only). Adding view/download logging for docs is **new** but cheap via `ActivityLog`. |
| 5. IP / user-agent logging? | Not in `ActivityLog` today (only `meta` JSON). Can be added per-event in `meta` if required. |

---

## 10. Testing Baseline Audit

Existing relevant tests:
- `tests/Unit/ProjectTest.php`, `tests/Feature/ProjectTest.php`, `tests/Feature/ProjectBoardTest.php`, `tests/Feature/ProjectArchivePropertyTest.php`.
- **No `ExternalDashboard`/`ExternalAccess` tests** (gap — the surface we must protect is untested).
- `Storage::fake` is used widely (finance, training, expense, payment-bundle, recruitment Filament tests) — established pattern.
- Filament Livewire tests exist (`tests/Feature/Filament/...`) using `livewire()` helper.
- DB: SQLite `:memory:` (Pest).

| Q | Finding |
|---|---|
| 1. Existing relevant tests | Project CRUD/board/archive (above). |
| 2. Missing tests | External dashboard isolation; any document tests; download authorization. |
| 3. Helpers/factories | `ProjectFactory` exists; no `ExternalAccess` test usage; finance/resume tests show the `Storage::fake('private')` + policy pattern to copy. |
| 4. `Storage::fake`? | Yes, common. |
| 5. Filament Livewire tests? | Yes. |
| 6. External dashboard tests? | **None — must add.** |

Proposed new test files:
- `ProjectDocumentStorageTest` — store/hash/size/disk, 10 MB cap, mime allowlist.
- `ProjectDocumentAuthorizationTest` — policy view/create/delete by role & membership.
- `ProjectDocumentFilamentTest` — relation-manager upload/list/delete (Livewire).
- `ProjectDocumentPreviewDownloadTest` — inline vs attachment, 404 on missing, IDOR (non-member blocked).
- `ProjectDocumentAuditTest` — ActivityLog events fired.
- `ProjectDocumentExternalDashboardIsolationTest` — **render external dashboard, assert no document data in payload/HTML.**
- `ProjectDocumentExternalAccessAuthorizationTest` — external token cannot hit document routes (redirect/403/404).
- `ProjectDocumentSerializationLeakTest` — `formatProject()` and model `$hidden` omit file_path/hash/disk.

---

## 11. Risk Register

| Risk | Severity | Current Evidence | Recommendation |
|---|---:|---|---|
| External dashboard leakage | **High** | `public $project` Livewire prop dehydrates loaded relations to browser; no visibility flag exists | Never load/withCount/with `documents` on Project reachable by `ExternalDashboard`; add isolation test (§6, §10) |
| Public storage exposure | Low | Sensitive files use `private` (serve=false); no `temporaryUrl` | Use `private` disk; stream via authorized route only |
| Raw model serialization | Low | PM API uses `formatProject()` allowlist; no `return $project` | Keep allowlist; add `$hidden` on ProjectDocument as defense-in-depth |
| Weak authorization | Medium | ProjectPolicy is permission-based; no document policy yet | Add `ProjectDocumentPolicy` inheriting Project access + Shield perms |
| Orphan files | Low | Service `delete()` removes file+row (finance/resume) | Mirror in ProjectDocumentService; delete file on row delete |
| Filename XSS | Medium | original_filename rendered in UI | Blade `{{ }}` auto-escape; never `{!! !!}`; `addslashes` in disposition |
| Formula injection (CSV export) | Low | ATS CSV sanitizer exists | Reuse sanitizer if a document list export is added |
| Missing audit logs | Low | `ActivityLog` available, not yet wired for docs | Log upload/view/download/archive/delete |
| Oversized files | Medium | No global size cap | Enforce 10 MB in service + Filament FileUpload |
| MIME spoofing | Medium | Finance trusts client mime | Validate by content (magic bytes) for PDF/image; restrict extensions |
| Accidental future client-visible exposure | **High** | No client-visible concept today; easy to add a relation that leaks | Do not introduce a client-visible flag; keep docs internal-only; guardrails + test |
| Object storage misconfig | Low | s3 env-driven, default local | Avoid `temporaryUrl`/public-URL assumptions so S3 swap stays transparent |
| External login plaintext password (pre-existing) | Medium | `ExternalLogin` compares plaintext, no rate-limit | Separate ticket; relevant because it guards the surface docs must avoid |

---

## 12. Final Recommendation

1. **Filament FileUpload?** Yes — `FileUpload::make` in a relation-manager create form (no Spatie).
2. **Laravel Storage private disk?** Yes — `Storage::disk('private')`, `serve=false`.
3. **Custom `ProjectDocument` model?** **Yes** — dedicated `HasMany` from Project (not polymorphic needed for MVP). Mirror `FinanceAttachment`'s security fields: `project_id`, `category`, `file_path`, `file_hash` (sha256), `file_size`, `mime_type`, `original_filename`, `uploaded_by`, `uploaded_at`, optional `archived_at`. `$hidden`: `file_path`, `file_hash`. **Do not reuse `FinanceAttachment`.**
4. **Spatie Media Library?** **No** — not installed; not worth introducing for MVP.
5. **UI location:** new `DocumentsRelationManager` on `ProjectResource` (internal admin only), modeled on `NotesRelationManager`.
6. **Routes:** one authenticated streaming route group, e.g. `GET /admin/projects/{project}/documents/{document}/{view|download}` behind `auth` + `ProjectDocumentPolicy`, mirroring `ResumeController`. No external/public route.
7. **Policies:** `ProjectDocumentPolicy` (`viewAny/view/create/delete/download`) inheriting Project access + a `*_project_document` Shield permission set; nothing granted to external access.
8. **External dashboard guardrails:** never eager-load/count `documents` on Project in `ExternalDashboard`; add isolation + access tests; rely on `auth` middleware to keep the external session out of document routes.
9. **Phases (proposed):**
   - **P0** — migration + `ProjectDocument` model + factory + `Project::documents()`.
   - **P1** — `ProjectDocumentService` (store/validate 10 MB + mime, sha256, delete) + `ProjectDocumentPolicy` + Shield perms.
   - **P2** — streaming controller + routes (view/download) + ActivityLog wiring.
   - **P3** — `DocumentsRelationManager` (upload/list/filter/search/download/delete).
   - **P4** — tests (all 8 files in §10), with the external-isolation test as the gate.

**Default recommendation confirmed:** Laravel Storage (private) + Filament FileUpload + custom `ProjectDocument` model/service, private disk only, no external dashboard exposure.

---

## Validation performed (read-only)

- `php artisan route:list | grep -iE "project|external|client|file|document|attachment"` — confirmed PM API project routes use explicit controller; external surface is only `external/{token}` + `/dashboard` (Livewire); finance attachment download/destroy are the existing protected-file routes; **no project document/attachment routes exist.**
- `rg "FileUpload::make"` → 11 occurrences (no Spatie).
- `rg "spatie/laravel-medialibrary|HasMedia|InteractsWithMedia"` → **0** (no Media Library).
- `rg "Storage::disk"` / `temporaryUrl` → all sensitive storage on `private`; **0** temporary-URL usages.
- `rg -i document resources/views/...external...` → only JS `document.*` DOM refs; **no project-document rendering** on the external dashboard.
- Test suites (`--filter=Project` / `=ExternalAccess`) **not executed** to keep this pass strictly read-only and fast; baseline determined by inspecting `tests/` (see §10). External dashboard has **no** existing tests.

## Unresolved Questions

1. **Document categories** — fixed enum (e.g. Contract / SOW / Invoice / Report / Other) or free-text? Affects migration + filter UI.
2. **Versioning** — does re-uploading the "same" document replace, or keep history? MVP assumption: each upload is a distinct row (no versioning).
3. **Delete semantics** — hard delete + audit (like `FinanceAttachment`), or `archived_at` soft-archive to match Project's archive model? Recommend soft-archive for recoverability; needs confirmation.
4. **Who can upload/delete** — all project members, or managers/HR-admin only? §5 assumes inherit-Project-access + Shield perms; exact role matrix unconfirmed.
5. **View/download activity logging granularity** — log every view/download (volume) or only upload/delete? §9 lists all five events; confirm whether view/download logging is required for MVP.
6. **Office-file preview** — confirmed download-only (no server-side render) for DOC/XLS in MVP?
7. **External login hardening** — track the plaintext-password weakness (§8) as a separate security ticket, or fold into this work?
8. **S3 in production** — is object storage actually used in prod? If yes, confirm no inline-preview assumptions break (range requests, content-type) under S3.
