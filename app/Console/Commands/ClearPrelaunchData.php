<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ClearPrelaunchData extends Command
{
    protected $signature = 'launch:clear-test-data
        {--execute : Delete ALL existing transactional and operational test activity}
        {--workers-stopped : Confirm queue workers and scheduled jobs have been stopped}
        {--rooms-ready : Confirm physical readiness and reset room statuses/housekeeping notes}';

    protected $description = 'Preview or clear prelaunch activity, preserving catalogues, users, settings and media';

    // Children precede parents. Use DELETE, never TRUNCATE: keep foreign-key
    // checks and monotonic IDs so delayed gateway callbacks cannot hit reused IDs.
    public const TABLES = [
        'jobs', 'failed_jobs', 'job_batches', 'notifications',
        'auxiliary_fulfilment_events', 'payment_reconciliation_cases', 'payment_attempts',
        'manual_payment_reviews', 'assisted_reservation_payments',
        'food_order_items', 'food_orders', 'orders', 'service_bookings',
        'accommodation_ledgers', 'incidents', 'maintenance_requests',
        'staff_tasks', 'staff_shifts', 'guest_registrations',
        'monthly_statements', 'daily_reconciliations', 'petty_cash_transactions',
        'stock_movements', 'expenses', 'bookings',
        'guest_access_requests', 'personal_access_tokens', 'password_reset_tokens', 'sessions',
    ];

    public function handle(): int
    {
        try {
            foreach (self::TABLES as $table) {
                if (!Schema::hasTable($table)) {
                    throw new RuntimeException("Missing table {$table}. Run normal migrations before cleanup.");
                }
            }
            $this->table(['Activity to clear', 'Rows'], array_map(
                fn ($table) => [$table, DB::table($table)->count()], self::TABLES
            ));
            $this->info('KEEP: users, rooms/prices/offers, products, menus, services, assets/handovers, property areas, inventory quantities, bank/deposit settings, migrations and all uploaded files.');
            $this->line($this->option('rooms-ready')
                ? 'Room statuses will become available; housekeeping notes will be cleared. Existing is_available switches stay unchanged.'
                : 'Room statuses and housekeeping notes will be preserved. Review them before launch.');
            if (!$this->option('execute')) {
                $this->info('PREVIEW ONLY. No rows or files changed.');
                return self::SUCCESS;
            }
            if (!app()->isDownForMaintenance() || !$this->option('workers-stopped')) {
                throw new RuntimeException('Put the app in maintenance mode and stop queue workers/cron first, then pass --workers-stopped.');
            }
            if (!in_array(config('queue.default'), ['database', 'sync'], true)) {
                throw new RuntimeException('This cleanup supports database/sync queues only. External queues need separate review.');
            }
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            if (!in_array($driver, ['mysql', 'mariadb', 'sqlite'], true) || $connection->getTablePrefix() !== '') {
                throw new RuntimeException('Unsupported database driver or table prefix; nothing was deleted.');
            }
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                foreach (array_merge(self::TABLES, ['rooms']) as $table) {
                    $engine = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
                    if (strcasecmp($engine->engine ?? '', 'InnoDB') !== 0) {
                        throw new RuntimeException("{$table} must use InnoDB for rollback protection; nothing was deleted.");
                    }
                }
            }
            $directory = storage_path('app/private/prelaunch-backups');
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Cannot create private backup directory.');
            }
            $lock = fopen($directory.'/cleanup.lock', 'c');
            if (!$lock) throw new RuntimeException('Cannot open cleanup lock.');
            try {
                if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another cleanup is already running.');
                $backup = $this->backup($directory, $driver);
                $this->info('Private data backup: '.$backup);
                $this->line('SHA256: '.hash_file('sha256', $backup));
                DB::transaction(function (): void {
                    foreach (self::TABLES as $table) DB::table($table)->delete();
                    if ($this->option('rooms-ready')) {
                        DB::table('rooms')->update(['status' => 'available', 'housekeeping_notes' => null]);
                    }
                });
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            $this->info('Test activity cleared. Catalogues, prices, accounts, settings and files preserved.');
            $this->warn('Stock quantities were kept as the opening baseline; verify physical counts. No bank/gateway transaction was refunded or erased.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function backup(string $directory, string $driver): string
    {
        $path = $directory.'/before-cleanup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(6)).'.sql';
        $file = fopen($path, 'xb');
        if (!$file) throw new RuntimeException('Cannot create backup; nothing was deleted.');
        try {
            if (!chmod($path, 0600)) throw new RuntimeException('Cannot protect backup permissions.');
            $write = static function (string $sql) use ($file): void {
                if (fwrite($file, $sql) !== strlen($sql)) throw new RuntimeException('Backup write failed; nothing was deleted.');
            };
            $write("-- Insignia prelaunch DATA backup. Restore into the SAME migrated schema, offline.\n");
            $write(in_array($driver, ['mysql', 'mariadb'], true) ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n" : "PRAGMA foreign_keys=OFF;\n");
            $grammar = DB::connection()->getQueryGrammar();
            $tables = Schema::getTableListing();
            // Export the complete database, including retained configuration.
            foreach ($tables as $table) $write('DELETE FROM '.$grammar->wrapTable($table).";\n");
            foreach ($tables as $table) {
                foreach (DB::table($table)->cursor() as $row) {
                    $values = (array) $row;
                    $columns = implode(', ', array_map(fn ($name) => $grammar->wrap($name), array_keys($values)));
                    $quoted = array_map(function ($value): string {
                        if ($value === null) return 'NULL';
                        $value = DB::connection()->getPdo()->quote((string) $value);
                        if ($value === false) throw new RuntimeException('Backup quoting failed.');
                        return $value;
                    }, array_values($values));
                    $write('INSERT INTO '.$grammar->wrapTable($table).' ('.$columns.') VALUES ('.implode(', ', $quoted).");\n");
                }
            }
            $write(in_array($driver, ['mysql', 'mariadb'], true) ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n");
            $write("-- BACKUP COMPLETE\n");
            if (!fflush($file)) throw new RuntimeException('Backup flush failed; nothing was deleted.');
        } finally {
            fclose($file);
        }
        return $path;
    }
}
