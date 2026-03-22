<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetSequences extends Command
{
    protected $signature = 'db:reset-sequences
                            {--table= : Сбросить только указанную таблицу}
                            {--dry-run : Показать что будет сделано без изменений}';

    protected $description = 'Синхронизирует PostgreSQL sequences так, чтобы nextval() возвращал max(id)+1';

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

            $maxId = DB::table($table)->max('id');
            if ($maxId === null) {
                $this->line("  <fg=gray>SKIP</> {$table} — таблица пустая");
                $skipped++;
                continue;
            }

            $maxId = (int) $maxId;
            $desiredNextVal = $maxId + 1;

            $state = DB::selectOne("SELECT last_value, is_called FROM {$sequence}");
            $lastValue = (int) ($state->last_value ?? 0);
            $isCalled = (bool) ($state->is_called ?? false);
            $currentNextVal = $isCalled ? $lastValue + 1 : $lastValue;

            if ($currentNextVal >= $desiredNextVal) {
                $this->line("  <fg=gray>OK</>   {$table} — next id актуален ({$currentNextVal})");
                $skipped++;
                continue;
            }

            if (!$dryRun) {
                DB::statement("SELECT setval('{$sequence}', {$maxId}, true)");
            }

            $this->line("  <fg=green>FIXED</> {$table} — next id: {$currentNextVal} → {$desiredNextVal}" . ($dryRun ? ' (dry-run)' : ''));
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
