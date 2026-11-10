<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RestorePerformanceIndexes extends Command
{
    protected $signature = 'performance:restore-indexes';

    protected $description = 'Restore the non-unique indexes captured by the isolated performance baseline.';

    public function handle(): int
    {
        if (!config('app.performance_testing') || app()->environment('production')) {
            $this->error('Enable PERFORMANCE_TESTING outside production before restoring indexes.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        $database = (string) $connection->getDatabaseName();

        

        $snapshotPath = storage_path('app/performance-indexes-snapshot.json');
        $snapshot = [];
        if (is_file($snapshotPath)) {
            $contents = file_get_contents($snapshotPath);
            if ($contents === false) {
                $this->error("Unable to read index snapshot at {$snapshotPath}.");

                return self::FAILURE;
            }

            try {
                $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                $this->error('Index snapshot is not valid JSON: ' . $exception->getMessage());

                return self::FAILURE;
            }
        }

        if (!is_array($snapshot)) {
            $this->error('Index snapshot has an invalid structure.');

            return self::FAILURE;
        }

        $migrationIndexes = [
            'users' => ['users_role_index' => ['role']],
            'products' => ['products_category_id_is_active_id_index' => ['category_id', 'is_active', 'id']],
            'product_images' => ['product_images_product_id_sort_order_index' => ['product_id', 'sort_order']],
            'orders' => [
                'orders_user_id_created_at_index' => ['user_id', 'created_at'],
                'orders_status_created_at_index' => ['status', 'created_at'],
            ],
            'order_items' => [
                'order_items_order_id_id_index' => ['order_id', 'id'],
                'order_items_product_id_index' => ['product_id'],
            ],
        ];
        $indexDefinitions = array_replace_recursive($migrationIndexes, $snapshot);

        $tables = ['categories', 'users', 'products', 'product_images', 'orders', 'order_items'];
        $existingIndexes = $this->existingIndexes($connection, $tables);
        $restored = 0;
        $alreadyPresent = 0;

        foreach ($indexDefinitions as $table => $tableIndexes) {
            if (!in_array($table, $tables, true) || !is_array($tableIndexes)) {
                $this->error("Unexpected table entry in index snapshot: {$table}");

                return self::FAILURE;
            }

            foreach ($tableIndexes as $name => $columns) {
                if (!$this->validIdentifier((string) $name)
                    || !is_array($columns)
                    || $columns === []
                    || count(array_filter($columns, fn ($column) => $this->validIdentifier((string) $column))) !== count($columns)) {
                    $this->error("Invalid index definition for {$table}.{$name} in snapshot.");

                    return self::FAILURE;
                }

                $columns = array_values($columns);
                $presentColumns = $existingIndexes[$table][$name] ?? null;

                if ($presentColumns !== null) {
                    if ($presentColumns !== $columns) {
                        $this->error("Index {$table}.{$name} already exists with different columns.");

                        return self::FAILURE;
                    }

                    $alreadyPresent++;
                    continue;
                }

                $quotedColumns = implode(', ', array_map(
                    fn (string $column) => $this->quoteIdentifier($column),
                    $columns
                ));
                $connection->statement(sprintf(
                    'ALTER TABLE %s ADD INDEX %s (%s)',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier((string) $name),
                    $quotedColumns
                ));

                $restored++;
            }
        }

        $removedTemporaryIndexes = $this->dropRedundantForeignKeySupportIndexes($connection, $tables);

        $this->info(sprintf(
            'Restored %d indexes in %s; %d were already present; removed %d temporary foreign-key support indexes. Primary keys, unique indexes, and foreign keys were not changed.',
            $restored,
            $database,
            $alreadyPresent,
            $removedTemporaryIndexes
        ));

        return self::SUCCESS;
    }

    private function existingIndexes($connection, array $tables): array
    {
        $placeholders = implode(', ', array_fill(0, count($tables), '?'));
        $rows = $connection->select(
            "SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ({$placeholders})
            ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
            $tables
        );

        $indexes = [];
        foreach ($rows as $row) {
            $indexes[$row->TABLE_NAME][$row->INDEX_NAME][] = $row->COLUMN_NAME;
        }

        return $indexes;
    }

    private function dropRedundantForeignKeySupportIndexes($connection, array $tables): int
    {
        $placeholders = implode(', ', array_fill(0, count($tables), '?'));
        $foreignKeyRows = $connection->select(
            "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ({$placeholders})
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION",
            $tables
        );

        $foreignKeys = [];
        foreach ($foreignKeyRows as $row) {
            $foreignKeys[$row->TABLE_NAME][$row->CONSTRAINT_NAME][] = $row->COLUMN_NAME;
        }

        $indexes = $this->existingIndexes($connection, $tables);
        $dropped = 0;

        foreach ($indexes as $table => $tableIndexes) {
            $redundant = [];

            foreach ($tableIndexes as $name => $columns) {
                if (!str_starts_with($name, 'perf_fk_')) {
                    continue;
                }

                foreach ($foreignKeys[$table] ?? [] as $foreignKeyColumns) {
                    $hasAlternative = false;
                    foreach ($tableIndexes as $otherName => $otherColumns) {
                        if ($otherName === $name) {
                            continue;
                        }

                        if (array_slice($otherColumns, 0, count($foreignKeyColumns)) === $foreignKeyColumns) {
                            $hasAlternative = true;
                            break;
                        }
                    }

                    if ($hasAlternative && $columns === $foreignKeyColumns) {
                        $redundant[] = $name;
                        break;
                    }
                }
            }

            foreach (array_unique($redundant) as $name) {
                $connection->statement(sprintf(
                    'ALTER TABLE %s DROP INDEX %s',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier($name)
                ));
                $dropped++;
            }
        }

        return $dropped;
    }

    private function validIdentifier(string $identifier): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/D', $identifier) === 1;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
