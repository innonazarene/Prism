# Prism Init

[![CI](https://github.com/innonazarene/prism-init/actions/workflows/ci.yml/badge.svg)](https://github.com/innonazarene/prism-init/actions)
[![Latest Version](https://img.shields.io/packagist/v/innonazarene/prism-init.svg)](https://packagist.org/packages/innonazarene/prism-init)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-9--12-FF2D20)](https://laravel.com)
[![License](https://img.shields.io/github/license/innonazarene/prism-init)](https://github.com/innonazarene/prism-init/blob/main/LICENSE)

**Prism Init** scaffolds a complete, convention-following backend API from your existing database in one command.


---

## What gets generated per table

| Artifact | Path (grouped, default) |
|---|---|
| Eloquent Model | `app/Models/Employee.php` |
| Service | `app/Services/EmployeeService.php` |
| Controller | `app/Http/Controllers/Api/V1/Employee/EmployeeController.php` |
| Store Request | `app/Http/Requests/Employee/StoreEmployeeRequest.php` |
| Update Request | `app/Http/Requests/Employee/UpdateEmployeeRequest.php` |
| API Resource | `app/Http/Resources/Employee/EmployeeResource.php` |
| Policy | `app/Policies/EmployeePolicy.php` |
| API Routes | `routes/api.php` — `Route::apiResource('employees', …)` |

**Plus, once:**

| Artifact | Path |
|---|---|
| `ApiResponse` Trait | `app/Traits/ApiResponse.php` |

---

## Conventions followed

### Database (3.3.1)
- Table names: `snake_case`, plural (`employees`, `salary_grades`)
- Model names: `PascalCase`, singular (`Employee`)
- Soft deletes: `deleted_at` on every model (configurable)
- Timestamps: `created_at`, `updated_at` on every model

### PHP / Laravel (3.3.2)
- **Models**: `PascalCase` singular, `$fillable`, `$casts`, `SoftDeletes`
- **Controllers**: thin — validate → delegate to Service → return Resource
- **Services**: all business logic inside `DB::transaction()` where needed
- **Requests**: `Store{Model}Request` / `Update{Model}Request` with commented validation guidelines
- **Resources**: `{Model}Resource` shaping JSON output
- **Policies**: `{Model}Policy` with all standard gates stubbed
- **Response envelope**: consistent `{ success, data, message, errors }` via `ApiResponse` trait

---

## Requirements

| | |
|---|---|
| PHP | ^8.1 |
| Laravel | ^9 \| ^10 \| ^11 \| ^12 |

---

## Installation

```bash
composer require innonazarene/prism-init --dev
```

Publish the config (optional but recommended):

```bash
php artisan vendor:publish --tag=prism-init-config
```

Publish stubs to customise them (optional):

```bash
php artisan vendor:publish --tag=prism-init-stubs
# → published to stubs/prism-init/
```

---

## Usage

```bash
php artisan prism:init
```

### Options

| Option | Description |
|---|---|
| `--prefix=v1` | Route prefix (default: `v1` → `/api/v1/…`) |
| `--grouped` | Force grouped folder structure |
| `--flat` | Force flat folder structure |
| `--no-timestamps` | Disable `$timestamps` on models |
| `--no-soft-deletes` | Skip `SoftDeletes` on models |
| `--skip-migrate` | Skip migration generation |
| `--skip-seed` | Skip seeding |
| `--skip-services` | Skip Service class generation |
| `--skip-resources` | Skip API Resource generation |
| `--skip-policies` | Skip Policy generation |
| `--tables=users,products` | Only scaffold listed tables |
| `--force` | Overwrite existing files |

### Examples

```bash
# Full scaffold, version prefix v2
php artisan prism:init --prefix=v2

# Only two tables, flat structure
php artisan prism:init --tables=orders,products --flat

# Skip migrations and seeds (DB already set up)
php artisan prism:init --skip-migrate --skip-seed

# No soft deletes, no policies
php artisan prism:init --no-soft-deletes --skip-policies
```

---

## Generated output example

Given a `departments` table, Prism Init produces:

**`app/Http/Controllers/Api/V1/Department/DepartmentController.php`**
```php
class DepartmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DepartmentService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->list($request->all());
        return $this->successResponse(DepartmentResource::collection($data), 'Department list retrieved.');
    }

    // store / show / update / destroy …
}
```

**`app/Traits/ApiResponse.php`** — envelope methods:
```php
// Success:   { success: true,  data: T,    message: string, errors: null }
// Error:     { success: false, data: null, message: string, errors: object }
// Paginated: { success: true,  data: T[],  meta: { current_page, last_page, per_page, total }, … }
```

**`routes/api.php`**
```php
use App\Http\Controllers\Api\V1\Department\DepartmentController;

Route::prefix('v1')->group(function () {
    Route::apiResource('departments', DepartmentController::class);
});
```

---

## Customising stubs

After publishing stubs (`--tag=prism-init-stubs`), edit any file in `stubs/prism-init/`.
Prism Init always prefers your published stub over the package default.

Available stubs:

| Stub | Purpose |
|---|---|
| `model.stub` | Eloquent model |
| `service.stub` | Service class |
| `controller.stub` | API controller |
| `store-request.stub` | Store Form Request |
| `update-request.stub` | Update Form Request |
| `resource.stub` | API Resource |
| `policy.stub` | Policy |
| `api-response.stub` | `ApiResponse` trait |

---

## Third-Party Packages Used

| Package | Purpose |
|---|---|
| [kitloong/laravel-migrations-generator](https://github.com/kitloong/laravel-migrations-generator) | Generate migrations from existing DB |
| [orangehill/iseed](https://github.com/orangehill/iseed) | Generate seeders from live data |
| [krlove/eloquent-model-generator](https://github.com/krlove/eloquent-model-generator) | Generate Eloquent models with relationships |

---

## Backup & re-running

On every run, Prism saves clean copies of `routes/api.php` and `Controller.php` to `public/backup/`
and restores them before regenerating — so you always start from a clean slate.

---

## Contributing

Pull requests welcome. Please open an issue first to discuss changes.

```bash
git checkout -b feat/my-feature
# … make changes …
vendor/bin/phpunit
git push && open PR
```

---

## License

MIT — see [LICENSE](LICENSE).