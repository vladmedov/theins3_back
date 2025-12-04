<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SyncLog extends Model
{
    protected $fillable = [
        'entity_type',
        'last_synced_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * Получить время последней успешной синхронизации для сущности
     */
    public static function getLastSyncTime(string $entityType): ?string
    {
        $syncLog = self::where('entity_type', $entityType)->first();
        
        if (!$syncLog || !$syncLog->last_synced_at) {
            // Если нет записи, возвращаем дату очень давно
            return '1970-01-01 00:00:00';
        }
        
        return $syncLog->last_synced_at->format('Y-m-d H:i:s');
    }

    /**
     * Пометить начало синхронизации
     */
    public static function markAsRunning(string $entityType): void
    {
        self::updateOrCreate(
            ['entity_type' => $entityType],
            [
                'status' => 'running',
                'error_message' => null,
            ]
        );
        
        Log::info("Sync started for entity: {$entityType}");
    }

    /**
     * Пометить успешное завершение синхронизации
     */
    public static function markAsCompleted(string $entityType, string $timestamp): void
    {
        self::updateOrCreate(
            ['entity_type' => $entityType],
            [
                'status' => 'completed',
                'last_synced_at' => $timestamp,
                'error_message' => null,
            ]
        );
        
        Log::info("Sync completed for entity: {$entityType} at {$timestamp}");
    }

    /**
     * Пометить неудачное завершение синхронизации
     */
    public static function markAsFailed(string $entityType, string $error): void
    {
        self::updateOrCreate(
            ['entity_type' => $entityType],
            [
                'status' => 'failed',
                'error_message' => $error,
            ]
        );
        
        Log::error("Sync failed for entity: {$entityType}. Error: {$error}");
    }

    /**
     * Проверить, запущена ли синхронизация для сущности
     */
    public static function isRunning(string $entityType): bool
    {
        $syncLog = self::where('entity_type', $entityType)->first();
        
        return $syncLog && $syncLog->status === 'running';
    }

    /**
     * Сбросить статус на idle для всех сущностей (для устранения зависших процессов)
     */
    public static function resetStuckProcesses(int $inactiveMinutes = 1): void
    {
        $stuckLogs = self::where('status', 'running')
            ->where('updated_at', '<', now()->subMinutes($inactiveMinutes))
            ->get();

        foreach ($stuckLogs as $log) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Process timed out or stuck (no activity for ' . $inactiveMinutes . ' minutes)',
            ]);
            
            Log::warning("Reset stuck sync process for entity: {$log->entity_type}");
        }
    }
}

