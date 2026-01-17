<?php

declare(strict_types=1);

namespace LaraForge\Security;

/**
 * Database Portability Guard
 *
 * Ensures generated database queries and migrations work across
 * different database environments (SQLite, MySQL, PostgreSQL, SQL Server).
 *
 * This guard helps prevent issues when porting projects between environments,
 * such as development (SQLite) to production (MySQL/PostgreSQL).
 */
final class DatabasePortabilityGuard
{
    /**
     * @var array<string, array<string, string>>
     */
    private const INCOMPATIBLE_FUNCTIONS = [
        'mysql_only' => [
            'IFNULL' => 'Use COALESCE() which is supported across all databases',
            'IF()' => 'Use CASE WHEN ... THEN ... ELSE ... END instead',
            'CONCAT_WS' => 'Use multiple CONCAT() calls or handle in PHP',
            'GROUP_CONCAT' => 'Use STRING_AGG (PostgreSQL) or GROUP_CONCAT (MySQL) via raw expressions with driver checks',
            'RAND()' => 'Use RANDOM() for PostgreSQL, RAND() for MySQL - abstract via scope',
            'NOW()' => 'Use Carbon::now() in PHP or database-agnostic current_timestamp',
            'DATE_FORMAT' => 'Use Carbon formatting in PHP after retrieval',
            'UNIX_TIMESTAMP' => 'Use strtotime() in PHP or database-agnostic methods',
        ],
        'postgresql_only' => [
            'STRING_AGG' => 'Abstract via scope or use database driver checks',
            'ARRAY_AGG' => 'Handle aggregation in PHP for portability',
            '::' => 'PostgreSQL type casting - use database-agnostic CAST()',
        ],
        'sqlite_only' => [
            'strftime' => 'Use Carbon for date formatting in PHP',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const MIGRATION_WARNINGS = [
        'json' => 'JSON columns: SQLite stores as TEXT, ensure proper casting',
        'enum' => 'ENUM columns: Not natively supported in SQLite, consider using string with validation',
        'unsigned' => 'Unsigned integers: Not supported in PostgreSQL, SQLite handles implicitly',
        'binary' => 'Binary columns: Behavior varies by database, test across environments',
        'spatial' => 'Spatial types: Not universally supported, use dedicated packages',
        'fulltext' => 'Fulltext indexes: MySQL/PostgreSQL only, use Laravel Scout for portability',
        'boolean' => 'Boolean columns: SQLite uses integers (0/1), handled by Eloquent casts',
    ];

    /**
     * @var array<string, string>
     */
    private array $violations = [];

    /**
     * Validate code for database portability issues.
     *
     * @return array<string, string>
     */
    public function validate(string $code): array
    {
        $this->violations = [];

        $this->checkIncompatibleFunctions($code);
        $this->checkRawQueries($code);
        $this->checkMigrationIssues($code);
        $this->checkQueryBuilderIssues($code);

        return $this->violations;
    }

    /**
     * Check if code passes portability checks.
     */
    public function passes(string $code): bool
    {
        return count($this->validate($code)) === 0;
    }

    /**
     * Get recommendations for writing portable database code.
     *
     * @return array<string, string>
     */
    public function getRecommendations(): array
    {
        return [
            'use_eloquent' => 'Prefer Eloquent methods over raw SQL for automatic query adaptation',
            'avoid_raw' => 'Minimize DB::raw() usage; when needed, handle per-driver in a scope',
            'test_sqlite' => 'Use SQLite for testing to catch portability issues early',
            'use_carbon' => 'Use Carbon for date/time operations instead of database functions',
            'abstract_complex' => 'Abstract complex queries into dedicated Query classes',
            'use_cqrs' => 'Follow CQRS pattern: separate read queries from write operations',
            'driver_checks' => 'Use DB::getDriverName() for driver-specific code paths',
            'avoid_procedures' => 'Avoid stored procedures for maximum portability',
            'use_migrations' => 'Use migrations with database-agnostic column types',
            'test_all_drivers' => 'Test against all target database drivers in CI',
        ];
    }

    /**
     * Get the CQRS architecture pattern recommendations.
     *
     * @return array<string, array<string, string>>
     */
    public function getCqrsPattern(): array
    {
        return [
            'queries' => [
                'purpose' => 'Encapsulate read operations in dedicated Query classes',
                'location' => 'app/Queries or domain/Queries',
                'naming' => 'GetUserByEmailQuery, ListActiveOrdersQuery',
                'return' => 'Return DTOs or Eloquent models/collections',
                'example' => 'Inject into controllers, call execute() method',
            ],
            'actions' => [
                'purpose' => 'Encapsulate business logic and write operations',
                'location' => 'app/Actions or domain/Actions',
                'naming' => 'CreateUserAction, UpdateOrderStatusAction',
                'return' => 'Return result objects with success/failure state',
                'example' => 'Single responsibility, one public execute() method',
            ],
            'controllers' => [
                'purpose' => 'Handle HTTP layer only: input, invoke action, return response',
                'location' => 'app/Http/Controllers',
                'naming' => 'UserController, OrderController',
                'return' => 'JSON responses or view redirects',
                'example' => 'Thin controllers, delegate to Actions and Queries',
            ],
            'benefits' => [
                'testability' => 'Each component can be unit tested in isolation',
                'portability' => 'Database logic centralized, easier to adapt per driver',
                'maintainability' => 'Clear separation of concerns',
                'scalability' => 'Can split read/write to different databases',
            ],
        ];
    }

    /**
     * Generate a portable query scope template.
     */
    public function getPortableScopeTemplate(string $scopeName): string
    {
        return <<<PHP
/**
 * Scope for {$scopeName} - handles database driver differences.
 */
public function scope{$scopeName}(Builder \$query, mixed \$value): Builder
{
    \$driver = \$query->getConnection()->getDriverName();

    return match (\$driver) {
        'mysql' => \$query->whereRaw('/* MySQL specific */', [\$value]),
        'pgsql' => \$query->whereRaw('/* PostgreSQL specific */', [\$value]),
        'sqlite' => \$query->whereRaw('/* SQLite specific */', [\$value]),
        default => \$query->where('column', \$value),
    };
}
PHP;
    }

    /**
     * Generate a Query class template following CQRS.
     */
    public function getQueryClassTemplate(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Queries;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * {$name}
 *
 * Encapsulates the read query logic for portability across database drivers.
 * Part of CQRS pattern - separates reads from writes.
 */
final readonly class {$name}
{
    public function __construct(
        private Builder \$query,
    ) {}

    /**
     * Execute the query and return results.
     *
     * @return Collection
     */
    public function execute(): Collection
    {
        return \$this->query
            ->select(\$this->getSelectColumns())
            ->when(\$this->hasConditions(), fn (\$q) => \$this->applyConditions(\$q))
            ->get();
    }

    /**
     * Get columns to select - override in subclasses.
     */
    protected function getSelectColumns(): array
    {
        return ['*'];
    }

    /**
     * Check if conditions should be applied.
     */
    protected function hasConditions(): bool
    {
        return false;
    }

    /**
     * Apply query conditions - database-agnostic.
     */
    protected function applyConditions(Builder \$query): Builder
    {
        return \$query;
    }
}
PHP;
    }

    /**
     * Generate an Action class template following CQRS.
     */
    public function getActionClassTemplate(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\DB;

/**
 * {$name}
 *
 * Encapsulates the write/mutation logic.
 * Part of CQRS pattern - handles state changes.
 */
final readonly class {$name}
{
    /**
     * Execute the action.
     *
     * @return ActionResult
     */
    public function execute(array \$data): ActionResult
    {
        return DB::transaction(function () use (\$data) {
            // Validate input
            \$validated = \$this->validate(\$data);

            // Perform the action
            \$result = \$this->perform(\$validated);

            // Return structured result
            return ActionResult::success(\$result);
        });
    }

    /**
     * Validate input data.
     */
    private function validate(array \$data): array
    {
        // Add validation logic
        return \$data;
    }

    /**
     * Perform the core action logic.
     */
    private function perform(array \$data): mixed
    {
        // Add business logic
        return null;
    }
}
PHP;
    }

    private function checkIncompatibleFunctions(string $code): void
    {
        foreach (self::INCOMPATIBLE_FUNCTIONS as $dialect => $functions) {
            foreach ($functions as $function => $recommendation) {
                if (preg_match('/\b'.preg_quote($function, '/').'\s*\(/i', $code)) {
                    $this->violations["incompatible-{$function}"] =
                        "{$dialect}: {$function}() detected. {$recommendation}";
                }
            }
        }
    }

    private function checkRawQueries(string $code): void
    {
        // Check for excessive raw SQL
        $rawCount = preg_match_all('/DB::raw\s*\(/i', $code);
        if ($rawCount > 3) {
            $this->violations['excessive-raw'] =
                "Found {$rawCount} DB::raw() calls. Consider abstracting into Query classes for better portability.";
        }

        // Check for raw queries without parameter binding
        if (preg_match('/DB::(?:select|insert|update|delete)\s*\([^)]*\$[a-zA-Z]/i', $code)) {
            $this->violations['raw-without-binding'] =
                'Raw query with variable interpolation detected. Use parameter binding for security and portability.';
        }
    }

    private function checkMigrationIssues(string $code): void
    {
        foreach (self::MIGRATION_WARNINGS as $type => $warning) {
            if (preg_match('/->'.$type.'\s*\(/i', $code)) {
                $this->violations["migration-{$type}"] = $warning;
            }
        }

        // Check for tinyInteger with MySQL-specific behavior
        if (preg_match('/->tinyInteger\s*\([^)]*\).*->unsigned\s*\(\)/i', $code)) {
            $this->violations['tinyint-unsigned'] =
                'Unsigned tinyInteger may behave differently across databases.';
        }
    }

    private function checkQueryBuilderIssues(string $code): void
    {
        // Check for LIKE with case sensitivity issues
        if (preg_match('/->where\s*\([^)]*[\'"]LIKE[\'"]/i', $code)) {
            $this->violations['like-case-sensitivity'] =
                'LIKE is case-insensitive in MySQL but case-sensitive in PostgreSQL. Use ILIKE scope or lower() for consistency.';
        }

        // Check for boolean comparisons
        if (preg_match('/->where\s*\([^,]+,\s*(?:true|false)\s*\)/i', $code)) {
            $this->violations['boolean-comparison'] =
                'Boolean comparisons may vary across databases. Use explicit 1/0 or Eloquent casts.';
        }
    }
}
