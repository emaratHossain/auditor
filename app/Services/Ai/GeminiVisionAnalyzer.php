<?php

namespace App\Services\Ai;

use App\Models\Audit;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gemini 2.5 Flash. Used during the build week because its free tier costs
 * nothing, which makes iterating on the prompt free too.
 */
class GeminiVisionAnalyzer implements VisionAnalyzer
{
    // Published free-tier rates are 0; these are the paid rates per million tokens,
    // so the recorded cost is honest if the key is ever a paid one.
    private const IN_PER_M = 0.30;
    private const OUT_PER_M = 2.50;

    public function __construct(private PromptBuilder $prompt = new PromptBuilder) {}

    public function modelName(): string
    {
        return (string) config('ai.gemini.model');
    }

    public function analyse(Audit $audit): AnalysisResult
    {
        $key = config('ai.gemini.key');
        if (blank($key)) {
            throw new RuntimeException('No Gemini API key is set, so the page could not be analysed. Add GEMINI_API_KEY to .env, or set AI_DRIVER=stub.');
        }

        $parts = [['text' => $this->prompt->instruction($audit)]];

        foreach ($this->prompt->images($audit) as $image) {
            $parts[] = ['text' => "Section: {$image['name']}"];
            $parts[] = ['inline_data' => ['mime_type' => $image['mime'], 'data' => $image['data']]];
        }

        $response = Http::timeout(150)
            ->withHeaders(['x-goog-api-key' => $key])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->modelName()}:generateContent", [
                'contents' => [['parts' => $parts]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema'   => $this->responseSchema(),
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini refused the request ('.$response->status().'). '.$response->json('error.message', ''));
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $decoded = json_decode((string) $text, true);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Gemini did not return JSON.');
        }

        $usage = $response->json('usageMetadata', []);
        $in = (int) ($usage['promptTokenCount'] ?? 0);
        $out = (int) ($usage['candidatesTokenCount'] ?? 0);

        return new AnalysisResult(
            sections: $decoded['sections'] ?? [],
            model: $this->modelName(),
            tokens: $in + $out,
            cost: round(($in / 1_000_000 * self::IN_PER_M) + ($out / 1_000_000 * self::OUT_PER_M), 5),
            raw: $decoded,
        );
    }

    /** Gemini rejects the "enum on a string" form used elsewhere, so it is spelled out here. */
    private function responseSchema(): array
    {
        return [
            'type'       => 'OBJECT',
            'required'   => ['sections'],
            'properties' => [
                'sections' => [
                    'type'  => 'ARRAY',
                    'items' => [
                        'type'     => 'OBJECT',
                        'required' => ['section', 'score', 'problems'],
                        'properties' => [
                            'section'  => ['type' => 'STRING'],
                            'score'    => ['type' => 'INTEGER'],
                            'problems' => [
                                'type'  => 'ARRAY',
                                'items' => [
                                    'type'     => 'OBJECT',
                                    'required' => ['what', 'why', 'fix', 'severity', 'category'],
                                    'properties' => [
                                        'what'     => ['type' => 'STRING'],
                                        'why'      => ['type' => 'STRING'],
                                        'fix'      => ['type' => 'STRING'],
                                        'severity' => ['type' => 'INTEGER'],
                                        'category' => ['type' => 'STRING', 'enum' => AuditSchema::ALLOWED_CATEGORIES],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
