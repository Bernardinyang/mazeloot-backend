<?php

namespace App\Domains\Memora\Controllers\V1;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraPreset;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraRawFile;
use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Models\MemoraWatermark;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     * GET /api/v1/memora/dashboard
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $userId = $user->uuid;

        // Basic counts
        $collections = MemoraCollection::where('user_uuid', $userId)->count();
        $publishedCollections = MemoraCollection::where('user_uuid', $userId)
            ->where('status', 'published')
            ->count();

        $projects = MemoraProject::where('user_uuid', $userId)->count();
        $activeProjects = MemoraProject::where('user_uuid', $userId)
            ->where('status', 'active')
            ->count();

        $selections = MemoraSelection::where('user_uuid', $userId)->count();
        $completedSelections = MemoraSelection::where('user_uuid', $userId)
            ->where('status', 'completed')
            ->count();

        $proofing = MemoraProofing::where('user_uuid', $userId)->count();
        $activeProofing = MemoraProofing::where('user_uuid', $userId)
            ->where('status', 'active')
            ->count();

        $totalMedia = MemoraMedia::where('user_uuid', $userId)->count();
        $featuredMedia = MemoraMedia::where('user_uuid', $userId)
            ->where('is_featured', true)
            ->count();

        $presets = MemoraPreset::where('user_uuid', $userId)->count();
        $activePresets = MemoraPreset::where('user_uuid', $userId)
            ->where('is_selected', true)
            ->count();

        $watermarks = MemoraWatermark::where('user_uuid', $userId)->count();
        $activeWatermarks = $watermarks; // Watermarks don't have active/inactive status

        $rawFiles = MemoraRawFile::where('user_uuid', $userId)->count();
        $activeRawFiles = MemoraRawFile::where('user_uuid', $userId)
            ->where('status', 'active')
            ->count();

        // Activity data for last 7 days
        $activityData = $this->getActivityData($userId);

        // Recent activity (last 5 items from each type)
        $recentActivity = $this->getRecentActivity($userId);

        return ApiResponse::success([
            'stats' => [
                'collections' => $collections,
                'publishedCollections' => $publishedCollections,
                'projects' => $projects,
                'activeProjects' => $activeProjects,
                'selections' => $selections,
                'completedSelections' => $completedSelections,
                'proofing' => $proofing,
                'activeProofing' => $activeProofing,
                'totalMedia' => $totalMedia,
                'featuredMedia' => $featuredMedia,
                'presets' => $presets,
                'activePresets' => $activePresets,
                'watermarks' => $watermarks,
                'activeWatermarks' => $activeWatermarks,
                'rawFiles' => $rawFiles,
                'activeRawFiles' => $activeRawFiles,
            ],
            'activity' => $activityData,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Get activity data for last 7 days
     */
    private function getActivityData(string $userId): array
    {
        $now = now();
        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->startOfDay();
            $nextDate = $date->copy()->endOfDay();

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'collections' => MemoraCollection::where('user_uuid', $userId)
                    ->whereBetween('created_at', [$date, $nextDate])
                    ->count(),
                'projects' => MemoraProject::where('user_uuid', $userId)
                    ->whereBetween('created_at', [$date, $nextDate])
                    ->count(),
                'selections' => MemoraSelection::where('user_uuid', $userId)
                    ->whereBetween('created_at', [$date, $nextDate])
                    ->count(),
                'proofing' => MemoraProofing::where('user_uuid', $userId)
                    ->whereBetween('created_at', [$date, $nextDate])
                    ->count(),
                'rawFiles' => MemoraRawFile::where('user_uuid', $userId)
                    ->whereBetween('created_at', [$date, $nextDate])
                    ->count(),
            ];
        }

        return $days;
    }

    /**
     * Get recent activity from all content types
     */
    private function getRecentActivity(string $userId): array
    {
        $activities = [];

        // Collections
        $collections = MemoraCollection::where('user_uuid', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get(['uuid', 'name', 'created_at']);

        foreach ($collections as $item) {
            $activities[] = [
                'id' => "collection-{$item->uuid}",
                'type' => 'collection',
                'title' => 'Collection created',
                'description' => $item->name,
                'date' => $item->created_at->toISOString(),
            ];
        }

        // Projects
        $projects = MemoraProject::where('user_uuid', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get(['uuid', 'name', 'created_at']);

        foreach ($projects as $item) {
            $activities[] = [
                'id' => "project-{$item->uuid}",
                'type' => 'project',
                'title' => 'Project created',
                'description' => $item->name,
                'date' => $item->created_at->toISOString(),
            ];
        }

        // Selections
        $selections = MemoraSelection::where('user_uuid', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get(['uuid', 'name', 'created_at']);

        foreach ($selections as $item) {
            $activities[] = [
                'id' => "selection-{$item->uuid}",
                'type' => 'selection',
                'title' => 'Selection created',
                'description' => $item->name,
                'date' => $item->created_at->toISOString(),
            ];
        }

        // Proofing
        $proofing = MemoraProofing::where('user_uuid', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get(['uuid', 'name', 'created_at']);

        foreach ($proofing as $item) {
            $activities[] = [
                'id' => "proofing-{$item->uuid}",
                'type' => 'proofing',
                'title' => 'Proofing created',
                'description' => $item->name,
                'date' => $item->created_at->toISOString(),
            ];
        }

        // Raw Files
        $rawFiles = MemoraRawFile::where('user_uuid', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get(['uuid', 'name', 'created_at']);

        foreach ($rawFiles as $item) {
            $activities[] = [
                'id' => "rawFile-{$item->uuid}",
                'type' => 'rawFile',
                'title' => 'Raw file created',
                'description' => $item->name,
                'date' => $item->created_at->toISOString(),
            ];
        }

        // Sort by date descending and return top 5
        usort($activities, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($activities, 0, 5);
    }
}
