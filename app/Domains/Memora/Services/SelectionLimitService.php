<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraSelection;

class SelectionLimitService
{
    /**
     * Check if a selection is allowed based on limits
     *
     * @param  string  $selectionId  Selection UUID
     * @param  string|null  $setId  Media Set UUID (optional)
     * @param  int  $currentSelectedCount  Current number of selected items
     * @return bool True if selection is allowed, false otherwise
     */
    public function checkSelectionLimit(string $selectionId, ?string $setId = null, int $currentSelectedCount = 0): bool
    {
        $limit = $this->getSelectionLimit($selectionId, $setId);

        // If no limit, always allow
        if ($limit === null) {
            return true;
        }

        // Check if limit has been reset (allowing more selections)
        $selection = MemoraSelection::query()->find($selectionId);
        if ($selection && $selection->reset_selection_limit_at) {
            // If limit was reset, we need to count selections made after the reset
            $selectedAfterReset = $this->getSelectedCountAfterReset($selectionId, $setId, $selection->reset_selection_limit_at);

            return $selectedAfterReset < $limit;
        }

        // Otherwise, check against current count
        return $currentSelectedCount < $limit;
    }

    /**
     * Get remaining selections available
     *
     * @param  string  $selectionId  Selection UUID
     * @param  string|null  $setId  Media Set UUID (optional)
     * @return int|null Remaining selections (null if unlimited)
     */
    public function getRemainingSelections(string $selectionId, ?string $setId = null): ?int
    {
        $limit = $this->getSelectionLimit($selectionId, $setId);

        // If no limit, return null (unlimited)
        if ($limit === null) {
            return null;
        }

        $currentCount = $this->getCurrentSelectedCount($selectionId, $setId);

        return max(0, $limit - $currentCount);
    }

    /**
     * Check if more selections can be made
     *
     * @param  string  $selectionId  Selection UUID
     * @param  string|null  $setId  Media Set UUID (optional)
     * @param  int  $currentSelectedCount  Current number of selected items
     * @return bool True if more selections can be made
     */
    public function canSelectMore(string $selectionId, ?string $setId = null, int $currentSelectedCount = 0): bool
    {
        return $this->checkSelectionLimit($selectionId, $setId, $currentSelectedCount);
    }

    /**
     * Get the effective selection limit for a selection/set combination
     *
     * PRIORITY ORDER (highest to lowest):
     * 1. Set Limit - Most specific, applies only to the current set
     * 2. Selection Limit - General limit, applies to all sets in the selection
     * 3. Unlimited - No limit (null)
     *
     * Examples:
     * - Set limit = 5, Selection limit = 10 → Effective limit = 5 (set limit wins)
     * - Set limit = null, Selection limit = 10 → Effective limit = 10 (selection limit used)
     * - Set limit = null, Selection limit = null → Effective limit = null (unlimited)
     * - Set limit = 5, Selection limit = null → Effective limit = 5 (set limit used)
     *
     * @param  string  $selectionId  Selection UUID
     * @param  string|null  $setId  Media Set UUID (optional)
     * @return int|null The limit (null if unlimited)
     */
    protected function getSelectionLimit(string $selectionId, ?string $setId = null): ?int
    {
        // PRIORITY 1: If setId is provided, check set limit first (most specific)
        // Set limits override selection limits when both exist
        if ($setId) {
            $set = MemoraMediaSet::query()->find($setId);
            if ($set && $set->selection_limit !== null) {
                return $set->selection_limit;
            }
        }

        // PRIORITY 2: Check selection limit (general limit for all sets)
        // Only used if set doesn't have its own limit
        $selection = MemoraSelection::query()->find($selectionId);
        if ($selection && $selection->selection_limit !== null) {
            return $selection->selection_limit;
        }

        // PRIORITY 3: No limit (unlimited selections allowed)
        return null;
    }

    /**
     * Get current count of selected media items
     *
     * @param  string  $selectionId  Selection UUID
     * @param  string|null  $setId  Media Set UUID (optional)
     * @return int Current selected count
     */
    protected function getCurrentSelectedCount(string $selectionId, ?string $setId = null): int
    {
        $query = MemoraMedia::query()
            ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
            ->where('memora_media_sets.selection_uuid', $selectionId)
            ->where('memora_media.is_selected', true)
            ->whereNull('memora_media.deleted_at');

        if ($setId) {
            $query->where('memora_media_sets.uuid', $setId);
        }

        return $query->count();
    }

    /**
     * Get count of selected items after reset timestamp
     *
     * @param  string  $selectionId  Selection UUID
     * @param  string|null  $setId  Media Set UUID (optional)
     * @param  \Illuminate\Support\Carbon  $resetTimestamp  Reset timestamp
     * @return int Count of selections made after reset
     */
    protected function getSelectedCountAfterReset(string $selectionId, ?string $setId, $resetTimestamp): int
    {
        $query = MemoraMedia::query()
            ->join('memora_media_sets', 'memora_media.media_set_uuid', '=', 'memora_media_sets.uuid')
            ->where('memora_media_sets.selection_uuid', $selectionId)
            ->where('memora_media.is_selected', true)
            ->where('memora_media.selected_at', '>=', $resetTimestamp)
            ->whereNull('memora_media.deleted_at');

        if ($setId) {
            $query->where('memora_media_sets.uuid', $setId);
        }

        return $query->count();
    }
}
