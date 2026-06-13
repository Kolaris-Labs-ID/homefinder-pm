# Internal Project Document Storage — Implementation

Implements internal file storage for documents attached to a Project (= one client/company).
Audit reference: [`project-document-storage-audit.md`](project-document-storage-audit.md).

**Hard rule:** documents are internal-only; they must never be reachable from the external client dashboard (`ExternalAccess` / `ExternalDashboard`). The external-isolation + external-access tests are the **release gate**.

Built test-first (TDD): each behavior gets a failing test before implementation.

---

## Locked decisions (from final implementation prompt)

1. **Categories** — fixed set (string column + app-level validation): `contract, sow, invoice, report, brief, design, credential, legal, meeting_note, other`.
2. **Versioning** — none. Each upload is a distinct `ProjectDocument` row.
3. **Delete** — **soft-archive** by default (`archived_at`, `archived_by`, `archive_reason`). Physical file is retained; no purge in MVP.
4. **Permissions** — permission-based, project-scoped:
   - view/download → access parent Project **AND** `view_project_document` / `download_project_document`
   - upload → access parent Project **AND** `create_project_document`
   - archive → access parent Project **AND** `delete_project_document`
   - external dashboard → **nothing**.
5. **Audit** — log upload, download, archive (view optional, deferred).
6. **Office preview** — DOC/DOCX/XLS/XLSX download-only; only PDF/images preview inline.
7. **External login hardening** — separate ticket; out of scope. But tests must prove a valid external session cannot reach documents.
8. **S3** — `Storage::disk('private')` only; no `temporaryUrl`, no public URL.

"Access parent Project" = `$user->canSeeAllPmData()` (super_admin + C-Level) **OR** project member — identical to `ProjectResource::getEloquentQuery()` scoping. `super_admin` is additionally short-circuited by the global `Gate::before` hook.

---

## Phase 1 — Migration + Model

Table `project_documents`:

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| project_id | foreignId → projects, cascadeOnDelete | |
| uploaded_by | foreignId nullable → users, nullOnDelete | actor |
| disk | string, default `private` | |
| file_path | string | randomized stored path |
| original_filename | string | metadata only |
| mime_type | string | |
| size_bytes | unsignedBigInteger | |
| file_hash | string(64), index | sha256 |
| category | string, index | one of the fixed set |
| description | text nullable | |
| uploaded_at | timestamp | |
| archived_at | timestamp nullable | soft-archive |
| archived_by | foreignId nullable → users, nullOnDelete | |
| archive_reason | string nullable | |
| timestamps | | |

`App\Models\ProjectDocument`:
- `belongsTo` Project, `uploadedBy` (User), `archivedBy` (User).
- `Project::documents(): HasMany`.
- `$hidden = ['file_path', 'file_hash', 'disk']` (defense-in-depth against serialization leaks).
- `CATEGORIES` const + `CATEGORY_LABELS`; accessors: `category_label`, `formatted_size`, `is_archived`.
- Scopes: `active()` (whereNull archived_at), `archived()`.
- **Not** added to `Project::$with`; never eager-loaded/withCount on a Project reachable by the external dashboard.

## Phase 2 — Storage Service

`App\Services\ProjectDocumentStorageService`:
- `DISK = 'private'`, `MAX_BYTES = 10 MB`.
- `store(Project, UploadedFile, string $category, string $description, User): ProjectDocument` — validate, store at `project-documents/{project_id}/{uuid}.{ext}`, sha256, cleanup file on DB failure, log `project_document_uploaded`.
- `archive(ProjectDocument, User, ?string $reason): void` — set archive columns, retain file, log `project_document_archived`.
- Validation (content-sniffed, not extension-only):
  - size ≤ 10 MB; extension ∈ {pdf,doc,docx,xls,xlsx,png,jpg,jpeg}.
  - magic bytes by family: pdf `%PDF`; png `\x89PNG`; jpg `\xFF\xD8\xFF`; doc/xls OLE2 `\xD0\xCF\x11\xE0`; docx/xlsx ZIP `PK\x03\x04`.

## Phase 3 — Policy + Permissions

`App\Policies\ProjectDocumentPolicy`: `viewAny`, `view`, `create`, `download`, `delete` (delete = archive). Project-scoped via `canAccessProject()`. `ProjectDocumentPermissionSeeder` (web guard): `view/create/download/delete_project_document`; granted to super_admin + manager/head-division + hr-admin (mapped to existing roles). Registered in `AuthServiceProvider` + `DatabaseSeeder`.

## Phase 4 — Protected routes + controller

`App\Http\Controllers\ProjectDocumentController` under `['web','auth']`:
- `GET /admin/projects/{project}/documents/{document}/view` — inline for PDF/image; **download-only** (attachment) for Office.
- `GET /admin/projects/{project}/documents/{document}/download` — attachment, escaped filename.
- Guards: `$document->project_id === $project->id` (404 on mismatch, no IDOR); `authorize()`; stream from `private`; 404 if file missing; logs `project_document_downloaded`.
- **Archive** is handled via the Filament RM action → service (not a standalone web route) to minimize attack surface — see Deviations.

