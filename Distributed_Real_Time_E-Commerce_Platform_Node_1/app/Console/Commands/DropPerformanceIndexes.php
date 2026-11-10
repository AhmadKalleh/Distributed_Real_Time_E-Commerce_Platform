<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DropPerformanceIndexes extends Command
{
    protected $signature = 'performance:baseline:drop-indexes';

    protected $description = 'Drop non-unique secondary indexes from the isolated performance database.';

    public function handle(): int
    {
        if (!config('app.performance_testing') || app()->environment('production')) {
            $this->error('Enable PERFORMANCE_TESTING outside production before running this command.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        // if ($connection->getDriverName() !== 'mysql' || !str_contains(strtolower($database), 'performance')) {
        //     $this->error('This command only runs against a MySQL database with "performance" in its name.');

        //     return self::FAILURE;
        // }

        $tables = ['categories', 'users', 'products', 'product_images', 'orders', 'order_items'];
        $placeholders = implode(', ', array_fill(0, count($tables), '?'));

        $foreignKeyRows = $connection->select(
            "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IS NOT NULL
                AND TABLE_NAME IN ({$placeholders})
            ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION",
            $tables
        );

        $foreignKeys = [];
        foreach ($foreignKeyRows as $row) {
            $foreignKeys[$row->TABLE_NAME][$row->CONSTRAINT_NAME][] = $row->COLUMN_NAME;
        }

        $indexRows = $connection->select(
            "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ({$placeholders})
            ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
            $tables
        );

        $indexes = [];
        foreach ($indexRows as $row) {
            $indexes[$row->TABLE_NAME][$row->INDEX_NAME]['non_unique'] = (bool) $row->NON_UNIQUE;
            $indexes[$row->TABLE_NAME][$row->INDEX_NAME]['columns'][] = $row->COLUMN_NAME;
        }

        $dropByTable = [];
        $addByTable = [];
        $snapshot = [];

        foreach ($foreignKeys as $table => $constraints) {
            foreach ($constraints as $columns) {
                $hasMinimalSupportingIndex = false;

                foreach ($indexes[$table] ?? [] as $index) {
                    if (array_slice($index['columns'], 0, count($columns)) !== $columns) {
                        continue;
                    }

                    if (!$index['non_unique'] || $index['columns'] === $columns) {
                        $hasMinimalSupportingIndex = true;
                        break;
                    }
                }

                if (!$hasMinimalSupportingIndex) {
                    $addByTable[$table][] = [
                        'name' => 'perf_fk_' . substr(sha1($table . ':' . implode(',', $columns)), 0, 12),
                        'columns' => $columns,
                    ];
                }
            }
        }

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $name => $index) {
                if ($name === 'PRIMARY' || !$index['non_unique']) {
                    continue;
                }

                $isMinimalForeignKeyIndex = false;
                foreach ($foreignKeys[$table] ?? [] as $columns) {
                    if ($index['columns'] === $columns) {
                        $isMinimalForeignKeyIndex = true;
                        break;
                    }
                }

                if (!$isMinimalForeignKeyIndex) {
                    $dropByTable[$table][] = $name;
                    $snapshot[$table][$name] = $index['columns']; // جديد
                }
            }
        }

        $dropped = 0;
        $added = 0;

        foreach (array_unique(array_merge(array_keys($dropByTable), array_keys($addByTable))) as $tableName) {
            $alterations = [];

            foreach ($dropByTable[$tableName] ?? [] as $indexName) {
                $alterations[] = 'DROP INDEX ' . $this->quoteIdentifier($indexName);
                $dropped++;
            }

            foreach ($addByTable[$tableName] ?? [] as $index) {
                $columns = implode(', ', array_map(
                    fn (string $column) => $this->quoteIdentifier($column),
                    $index['columns']
                ));
                $alterations[] = 'ADD INDEX ' . $this->quoteIdentifier($index['name']) . " ({$columns})";
                $added++;
            }

            if ($alterations !== []) {
                $table = $this->quoteIdentifier($tableName);
                $connection->statement('ALTER TABLE ' . $table . ' ' . implode(', ', $alterations));
            }
        }

        if (!empty($snapshot)) {
            $snapshotPath = storage_path('app/performance-indexes-snapshot.json');
            $existing = is_file($snapshotPath) ? json_decode(file_get_contents($snapshotPath), true) : [];
            file_put_contents(
                $snapshotPath,
                json_encode(array_replace_recursive($existing ?: [], $snapshot), JSON_PRETTY_PRINT)
            );
        }

        $this->info(sprintf(
            'Dropped %d non-unique secondary indexes and added %d minimal foreign-key support indexes in %s. Foreign keys, primary keys, and unique indexes were preserved.',
            $dropped,
            $added,
            $database
        ));

        return self::SUCCESS;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
