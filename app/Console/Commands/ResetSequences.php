<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetSequences extends Command
{
    protected $signature = 'db:reset-sequences
                            {--table= : Сбросить только указанную таблицу}
                            {--dry-run : Показать что будет сделано без изменений}';

    protected $description = 'Сбрасывает PostgreSQL-последовательности (sequences) до max(id)+1 для всех таблиц';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $onlyTable = $this->option('table');

        if ($dryRun) {
            $this->warn('Режим dry-run — изменения не применяются.');
        }

        $tables = $onlyTable
            ? [$onlyTable]
            : $this->getAllTables();

        $fixed = 0;
        $skipped = 0;

        foreach ($tables as $table) {
            $sequence = $this->getSequenceName($table);

            if (!$sequence) {
                $this->line("  <fg=gray>SKIP</> {$table} — sequence не найден");
                $skipped++;
                continue;
            }

            $maxId = DB::table($table)->max('id') ?? 0;
            $nextVal = $maxId + 1;

            $currentVal = DB::selectOne("SELECT last_value FROM {$sequence}")->last_value ?? 0;

            if ($currentVal >= $nextVal) {
                $this->line("  <fg=gray>OK</>   {$table} — sequence актуален ({$currentVal})");
                $skipped++;
                continue;
            }

            if (!$dryRun) {
                DB::statement("SELECT setval('{$sequence}', {$nextVal})");
            }

            $this->line("  <fg=green>FIXED</> {$table} — sequence: {$currentVal} → {$nextVal}" . ($dryRun ? ' (dry-run)' : ''));
            $fixed++;
        }

        $this->newLine();
        $this->info("Готово: исправлено {$fixed}, пропущено {$skipped}.");

        return self::SUCCESS;
    }

    private function getAllTables(): array
    {
        $rows = DB::select("
            SELECT tablename
            FROM pg_tables
            WHERE schemaname = 'public'
            ORDER BY tablename
        ");

        return array_column($rows, 'tablename');
    }

    private function getSequenceName(string $table): ?string
    {
        try {
            $row = DB::selectOne("
                SELECT pg_get_serial_sequence('{$table}', 'id') AS seq
            ");

            return $row->seq ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
