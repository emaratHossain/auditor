<?php

namespace App\Services\Rewrite;

use App\Models\Audit;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * One text-only call. Roughly a tenth of a cent, so a click costs nothing worth
 * thinking about — which is exactly why it can be on-demand.
 */
class ClaudeCopyRewriter implements CopyRewriter
{
    public function __construct(private RewritePrompt $prompt = new RewritePrompt) {}

    public function modelName(): string
    {
        return (string) config('ai.claude.model');
    }

    public function rewrite(
        Audit $audit,
        string $sectionName,
        string $element,
        string $original,
        string $critique,
        ?string $insight,
    ): RewriteResult {
        $key = config('ai.claude.key');

        if (blank($key)) {
            throw new RuntimeException('No Anthropic API key is set, so the copy could not be rewritten. Add ANTHROPIC_API_KEY to .env, or set AI_REWRITE_DRIVER=stub.');
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->modelName(),
                'max_tokens' => 1200,
                'messages'   => [['role' => 'user', 'content' => $this->prompt->instruction(
                    url: $audit->page->url,
                    sectionName: $sectionName,
                    element: $element,
                    original: $original,
                    critique: $critique,
                    insight: $insight,
                )]],
                'output_config' => [
                    'format' => ['type' => 'json_schema', 'schema' => RewriteSchema::forPrompt()],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('The rewrite request was refused ('.$response->status().'). '.$response->json('error.message', ''));
        }

        // Safety classifiers can decline with a normal 200, so check before reading content.
        if ($response->json('stop_reason') === 'refusal') {
            throw new RuntimeException('The model declined to rewrite this copy.');
        }

        $text = collect($response->json('content', []))->firstWhere('type', 'text')['text'] ?? null;
        $decoded = json_decode((string) $text, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The rewrite reply was not JSON at all.');
        }

        return new RewriteResult(
            variants: $decoded['variants'] ?? [],
            model: $this->modelName(),
            tokens: (int) $response->json('usage.input_tokens', 0) + (int) $response->json('usage.output_tokens', 0),
        );
    }
}
