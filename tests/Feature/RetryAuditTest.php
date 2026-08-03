<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Page;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Try again is the button on every failure screen, so it is the one button that
 * must never itself fail. A 500 there leaves the user with a dead end on the
 * page that was already telling them something went wrong.
 */
class RetryAuditTest extends TestCase
{
    use RefreshDatabase;

    private function page(): Page
    {
        return Page::create(['name' => 'Retry test', 'url' => 'https://example.com/retry']);
    }

    public function test_it_reruns_with_the_numbers_already_on_file(): void
    {
        Bus::fake();
        $page = $this->page();

        $first = app(AuditService::class)->start($page, [
            'visitors' => 5000, 'bounce_rate' => 60, 'conversion_rate' => 2,
            'cta_click_rate' => 3.5, 'rage_clicks' => ['Hero' => 90], 'source' => 'demo',
        ]);

        $response = $this->postJson("/api/audits/{$first->id}/retry")->assertCreated();

        $metrics = Audit::find($response->json('data.id'))->metrics;

        $this->assertSame(5000, $metrics->visitors);
        $this->assertSame(['Hero' => 90], $metrics->rage_clicks);
        $this->assertSame('demo', $metrics->source, 'a retry of a demo audit is still a demo audit');
    }

    /**
     * An audit with no metrics row cannot be re-run — there are no numbers to
     * re-run it with, and inventing them is the one thing this product must
     * never do. So it has to say that, not blow up.
     */
    public function test_retrying_an_audit_with_no_numbers_explains_itself(): void
    {
        $audit = $this->page()->audits()->create(['status' => Audit::STATUS_FAILED]);

        $response = $this->postJson("/api/audits/{$audit->id}/retry")
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertStringNotContainsString('Undefined array key', $response->json('message'));
        $this->assertStringNotContainsString('::', $response->json('message'));
    }

    /** The service is a boundary. A missing required number is a sentence, not a PHP notice. */
    public function test_starting_an_audit_without_the_required_numbers_fails_readably(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('visitors');

        app(AuditService::class)->start($this->page(), ['bounce_rate' => 60]);
    }
}
