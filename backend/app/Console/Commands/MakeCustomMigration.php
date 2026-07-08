<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeCustomMigration extends Command
{

    private const PATTERN = [
        'valid_filename' => '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/',
        'file_prefix' => '/^(\d{3})_/',
        'create_table' => '/^create_(.+)_table$/',
        'update_table1' => '/_(to)_/',
        'update_table2' => '/^add_(.+)_to_(.+)$/',

    ];
    protected MigrationCreator $creator;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:cmigration {name? : The name of the migration} {--table= : The table name (for update)} {--create= : The table name to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prompt for a migration name and create a new migration file';

    public function __construct() {
        parent::__construct();
    }

    /**
     * Handle the CLI command to generate a migration file.
     *
     * Collect and validates the migration name, detects whether the stub should be for
     * - create
     * - update
     *  - blank migrations, and computes the next migration filename prefix.
     * Then fill the stub placeholders, and writes the migration file to `database/migrations`.
     *
     * @return int Command exit code (0 success, 1 failure).
     */
    public function handle(): int
    {
        $this->creator = app()->makeWith(MigrationCreator::class, ['customStubPath' => null]);

        $name = $this->argument('name') ?? $this->ask('Enter the migartion name (e.g: add_columns_to_documents_table)');

        if (empty($name)) {
            $this->error('Migration name cannot be empty.');
            return 1;
        }

        // Filename validation check
        if (!preg_match(self::PATTERN['valid_filename'], $name)) {
            $this->error('Invalid migration name. Use CamelCase or snake_case pattern.');
            return 1;
        }

        // Check for existing migrations with the same name
        $existing = File::glob(database_path('migrations/*_' . $name . '.php'));
        if (!empty($existing)) {
            $this->error("A migration with the name: {$name} already exists. Please, try again with another name");
            return 1;
        }

        $prefix = $this->getPrecentPrefix();

        if (!$this->option('create') && !$this->option('table')) {
            $detected = $this->detectStubType($name);

            $create = false;
            $table = null;

            switch ($detected['type']) {
                case 'create':
                    $create = true;
                    break;
                case 'update':
                    $table = $detected['table'];
                    break;
            }
        } else {
            $table = $this->option('create') ?: $this->option('table');
            $create = (bool) $this->option('create');
        }

        $class = Str::studly($name);

        $stub = $this->getStub($this->creator, $table, $create);
        $stub = str_replace('{{ class }}', $class, $stub);
        $stub = str_replace('{{ table }}', $table ?? '', $stub);

        $filename = $prefix . '_' . $name . '.php';
        $path = database_path('migrations/' . $filename);

        if (File::exists($path)) {
            $this->error("A migration with prefix {$prefix} already exists. Please run again.");
            return 1;
        }

        File::put($path, $stub);

        $this->info("Migration successfully created: {$filename}");
        return  0;
    }

    /**
     * Compute the next numeric migration prefix based on existing filenames.
     *
     * First, Scan `database/migrations`, extracts the numeric prefix using the configured pattern,
     * Second, return the next incremented value as a 3-digit, zero-padded string.
     *
     * @return string Next migration prefix (e.g., "001", "012").
     */
    private function getPrecentPrefix(): string
    {
        $path = database_path('migrations');
        $files = File::files($path);

        $max_value = 0;
        $value_border = 900;


        foreach($files as $file) {
            $filename =$file->getFilename();
            if (preg_match(self::PATTERN['file_prefix'], $filename, $matches)) {
                $prefix_value = (int) $matches[1];

                if ($prefix_value > $max_value && $prefix_value < $value_border) {
                    $max_value = $prefix_value;
                }
            }
        }

        return str_pad($max_value + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve and load the appropriate stub template for a migration.
     *
     * Choose the stub file based on whether the migration is a create migration or an update
     * migration tied to a specific table.
     *
     * @param MigrationCreator $creator Stub provider.
     * @param string|null $table Target table for update migrations (null for create/blank).
     * @param bool $create Whether the migration should use the create stub.
     * @return string Stub contents.
     */
    private function getStub(MigrationCreator $creator, ?string $table, bool $create): string
    {
        $stub_path = $creator->stubPath();

        $stub =  $stub_path . '/migration.' . match (true) {
            $create === true => 'create.stub',
            $table !== null => 'update.stub',
            default => 'stub'
        };

        return File::get($stub);
    }

    /**
     * Detect the migration type and (when applicable) extract the target table from the name.
     *
     * - Returns `create` when the name matches the create pattern.
     * - Returns `update` when the name indicates add/to or alter operations.
     * - Otherwise returns `blank`.
     *
     * @param string $name Migration name.
     * @return array{type: string, table: string|null} Detected migration metadata.
     */
    private function detectStubType(string $name): array
    {
        // Check: 'create'
        if (Str::startsWith($name, 'create_') && str_contains($name, '_table')) {
            return ['type' => 'create', 'table' => $this->extractTableName($name, 'create')];
        }

        // Check: add/to (update)
        if (Str::contains($name, '_to_') || Str::startsWith($name, 'add_') || Str::startsWith($name, 'alter_')) {
            $table = $this->extractTableName($name, 'update');
            return ['type' => 'update', 'table' => $table];
        }

        // Default to blank
        return ['type' => 'blank', 'table' => null];
    }

    /**
     * Extract the table name from a migration name based on the expected migration type.
     *
     * @param string $name Migration name.
     * @param string $type Expected type context ('create' or 'update').
     * @return string|null Extracted table name, or null if it cannot be determined.
     */
    private function extractTableName(string $name, string $type): ?string
    {
        switch ($type) {
            case 'create':
                if (preg_match(self::PATTERN['create_table'], $name, $matches)) {
                    return $matches[1];
                }
            case 'update':
                if (preg_match(self::PATTERN['update_table1'], $name, $matches)) {
                    $parts = explode('_to_', $name);
                    return str_replace('_table', '', $parts[1]) ?? null;
                }

                if (preg_match(self::PATTERN['update_table2'], $name, $matches)) {
                    return $matches[2];
                }
        };
        return null;
    }
}
