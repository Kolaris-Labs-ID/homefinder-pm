# Project Structure

## Directory Organization

### Application Layer (`app/`)

**Console Commands** (`app/Console/Commands/`)

-   Artisan commands for scheduled tasks and utilities
-   Examples: `CheckOverdueItems`, `CreateAdminUser`, `UpdateGoogleUserAvatars`

**Events & Listeners** (`app/Events/`, `app/Listeners/`)

-   Domain events (e.g., `ProjectMemberAttached`, `ProjectMemberDetached`)
-   Event listeners for notifications and side effects

**Filament** (`app/Filament/`)

-   `Actions/` - Reusable Filament actions (exports, imports)
-   `Pages/` - Custom Filament pages (boards, timelines, leaderboards)
-   `Resources/` - CRUD resources with subdirectories for Pages/RelationManagers
-   `Widgets/` - Dashboard widgets (charts, stats, calendars)

**HTTP Layer** (`app/Http/`)

-   `Controllers/` - API and auth controllers
-   `Middleware/` - Custom middleware (e.g., `SetLocale`)

**Models** (`app/Models/`)

-   Domain models at root level (Project, Ticket, User, etc.)
-   `Hris/` subdirectory for HRIS-specific models (Employee, Department, LeaveRequest, etc.)
-   Models use Eloquent relationships and scopes

**Policies** (`app/Policies/`)

-   Authorization policies for each resource
-   `Hris/` subdirectory for HRIS policies
-   Integrated with Filament Shield permissions

**Services** (`app/Services/`)

-   Business logic encapsulation
-   `Hris/` subdirectory for HRIS services (LeaveBalanceService, LeaveWorkflowService)
-   Use service classes for complex operations

**Other**

-   `Exports/` - Excel export classes
-   `Imports/` - Excel import classes
-   `Livewire/` - Custom Livewire components
-   `Mail/` - Mailable classes for notifications
-   `Helpers/` - Utility classes (e.g., `RoleHelper`)

### Database (`database/`)

-   `factories/` - Model factories (organized with `Hris/` subdirectory)
-   `migrations/` - Database migrations (chronologically ordered)
-   `seeders/` - Database seeders (AdminSeeder, RoleSeeder, HrisSeeder)

### Resources (`resources/`)

-   `css/` - Stylesheets (Tailwind)
-   `js/` - JavaScript assets
-   `views/` - Blade templates
    -   `filament/` - Filament view overrides
    -   `livewire/` - Livewire component views
    -   `emails/` - Email templates
    -   `vendor/` - Published vendor views

### Tests (`tests/`)

-   `Feature/` - Integration tests (organized with `Api/` and `Hris/` subdirectories)
-   `Unit/` - Unit tests (organized with `Hris/` subdirectory)
-   Uses Pest PHP testing framework

### Configuration (`config/`)

-   Standard Laravel config files
-   Notable: `filament-shield.php`, `permission.php`, `excel.php`, `octane.php`

## Naming Conventions

**Models**

-   Singular, PascalCase (e.g., `Project`, `Ticket`, `LeaveRequest`)
-   HRIS models namespaced under `App\Models\Hris\`

**Controllers**

-   Singular resource name + "Controller" (e.g., `ProjectController`)
-   API controllers in `Api/` subdirectory

**Filament Resources**

-   Singular model name + "Resource" (e.g., `ProjectResource`, `EmployeeResource`)
-   Subdirectories for Pages and RelationManagers

**Policies**

-   Singular model name + "Policy" (e.g., `ProjectPolicy`, `EmployeePolicy`)
-   Methods follow Filament Shield conventions (viewAny, view, create, update, delete, etc.)

**Services**

-   Descriptive name + "Service" (e.g., `LeaveBalanceService`, `NotificationService`)

**Database Tables**

-   Plural, snake_case (e.g., `projects`, `tickets`, `leave_requests`)
-   Pivot tables: alphabetically ordered model names (e.g., `project_members`, `ticket_users`)

**Migrations**

-   Format: `YYYY_MM_DD_HHMMSS_description.php`
-   Descriptive action names (e.g., `create_projects_table`, `add_division_to_users_table`)

## Key Patterns

**Filament Resources**

-   Form schema in `form()` method
-   Table columns in `table()` method
-   Relation managers for related data
-   Custom pages for specialized views (boards, timelines)

**Authorization**

-   Policies check Filament Shield permissions via `$user->can('permission_name')`
-   Permission naming: `{action}_{resource}` (e.g., `view_any_project`, `create_employee`)

**Relationships**

-   Use Eloquent relationships in models
-   Leverage relation managers in Filament resources
-   Pivot tables for many-to-many relationships

**API Structure**

-   RESTful endpoints under `/api/hris/`
-   Sanctum authentication required
-   Return JSON responses

**Queues**

-   Email notifications queued for async processing
-   Queue connection: database (configurable)
-   Supervisor for production queue workers

**Modular HRIS**

-   HRIS functionality organized in subdirectories
-   Separate namespace for HRIS models, policies, services
-   API endpoints prefixed with `/api/hris/`
