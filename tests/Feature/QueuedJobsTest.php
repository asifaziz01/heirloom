<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeMedia;
use App\Jobs\BuildArchive;
use App\Jobs\ExtractInterview;
use App\Jobs\RunAiTask;
use App\Models\AiTask;
use App\Models\Export;
use App\Models\Family;
use App\Models\Interview;
use App\Models\Person;
use App\Models\User;
use App\Services\HeritageAi;
use Database\Seeders\DemoFamilySeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * AI calls and the archive export run as queued jobs so they survive shared-hosting request limits.
 * Controllers only dispatch; each job records pending -> running -> done | failed on its model, and the
 * pages poll a members-only status endpoint. The review-before-save flow for AI suggestions is unchanged.
 */
class QueuedJobsTest extends TestCase
{
    use RefreshDatabase;

    private Family $family;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.anthropic.key' => 'test-key']);
        $this->seed(DemoFamilySeeder::class);
        $this->family = Family::firstOrFail();
        $this->owner = User::where('email', 'owner@heirloom.test')->firstOrFail();
    }

    private function person(string $given): Person
    {
        return Person::where('family_id', $this->family->id)->where('given_name', $given)->firstOrFail();
    }

    private function interview(): Interview
    {
        return $this->family->interviews()->create(['title' => 'Abdul on the Aminabad house', 'transcript' => 'We moved to Aminabad in 1961.', 'person_id' => $this->person('Abdul')->id, 'created_by' => $this->owner->id]);
    }

    private function extraction(): array
    {
        return ['summary' => 'A move to Aminabad.', 'story_title' => 'The Aminabad house', 'story_body' => 'We moved…', 'people' => [], 'places' => [], 'events' => [], 'follow_up_questions' => []];
    }

    public function test_interview_extraction_is_dispatched_and_the_page_shows_progress(): void
    {
        Queue::fake();
        $interview = $this->interview();

        $this->actingAs($this->owner)->post(route('interviews.extract', [$this->family, $interview]))->assertRedirect()->assertSessionHas('status');
        Queue::assertPushed(ExtractInterview::class, fn (ExtractInterview $job) => $job->interview->is($interview));
        $this->assertSame('queued', $interview->fresh()->status);

        $this->actingAs($this->owner)->get(route('interviews.show', [$this->family, $interview]))->assertOk()->assertSee('Extracting people, places');
        $this->actingAs($this->owner)->getJson(route('interviews.status', [$this->family, $interview]))->assertOk()->assertJson(['status' => 'pending', 'error' => null]);

        // a second click while it runs does not queue a duplicate
        $this->actingAs($this->owner)->post(route('interviews.extract', [$this->family, $interview]))->assertRedirect();
        Queue::assertPushed(ExtractInterview::class, 1);

        // members only
        $this->actingAs(User::factory()->create())->getJson(route('interviews.status', [$this->family, $interview]))->assertForbidden();
    }

    public function test_extract_interview_job_records_the_result_or_the_failure(): void
    {
        $interview = $this->interview();
        $interview->update(['status' => 'queued']);

        $ai = $this->mock(HeritageAi::class);
        $ai->shouldReceive('extractInterview')->once()->andReturn($this->extraction());
        (new ExtractInterview($interview))->handle($ai);
        $interview->refresh();
        $this->assertSame('extracted', $interview->status);
        $this->assertSame('A move to Aminabad.', $interview->extraction['summary']);
        $this->actingAs($this->owner)->getJson(route('interviews.status', [$this->family, $interview]))->assertJson(['status' => 'done']);
        // the review step is still there: nothing was written to the archive
        $this->assertDatabaseMissing('stories', ['title' => 'The Aminabad house']);
        $this->actingAs($this->owner)->get(route('interviews.show', [$this->family, $interview]))->assertOk()->assertSee('Review what the AI found');

        $ai = $this->mock(HeritageAi::class);
        $ai->shouldReceive('extractInterview')->once()->andThrow(new RuntimeException('The AI service is busy. Please try again in a minute.'));
        (new ExtractInterview($interview))->handle($ai);
        $interview->refresh();
        $this->assertSame('failed', $interview->status);
        $this->assertSame('The AI service is busy. Please try again in a minute.', $interview->extraction_error);
        $this->assertNotNull($interview->extraction, 'an earlier extraction survives a failed retry');
        $this->actingAs($this->owner)->getJson(route('interviews.status', [$this->family, $interview]))->assertJson(['status' => 'failed', 'error' => 'The AI service is busy. Please try again in a minute.']);
        $this->actingAs($this->owner)->get(route('interviews.show', [$this->family, $interview]))->assertOk()->assertSee('Last attempt failed')->assertSee('Extract again');

        (new ExtractInterview($interview))->failed(new Exception('database went away'));
        $this->assertSame('failed', $interview->fresh()->status);
        $this->assertStringContainsString('Please try again', $interview->fresh()->extraction_error);
    }

    public function test_media_analysis_is_dispatched_and_the_job_stores_suggestions(): void
    {
        Queue::fake();
        $media = $this->family->media()->where('kind', 'photo')->firstOrFail();
        $titleBefore = $media->title;
        $logsBefore = $this->family->activity()->where('subject_type', 'Media')->count();

        $this->actingAs($this->owner)->post(route('media.analyze', [$this->family, $media]))->assertRedirect();
        Queue::assertPushed(AnalyzeMedia::class, fn (AnalyzeMedia $job) => $job->media->is($media));
        $this->assertSame('pending', $media->fresh()->aiStatus());
        $this->actingAs($this->owner)->get(route('media.show', [$this->family, $media]))->assertOk()->assertSee('Looking at this photo')->assertDontSee('Analyse with AI');
        $this->actingAs($this->owner)->getJson(route('media.status', [$this->family, $media]))->assertJson(['status' => 'pending']);

        $ai = $this->mock(HeritageAi::class);
        $ai->shouldReceive('analyzeMedia')->once()->andReturn(['title' => 'Wedding day in Lucknow', 'description' => 'A couple on a veranda.', 'estimated_year' => 1958, 'era_reasoning' => null, 'document_type' => null, 'transcription' => null, 'people' => [], 'places' => ['Lucknow'], 'dates' => []]);
        (new AnalyzeMedia($media))->handle($ai);
        $media->refresh();
        $this->assertSame('done', $media->aiStatus());
        $this->assertSame('Wedding day in Lucknow', $media->ai_suggestions['title']);
        $this->actingAs($this->owner)->getJson(route('media.status', [$this->family, $media]))->assertJson(['status' => 'done']);
        $this->actingAs($this->owner)->get(route('media.show', [$this->family, $media]))->assertOk()->assertSee('Wedding day in Lucknow')->assertSee('Analyse with AI');
        $this->assertSame($titleBefore, $media->title, 'suggestions are not applied until the user copies them into the form');
        $this->assertSame($logsBefore, $this->family->activity()->where('subject_type', 'Media')->count(), 'suggestion bookkeeping stays out of the audit log');

        $ai = $this->mock(HeritageAi::class);
        $ai->shouldReceive('analyzeMedia')->once()->andThrow(new RuntimeException('Could not reach the AI service. Check your connection.'));
        (new AnalyzeMedia($media))->handle($ai);
        $this->assertSame('failed', $media->fresh()->aiStatus());
        $this->actingAs($this->owner)->getJson(route('media.status', [$this->family, $media]))->assertJson(['status' => 'failed', 'error' => 'Could not reach the AI service. Check your connection.']);
        $this->actingAs($this->owner)->get(route('media.show', [$this->family, $media]))->assertOk()->assertSee('Last attempt failed');
    }

    public function test_biography_and_question_requests_run_as_ai_tasks(): void
    {
        Queue::fake();
        $abdul = $this->person('Abdul');

        $this->actingAs($this->owner)->post(route('ai.biography', [$this->family, $abdul]))->assertRedirect(route('people.show', [$this->family, $abdul]));
        Queue::assertPushed(RunAiTask::class, fn (RunAiTask $job) => $job->task->type === 'biography' && $job->task->person_id === $abdul->id);
        $task = AiTask::where('type', 'biography')->firstOrFail();
        $this->assertSame('pending', $task->status);
        $this->actingAs($this->owner)->get(route('people.show', [$this->family, $abdul]))->assertOk()->assertSee('Drafting a biography')->assertDontSee('Draft with AI');
        $this->actingAs($this->owner)->getJson(route('ai.tasks.status', [$this->family, $task]))->assertJson(['status' => 'pending', 'url' => null]);

        $ai = $this->mock(HeritageAi::class);
        $ai->shouldReceive('biography')->once()->andReturn('Abdul Rahman was born in Gorakhpur…');
        (new RunAiTask($task))->handle($ai);
        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertSame('Abdul Rahman was born in Gorakhpur…', $task->result['biography']);
        $editUrl = route('people.edit', [$this->family, $abdul, 'draft' => $task->id]);
        $this->actingAs($this->owner)->getJson(route('ai.tasks.status', [$this->family, $task]))->assertJson(['status' => 'done', 'url' => $editUrl]);
        $this->actingAs($this->owner)->get(route('people.show', [$this->family, $abdul]))->assertOk()->assertSee('An AI draft is ready');
        $this->actingAs($this->owner)->get($editUrl)->assertOk()->assertSee('Abdul Rahman was born in Gorakhpur…')->assertSee('This is an AI draft');
        $this->assertSame($abdul->biography, $abdul->fresh()->biography, 'the draft is only saved when the user submits the form');

        $this->actingAs($this->owner)->post(route('ai.questions', [$this->family, $abdul]))->assertRedirect();
        $qTask = AiTask::where('type', 'questions')->firstOrFail();
        Queue::assertPushed(RunAiTask::class, fn (RunAiTask $job) => $job->task->is($qTask));
        $ai = $this->mock(HeritageAi::class);
        $ai->shouldReceive('suggestQuestions')->once()->andReturn(['What was the Aminabad house like?']);
        (new RunAiTask($qTask))->handle($ai);
        $this->assertSame('done', $qTask->fresh()->status);
        $this->actingAs($this->owner)->get(route('people.show', [$this->family, $abdul]))->assertOk()->assertSee('What was the Aminabad house like?');

        $ai = $this->mock(HeritageAi::class);
        $ai->shouldReceive('suggestQuestions')->once()->andThrow(new RuntimeException('The AI declined to process this content.'));
        $qTask->update(['status' => 'pending']);
        (new RunAiTask($qTask))->handle($ai);
        $this->assertSame('failed', $qTask->fresh()->status);
        $this->assertSame('The AI declined to process this content.', $qTask->fresh()->error);
        $this->actingAs($this->owner)->get(route('people.show', [$this->family, $abdul]))->assertOk()->assertSee('The AI declined to process this content.');

        // other families cannot read the task
        $this->actingAs(User::factory()->create())->getJson(route('ai.tasks.status', [$this->family, $task]))->assertForbidden();
    }

    public function test_with_a_sync_queue_results_arrive_in_the_same_request(): void
    {
        // phpunit.xml sets QUEUE_CONNECTION=sync, the configuration of a host without cron.
        $abdul = $this->person('Abdul');
        $ai = $this->mock(HeritageAi::class);
        $ai->shouldReceive('biography')->once()->andReturn('Inline draft.');
        $ai->shouldReceive('extractInterview')->once()->andReturn($this->extraction());

        $response = $this->actingAs($this->owner)->post(route('ai.biography', [$this->family, $abdul]));
        $task = AiTask::where('type', 'biography')->firstOrFail();
        $response->assertRedirect(route('people.edit', [$this->family, $abdul, 'draft' => $task->id]));
        $this->assertSame('done', $task->status);

        $interview = $this->interview();
        $this->actingAs($this->owner)->post(route('interviews.extract', [$this->family, $interview]))->assertRedirect();
        $this->assertSame('extracted', $interview->fresh()->status);
    }

    public function test_archive_export_is_dispatched_built_on_the_private_disk_and_downloaded_once(): void
    {
        Queue::fake();
        $this->actingAs($this->owner)->post(route('export.archive', $this->family))->assertRedirect(route('export.index', $this->family));
        $export = $this->family->exports()->latest('id')->firstOrFail();
        Queue::assertPushed(BuildArchive::class, fn (BuildArchive $job) => $job->export->is($export));
        $this->assertSame('pending', $export->status);
        $this->actingAs($this->owner)->get(route('export.index', $this->family))->assertOk()->assertSee('Packing the archive')->assertDontSee('Prepare complete archive');
        $this->actingAs($this->owner)->getJson(route('export.status', [$this->family, $export]))->assertJson(['status' => 'pending']);

        // a second request while one is running does not queue another build
        $this->actingAs($this->owner)->post(route('export.archive', $this->family))->assertRedirect();
        Queue::assertPushed(BuildArchive::class, 1);

        (new BuildArchive($export))->handle();
        $export->refresh();
        $this->assertSame('done', $export->status);
        $this->assertStringStartsWith("families/{$this->family->id}/exports/", $export->path);
        $this->assertStringEndsWith('.zip', $export->filename);
        Storage::disk('local')->assertExists($export->path);
        $this->assertGreaterThan(0, $export->size);
        $this->actingAs($this->owner)->getJson(route('export.status', [$this->family, $export]))->assertJson(['status' => 'done']);
        $this->actingAs($this->owner)->get(route('export.index', $this->family))->assertOk()->assertSee('Your archive is ready')->assertSee($export->filename);

        // admins only
        $contributor = User::where('email', 'zara@heirloom.test')->firstOrFail();
        $this->actingAs($contributor)->get(route('export.download', [$this->family, $export]))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('export.download', [$this->family, $export]))->assertForbidden();

        $response = $this->actingAs($this->owner)->get(route('export.download', [$this->family, $export]));
        $response->assertOk();
        $this->assertStringContainsString($export->filename, $response->headers->get('content-disposition'));
        $this->assertNotNull($export->fresh()->downloaded_at);

        // once downloaded, the next visit removes the record and the file
        $this->actingAs($this->owner)->get(route('export.index', $this->family))->assertOk()->assertSee('Prepare complete archive');
        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
        Storage::disk('local')->assertMissing($export->path);
    }

    public function test_stale_and_failed_exports_are_handled(): void
    {
        $old = $this->family->exports()->create(['user_id' => $this->owner->id, 'status' => 'done', 'path' => "families/{$this->family->id}/exports/old.zip", 'filename' => 'old.zip', 'size' => 3]);
        Storage::disk('local')->put($old->path, 'zip');
        Export::whereKey($old->id)->update(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        Export::pruneStale();
        $this->assertDatabaseMissing('exports', ['id' => $old->id]);
        Storage::disk('local')->assertMissing($old->path);

        $export = $this->family->exports()->create(['user_id' => $this->owner->id, 'status' => 'running']);
        (new BuildArchive($export))->failed(new Exception('disk full'));
        $this->assertSame('failed', $export->fresh()->status);
        $this->actingAs($this->owner)->get(route('export.index', $this->family))->assertOk()->assertSee('could not be built')->assertSee('Prepare complete archive');
        $this->actingAs($this->owner)->get(route('export.download', [$this->family, $export]))->assertNotFound();
    }
}
