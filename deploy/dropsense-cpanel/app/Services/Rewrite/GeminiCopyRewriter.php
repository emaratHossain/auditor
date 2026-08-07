<?php

namespace App\Services\Rewrite;

use App\Models\Audit;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * The rewrite on Gemini, so a deployment can run on one provider.
 *
 * Without this, AI_REWRITE_DRIVER=gemini resolved to the stub and every "Rewrite
 * this" button served canned copy from a button that looked live.
 *
 * Same call as GeminiVisionAnalyzer, text only and a different schema — which is
 * why it is a tenth of a cent and can afford to run on a click rather than in
 * the pipeline.
 */
class GeminiCopyRewriter implements CopyRewriter
{
    public function __construct(private RewritePrompt $prompt = new RewritePrompt) {}

    public function modelName(): string
    {
        return (string) config('ai.gemini.model');
    }

    public function rewrite(
        Audit $audit,
        string $sectionName,
        string $element,
        string $original,
        string $critique,
        ?string $insight,
    ): RewriteResult {
        $key = config('ai.gemini.key');

        if (blank($key)) {
            throw new RuntimeException('No Gemini API key is set, so the copy could not be rewritten. Add GEMINI_API_KEY to .env, or set AI_REWRITE_DRIVER=stub.');
        }

        $response = Http::timeout(60)
            ->withHeaders(['x-goog-api-key' => $key])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->modelName()}:generateContent", [
                'contents' => [['parts' => [['text' => $this->prompt->instruction(
                    url: $audit->page->url,
                    sectionName: $sectionName,
                    element: $element,
                    original: $original,
                    critique: $critique,
                    insight: $insight,
                )]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema'   => $this->responseSchema(),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('The rewrite request was refused ('.$response->status().'). '.$response->json('error.message', ''));
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $decoded = json_decode((string) $text, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The rewrite reply was not JSON at all.');
        }

        $usage = $response->json('usageMetadata', []);
        $in = (int) ($usage['promptTokenCount'] ?? 0);

        // Thinking is billed as output and reported apart from it — see
        // GeminiVisionAnalyzer, where undercounting it also weakened the ceiling.
        $out = (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0);

        return new RewriteResult(
            variants: $decoded['variants'] ?? [],
            model: $this->modelName(),
            tokens: $in + $out,
        );
    }

    /**
     * RewriteSchema::forPrompt() in the dialect Gemini's responseSchema speaks —
     * upper-case type names, and no keywords it does not accept. RewriteService
     * still validates the reply against the real schema afterwards, so this being
     * a looser statement of the same shape costs nothing.
     */
    private function responseSchema(): array
    {
        return [
            'type'       => 'OBJECT',
            'required'   => ['variants'],
            'properties' => [
                'variants' => [
                    'type'     => 'ARRAY',
                    'minItems' => 2,
                    'maxItems' => RewriteSchema::MAX_VARIANTS,
                    'items'    => [
                        'type'       => 'OBJECT',
                        'required'   => ['text', 'reason'],
                        'properties' => [
                            'text'   => ['type' => 'STRING'],
                            'reason' => ['type' => 'STRING'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
