<?php

namespace App\AmazonAds\Services;

use App\AmazonAds\Models\PpcChangeLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class PpcChangeLoggerService
{
    /**
     * Log a change to a PPC entity
     *
     * @param string $entityType The type of entity (campaign, ad_group, keyword, product_targeting)
     * @param int $entityId The ID of the entity
     * @param string $fieldName The name of the field that changed
     * @param mixed $oldValue The old value
     * @param mixed $newValue The new value
     * @param string $action The action performed (created, updated, deleted)
     * @param int|null $userId The ID of the user who made the change
     * @return void
     */
    public function logChange(
        string $entityType,
        int $entityId,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        string $action,
        ?int $userId = null
    ): void {
        try {
            // Skip if values are equal
            if ($oldValue === $newValue) {
                return;
            }

            // Convert values to strings
            $oldValue = is_array($oldValue) ? json_encode($oldValue) : (string) $oldValue;
            $newValue = is_array($newValue) ? json_encode($newValue) : (string) $newValue;

            // Get user ID from auth if not provided
            $userId = $userId ?? Auth::id();

            PpcChangeLog::create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'field_name' => $fieldName,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'action' => $action,
                'user_id' => $userId,
                'changed_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log the error but don't throw it
            \Log::error('Failed to log PPC change: ' . $e->getMessage());
        }
    }

    /**
     * Log multiple changes in bulk
     *
     * @param Collection $changes Collection of change data
     * @return void
     */
    public function logBulkChanges(Collection $changes): void
    {
        try {
            if ($changes->isEmpty()) {
                return;
            }

            $userId = Auth::id();
            $now = now();

            $logs = $changes->map(function ($change) use ($userId, $now) {
                // Skip if values are equal
                if ($change['old_value'] === $change['new_value']) {
                    return null;
                }

                // Convert values to strings
                $oldValue = is_array($change['old_value']) ? json_encode($change['old_value']) : (string) $change['old_value'];
                $newValue = is_array($change['new_value']) ? json_encode($change['new_value']) : (string) $change['new_value'];

                return [
                    'entity_type' => $change['entity_type'],
                    'entity_id' => $change['entity_id'],
                    'field_name' => $change['field_name'],
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'action' => $change['action'],
                    'user_id' => $change['user_id'] ?? $userId,
                    'changed_at' => $now,
                ];
            })->filter()->values();

            if ($logs->isNotEmpty()) {
                PpcChangeLog::insert($logs->toArray());
            }
        } catch (\Exception $e) {
            // Log the error but don't throw it
            \Log::error('Failed to log bulk PPC changes: ' . $e->getMessage());
        }
    }

    /**
     * Get logs for a specific entity
     *
     * @param string $entityType
     * @param int $entityId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsForEntity(string $entityType, int $entityId)
    {
        return PpcChangeLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('changed_at', 'desc')
            ->get();
    }
} 