<?php

namespace App\Services\Ai;

use App\Models\Audit;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Claude Sonnet 5 — the demo default, because the quality of the critique IS
 * the product and this is where it reads most like an expert.
 *
 * Everything goes in ONE request: all section images, the phone shot, the
 * numbers. Not one request per section.
 */
class ClaudeVisionAnalyzer implements VisionAnalyzer
{
    // Sonnet 5 introductory pricing, per million tokens.
    private const IN_PER_M = 2.00;
    private const OUT_PER_M = 10.00;

    public function __construct(private PromptBuilder $prompt = new PromptBuilder) {}

    public function modelName(): string
    {
        return (string) config('ai.claude.model');
    }

    public function analyse(Audit $audit): AnalysisResult
    {
        $key = config('ai.claude.key');
        if (blank($key)) {
            throw new RuntimeException('No Anthropic API key is set, so the page could not be analysed. Add ANTHROPIC_API_KEY to .env, or set AI_DRIVER=stub.');
        }

        $content = [];

        foreach ($this->prompt->images($audit) as $image) {
            $content[] = ['type' => 'text', 'text' => "Section: {$image['name']}"];
            $content[] = [
                'type'   => 'image',
                'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => $image['data']],
            ];
        }

        $content[] = ['type' => 'text', 'text' => $this->prompt->instruction($audit)];

        $response = Http::timeout(180)
            ->withHeaders([
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->modelName(),
                'max_tokens' => 8000,
                'messages'   => [['role' => 'user', 'content' => $content]],
                'output_config' => [
                    'format' => ['type' => 'json_schema', 'schema' => AuditSchema::forPrompt()],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic refused the request ('.$response->status().'). '.$response->json('error.message', ''));
        }

        // Safety classifiers can decline with a normal 200, so check before reading content.
        if ($response->json('stop_reason') === 'refusal') {
            throw new RuntimeException('The model declined to analyse this page. Try a different page, or set AI_DRIVER=stub for the demo.');
        }

        $text = collect($response->json('content', []))
            ->firstWhere('type', 'text')['text'] ?? null;

        $decoded = json_decode((string) $text, true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Claude did not return JSON.');
        }

        $in = (int) $response->json('usage.input_tokens', 0);
        $out = (int) $response->json('usage.output_tokens', 0);

        return new AnalysisResult(
            sections: $decoded['sections'] ?? [],
            model: $this->modelName(),
            tokens: $in + $out,
            cost: round(($in / 1_000_000 * self::IN_PER_M) + ($out / 1_000_000 * self::OUT_PER_M), 5),
            raw: $decoded,
        );
    }
}
