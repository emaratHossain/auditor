<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * No audit may sit on pending or running forever — the shared Definition of
 * Done, card #84.
 *
 * The case that actually happens is the boring one: nobody started a queue
 * worker, so the job chain sits in the jobs table and the progress bar spins
 * for the rest of the demo. The app has to say that out loud, because from the
 * outside "working on it" and "nothing is listening" look identical.
 */
class StalledAuditTest extends TestCase
{
    use RefreshDatabase;

    private function audit(string $status, ?string $stage, int $ageSeconds): Audit
    {
        $page = Page::create(['name' => 'Stall test', 'url' => 'https://example.com/stall']);

        $audit = $page->audits()->create(['status' => $status, 'stage' => $stage]);

        // Backdate without touching timestamps, so updated_at is genuinely old.
        $when = now()->subSeconds($ageSeconds);
        Audit::withoutTimestamps(fn () => $audit->forceFill([
            'created_at' => $when,
            'updated_at' => $when,
        ])->save());

        return $audit->fresh();
    }

    public function test_a_fresh_pending_audit_is_left_alone(): void
    {
        $audit = $this->audit(Audit::STATUS_PENDING, null, 3);

        $this->getJson("/api/audits/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.status', Audit::STATUS_PENDING);
    }

    /** Nothing picked the job up at all. That is a missing worker, not a slow page. */
    public function test_a_pending_audit_nobody_picked_up_says_the_worker_is_missing(): void
    {
        $audit = $this->audit(Audit::STATUS_PENDING, null, 120);

        $response = $this->getJson("/api/audits/{$audit->id}")->assertOk();

        $this->assertSame(Audit::STATUS_FAILED, $response->json('data.status'));
        $this->assertStringContainsString('queue:work', $response->json('data.error_message'));
    }

    /** A real capture takes 72-121s, so a running audit gets a lot more rope. */
    public function test_a_running_audit_that_is_merely_slow_is_left_alone(): void
    {
        $audit = $this->audit(Audit::STATUS_RUNNING, 'capturing', 150);

        $this->getJson("/api/audits/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.status', Audit::STATUS_RUNNING);
    }

    public function test_a_running_audit_that_stopped_responding_is_failed_with_a_readable_message(): void
    {
        $audit = $this->audit(Audit::STATUS_RUNNING, 'capturing', 900);

        $response = $this->getJson("/api/audits/{$audit->id}")->assertOk();

        $this->assertSame(Audit::STATUS_FAILED, $response->json('data.status'));
        $this->assertStringContainsString('stopped', $response->json('data.error_message'));
        $this->assertStringNotContainsString('::', $response->json('data.error_message'));
    }

    public function test_a_finished_audit_is_never_touched(): void
    {
        $audit = $this->audit(Audit::STATUS_COMPLETED, null, 99_999);

        $this->getJson("/api/audits/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.status', Audit::STATUS_COMPLETED);
    }

    /** Marking it failed has to stick, so the retry button appears on reload too. */
    public function test_the_failure_is_written_not_just_reported(): void
    {
        $audit = $this->audit(Audit::STATUS_PENDING, null, 120);

        $this->getJson("/api/audits/{$audit->id}")->assertOk();

        $this->assertSame(Audit::STATUS_FAILED, $audit->fresh()->status);
    }
}
