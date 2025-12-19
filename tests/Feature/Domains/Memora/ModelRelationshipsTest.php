<?php

namespace Tests\Feature\Domains\Memora;

use App\Domains\Memora\Models\MemoraCollection;
use App\Domains\Memora\Models\MemoraCoverStyle;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraMediaFeedback;
use App\Domains\Memora\Models\MemoraMediaSet;
use App\Domains\Memora\Models\MemoraPreset;
use App\Domains\Memora\Models\MemoraProject;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraSelection;
use App\Domains\Memora\Models\MemoraWatermark;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create user status first
        $this->userStatus = UserStatus::factory()->create();
    }

    public function test_user_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        
        // Test user -> status relationship
        $this->assertNotNull($user->status);
        $this->assertEquals($this->userStatus->uuid, $user->status->uuid);
        
        // Test user -> activity logs relationship
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $user->activityLogs);
    }

    public function test_user_status_relationships(): void
    {
        $userStatus = UserStatus::factory()->create();
        $user = User::factory()->create(['status_uuid' => $userStatus->uuid]);
        
        // Test user status -> users relationship
        $this->assertTrue($userStatus->users->contains($user));
        $this->assertEquals($user->uuid, $userStatus->users->first()->uuid);
    }

    public function test_preset_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        $coverStyle = MemoraCoverStyle::factory()->create();
        $watermark = MemoraWatermark::factory()->create(['user_uuid' => $user->uuid]);
        
        $preset = MemoraPreset::factory()->create([
            'user_uuid' => $user->uuid,
            'design_cover_uuid' => $coverStyle->uuid,
            'default_watermark_uuid' => $watermark->uuid,
        ]);
        
        // Test preset -> user relationship
        $this->assertNotNull($preset->user);
        $this->assertEquals($user->uuid, $preset->user->uuid);
        
        // Test preset -> cover style relationship
        $this->assertNotNull($preset->coverStyle);
        $this->assertEquals($coverStyle->uuid, $preset->coverStyle->uuid);
        
        // Test preset -> watermark relationship
        $this->assertNotNull($preset->defaultWatermark);
        $this->assertEquals($watermark->uuid, $preset->defaultWatermark->uuid);
        
        // Test preset -> projects relationship
        $project = MemoraProject::factory()->create([
            'user_uuid' => $user->uuid,
            'preset_uuid' => $preset->uuid,
        ]);
        
        $this->assertTrue($preset->projects->contains($project));
        $this->assertEquals($project->uuid, $preset->projects->first()->uuid);
    }

    public function test_cover_style_relationships(): void
    {
        $coverStyle = MemoraCoverStyle::factory()->create();
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        
        $preset = MemoraPreset::factory()->create([
            'user_uuid' => $user->uuid,
            'design_cover_uuid' => $coverStyle->uuid,
        ]);
        
        // Test cover style -> presets relationship
        $this->assertTrue($coverStyle->presets->contains($preset));
        $this->assertEquals($preset->uuid, $coverStyle->presets->first()->uuid);
    }

    public function test_watermark_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        $watermark = MemoraWatermark::factory()->create(['user_uuid' => $user->uuid]);
        
        // Test watermark -> user relationship
        $this->assertNotNull($watermark->user);
        $this->assertEquals($user->uuid, $watermark->user->uuid);
        
        // Test watermark -> presets relationship
        $preset = MemoraPreset::factory()->create([
            'user_uuid' => $user->uuid,
            'default_watermark_uuid' => $watermark->uuid,
        ]);
        
        $this->assertTrue($watermark->presets->contains($preset));
        $this->assertEquals($preset->uuid, $watermark->presets->first()->uuid);
        
        // Test watermark -> projects relationship
        $project = MemoraProject::factory()->create([
            'user_uuid' => $user->uuid,
            'watermark_uuid' => $watermark->uuid,
        ]);
        
        $this->assertTrue($watermark->projects->contains($project));
        $this->assertEquals($project->uuid, $watermark->projects->first()->uuid);
    }

    public function test_project_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        $preset = MemoraPreset::factory()->create(['user_uuid' => $user->uuid]);
        $watermark = MemoraWatermark::factory()->create(['user_uuid' => $user->uuid]);
        
        $project = MemoraProject::factory()->create([
            'user_uuid' => $user->uuid,
            'preset_uuid' => $preset->uuid,
            'watermark_uuid' => $watermark->uuid,
        ]);
        
        // Test project -> user relationship
        $this->assertNotNull($project->user);
        $this->assertEquals($user->uuid, $project->user->uuid);
        
        // Test project -> preset relationship
        $this->assertNotNull($project->preset);
        $this->assertEquals($preset->uuid, $project->preset->uuid);
        
        // Test project -> watermark relationship
        $this->assertNotNull($project->watermark);
        $this->assertEquals($watermark->uuid, $project->watermark->uuid);
        
        // Test project -> media sets relationship
        $mediaSet = MemoraMediaSet::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        
        $this->assertTrue($project->mediaSets->contains($mediaSet));
        $this->assertEquals($mediaSet->uuid, $project->mediaSets->first()->uuid);
        
        // Test project -> selections relationship
        $selection = MemoraSelection::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        
        $this->assertTrue($project->selections->contains($selection));
        $this->assertEquals($selection->uuid, $project->selections->first()->uuid);
        
        // Test project -> proofing relationship
        $proofing = MemoraProofing::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        
        $this->assertTrue($project->proofing->contains($proofing));
        $this->assertEquals($proofing->uuid, $project->proofing->first()->uuid);
        
        // Test project -> collections relationship
        $collection = MemoraCollection::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        
        $this->assertTrue($project->collections->contains($collection));
        $this->assertEquals($collection->uuid, $project->collections->first()->uuid);
        
        // Test project -> parent/children relationships
        $childProject = MemoraProject::factory()->create([
            'user_uuid' => $user->uuid,
            'parent_uuid' => $project->uuid,
        ]);
        
        $this->assertNotNull($childProject->parent);
        $this->assertEquals($project->uuid, $childProject->parent->uuid);
        
        $this->assertTrue($project->children->contains($childProject));
        $this->assertEquals($childProject->uuid, $project->children->first()->uuid);
    }

    public function test_media_set_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        
        $mediaSet = MemoraMediaSet::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        
        // Test media set -> project relationship
        $this->assertNotNull($mediaSet->project);
        $this->assertEquals($project->uuid, $mediaSet->project->uuid);
        
        // Test media set -> media relationship
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $mediaSet->uuid,
        ]);
        
        $this->assertTrue($mediaSet->media->contains($media));
        $this->assertEquals($media->uuid, $mediaSet->media->first()->uuid);
    }

    public function test_media_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $mediaSet = MemoraMediaSet::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $mediaSet->uuid,
        ]);
        
        // Test media -> media set relationship
        $this->assertNotNull($media->mediaSet);
        $this->assertEquals($mediaSet->uuid, $media->mediaSet->uuid);
        
        // Test media -> feedback relationship
        $feedback = MemoraMediaFeedback::factory()->create([
            'media_uuid' => $media->uuid,
        ]);
        
        $this->assertTrue($media->feedback->contains($feedback));
        $this->assertEquals($feedback->uuid, $media->feedback->first()->uuid);
    }

    public function test_media_feedback_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        $mediaSet = MemoraMediaSet::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        $media = MemoraMedia::factory()->create([
            'media_set_uuid' => $mediaSet->uuid,
        ]);
        
        $feedback = MemoraMediaFeedback::factory()->create([
            'media_uuid' => $media->uuid,
        ]);
        
        // Test feedback -> media relationship
        $this->assertNotNull($feedback->media);
        $this->assertEquals($media->uuid, $feedback->media->uuid);
    }

    public function test_collection_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        
        $collection = MemoraCollection::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        
        // Test collection -> project relationship
        $this->assertNotNull($collection->project);
        $this->assertEquals($project->uuid, $collection->project->uuid);
    }

    public function test_selection_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        
        $selection = MemoraSelection::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        
        // Test selection -> project relationship
        $this->assertNotNull($selection->project);
        $this->assertEquals($project->uuid, $selection->project->uuid);
    }

    public function test_proofing_relationships(): void
    {
        $user = User::factory()->create(['status_uuid' => $this->userStatus->uuid]);
        $project = MemoraProject::factory()->create(['user_uuid' => $user->uuid]);
        
        $proofing = MemoraProofing::factory()->create([
            'project_uuid' => $project->uuid,
        ]);
        
        // Test proofing -> project relationship
        $this->assertNotNull($proofing->project);
        $this->assertEquals($project->uuid, $proofing->project->uuid);
    }
}


