# Company features

One codebase serves several companies, each with its own deployment and database.
Where companies differ in **policy** (whether they run recruitment, whether staff
sign in with Google, whether overtime is capped), the difference is a feature flag.
Bug fixes are never flagged: every company gets them.

Each company chooses every flag **once, at first setup**. The choice is stored in
its database (`app_features`) and locked.

## Flags

| Key | Off means |
|---|---|
| `module.recruitment` | No job postings, applicants, interviews, résumé screening or public job API (`/api/public/v1/*` answers 404). |
| `auth.google_login` | No Google button on the admin login, and `/auth/google*` and `POST /api/auth/google` answer 404. |
| `payroll.overtime_statutory_caps` | Weekday overtime is paid for every approved minute instead of up to 4 h/day and 18 h/week. |

The list lives in `app/Features/Feature.php`. `GET /api/features` returns every
flag's value without login, so the mobile app can adapt before sign-in.

## Setting up a company

**Existing company moving onto this codebase.** Set `APP_FEATURE_PRESET` in its
deployment env (`homefinder`, `partnershub` or `kgtb`, see `config/features.php`),
and run `php artisan app:init` after migrations on every deploy:

```sh
php artisan migrate --force && php artisan app:init --no-interaction
```

`app:init` stores the preset's value for each flag that has none yet and never
touches a flag that is already set, so it is safe on every deploy. Until it runs,
the preset applies directly. Each preset matches what that company's code did
before flags existed, so the move changes nothing they see.

**New company.** Leave `APP_FEATURE_PRESET` empty. Then either:

- a super admin opens **Settings → Company Features** in the admin panel, chooses
  each flag and clicks **Save and lock**, or
- run `php artisan app:init` in a terminal and answer each question.

Until then, each flag uses its default (the behaviour before the flag existed).

`php artisan features:status` shows every flag, its value and where it came from.

## Changing a locked flag

Only when the company's policy really changed:

```sh
php artisan features:change payroll.overtime_statutory_caps on --reason="Adopted PP 35/2021 caps from 1 Dec"
```

The change is recorded in `app_feature_changes`. For a payroll flag, run it between
pay periods, never while a period is being calculated.

## Adding a flag

1. Add a case to `App\Features\Feature` with a label, a description, and a
   `default()` equal to what the code did before the flag.
2. Add the key to **every** preset in `config/features.php`
   (`FeatureManagerTest` fails if one is missing).
3. Gate the code paths:
   - routes: `->middleware('feature:<key>')`
   - Filament resources and pages: `use RequiresFeature;` plus `requiredFeature()`
   - anything else: `Features::active(Feature::X)`
4. Test both values.

Deployments that already ran `app:init` pick up the new flag from their preset on
the next deploy. Without a preset, it shows up on the Company Features page.
