<?php

namespace Tests\Unit\Rewrite;

use App\Services\Rewrite\RewriteSchema;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * A model will eventually return something off-shape. When it does, the row must
 * not be written — a half-saved rewrite that looks finished is worse than an
 * error saying the call failed.
 */
class RewriteSchemaTest extends TestCase
{
    private function schema(): RewriteSchema
    {
        return new RewriteSchema;
    }

    public function test_it_accepts_a_well_formed_reply(): void
    {
        $reply = ['variants' => [
            ['text' => 'Ship in a week, not a quarter', 'reason' => 'Names the outcome and the timescale.'],
            ['text' => 'Cut your release cycle in half', 'reason' => 'Quantifies the promise.'],
        ]];

        $this->assertSame($reply['variants'], $this->schema()->validate($reply)['variants']);
    }

    public function test_a_reply_that_is_not_json_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('was not JSON');

        $this->schema()->validate('sorry, I cannot help with that');
    }

    public function test_a_reply_with_no_variants_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carried no versions');

        $this->schema()->validate(['variants' => []]);
    }

    public function test_a_variant_missing_its_reason_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("missing 'reason'");

        $this->schema()->validate(['variants' => [['text' => 'Some new headline']]]);
    }

    public function test_a_variant_with_empty_text_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');

        $this->schema()->validate(['variants' => [['text' => '   ', 'reason' => 'because']]]);
    }

    public function test_more_than_three_versions_is_trimmed_not_rejected(): void
    {
        $reply = ['variants' => array_map(
            fn ($i) => ['text' => "Version {$i}", 'reason' => "Reason {$i}"],
            range(1, 6),
        )];

        // Six is not malformed, it is just more than anyone reads on stage.
        $this->assertCount(3, $this->schema()->validate($reply)['variants']);
    }
}