## Phase 5 — ActivityLog
Extend `ActivityLog::recordForSubject()` match: `ProjectDocument → project_id`. Events `project_document_uploaded|downloaded|archived` with meta (category, original_filename, size_bytes, mime_type). Not exposed externally.

## Phase 6 — Filament Relation Manager
`ProjectResource\RelationManagers\DocumentsRelationManager` (added to `getRelations()`): FileUpload (stores via service), category Select, description; columns filename/category/size/uploader/date/archived; filters category + active/archived; search filename; actions preview (PDF/image)/download/archive. Internal Filament only; never exposes file_path/hash/disk.

## Phase 7 — External dashboard isolation (RELEASE GATE)
Never `with/load/withCount('documents')` on any Project in `ExternalDashboard` or its Blade. No documents in Project global eager-loading. Tests assert rendered external output contains no document data and is unchanged by adding a document.

## Phase 8 — API serialization guard
`Api\Pm\ProjectController::formatProject()` stays an explicit allowlist (no documents / no documents_count). `ProjectDocument` `$hidden` covers accidental serialization. Regression-tested.

## Phase 9 — Export sanitization
**N/A for MVP** — no ProjectDocument CSV/Excel export exists. If added later, sanitize `=`, `+`, `-`, `@`, tab, CR leading cells on filename/description/category/uploader/archive_reason (reuse ATS CSV sanitizer).

## Phase 10 — Tests
`ProjectDocumentStorageTest`, `ProjectDocumentAuthorizationTest`, `ProjectDocumentFilamentTest`, `ProjectDocumentPreviewDownloadTest`, `ProjectDocumentAuditTest`, `ProjectDocumentExternalDashboardIsolationTest`, `ProjectDocumentExternalAccessAuthorizationTest`, `ProjectDocumentSerializationLeakTest`.

## Phase 11 — Validation
`pest --filter='ProjectDocument'`, `=Project`, `=ExternalDashboard`; `pint`; targeted `rg` for stray `documents` eager-loads.

---

## Status — all phases complete & verified (2026-06-13)

Built test-first. Verification run:

| Suite | Result |
|---|---|
| `tests/Feature/Pm` (the 8 ProjectDocument files) | **41 passed / 109 assertions** |
| `--filter=Project` (existing + new) | **155 passed / 5193 assertions** — no regressions |
| `--filter=ExternalDashboard` | 3 passed |
| `--filter=ActivityLog` | 4 passed |
| `tests/Feature/Filament` (RM added to ProjectResource) | 32 passed |
| `pint --dirty` | clean |
| `rg` leak-vector scan (`with/load/withCount('documents')` in `app/Livewire`, PM API) | none |

Two audit-driven release gates were **mutation-tested** (proved they fail on a real regression, then restored):
- Audit log — breaking the emitted event name turned the upload-audit test red.
- External isolation — adding `->load('documents')` onto `ExternalDashboard`'s `$project` turned the `relationLoaded('documents')` assertion red.

### Implementation notes / discoveries
- **SQLite test shim:** `ExternalDashboard` uses MySQL-only `DATE_FORMAT()` (it is unrunnable under the SQLite test DB — which is why no prior tests covered it). The isolation test registers a narrow `DATE_FORMAT` SQLite UDF in `beforeEach` (test infra only) so the dashboard renders and real output can be asserted. The production dashboard SQL was left untouched (out of scope).
- **Livewire v3 does not auto-serialize loaded relations** into the component snapshot, so a loaded-but-unrendered `documents` relation would not surface via `assertDontSee`. The load-bearing isolation guard is therefore the structural `relationLoaded('documents')->toBeFalse()` assertion (plus `getEagerLoads()` for global eager-loading and `assertDontSee` for the rendered view). `ProjectDocument::$hidden` covers `file_path`/`file_hash`/`disk` regardless.

## Deviations from the prompt
- **Archive route consolidated into the Filament action** (no standalone `POST/DELETE .../archive` web route). Rationale: archive is an admin state-change, not a file endpoint; keeping it out of public routing shrinks attack surface. Same `ProjectDocumentStorageService::archive()` + policy `delete` ability back both. View/download (the data-exfil endpoints) remain explicit, policy-gated routes — they are what the external-access test must prove unreachable.
- **Category as validated string column** (not a DB enum). Matches the codebase's `attachment_role`/`group` string-constant idiom and avoids enum migration lock-in. App-level allowlist + Filament Select enforce the fixed set.

## Unresolved Questions
1. Role mapping: which existing role is "responsible internal team" for create/download? Provisional grant = super_admin + `manager` + `head-division` (+ `hr-admin`). Confirm against the real Shield role list (`view_any_project` holders).
2. Should `head-division`/`manager` get `delete_project_document` (archive), or restrict archive to super_admin + C-Level? Provisional: grant to manager-level; tighten if required.
3. Inline image preview content-types — restrict to png/jpeg only (current allowlist) or also accept future formats? Provisional: png/jpeg/pdf inline; everything else attachment.
4. Retention/purge of archived files — out of MVP; needs a later `project-documents:purge` command + policy `forceDelete` if hard-delete is ever required.
