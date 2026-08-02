<?php

namespace App\Services\Ai;

use InvalidArgumentException;

/**
 * The agreed shape of the AI's reply.
 *
 * A vision model will eventually return something off-shape. When it does the
 * pipeline must fail cleanly rather than write half-parsed rows — a half-saved
 * audit that looks complete is worse than one that says it failed.
 */
class AuditSchema
{
    public const ALLOWED_CATEGORIES = [
        'cta', 'layout', 'content', 'ui', 'trust', 'mobile', 'accessibility', 'performance',
    ];

    /** The JSON shape asked of the model. Shared by every driver. */
    public static function forPrompt(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['sections'],
            'properties' => [
                'sections' => [
                    'type'  => 'array',
                    'items' => [
                        'type'     => 'object',
                        'required' => ['section', 'score', 'problems'],
                        'properties' => [
                            'section'  => ['type' => 'string'],
                            'score'    => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'problems' => [
                                'type'  => 'array',
                                'items' => [
                                    'type'     => 'object',
                                    'required' => ['what', 'why', 'fix', 'severity', 'category'],
                                    'properties' => [
                                        'what'     => ['type' => 'string'],
                                        'why'      => ['type' => 'string'],
                                        'fix'      => ['type' => 'string'],
                                        'severity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                                        'category' => ['type' => 'string', 'enum' => self::ALLOWED_CATEGORIES],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws InvalidArgumentException with a message a human can act on
     */
    public function validate(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The AI reply was not JSON at all.');
        }

        if (! isset($decoded['sections']) || ! is_array($decoded['sections']) || $decoded['sections'] === []) {
            throw new InvalidArgumentException('The AI reply carried no sections.');
        }

        foreach ($decoded['sections'] as $i => $section) {
            $where = "section #{$i}";

            foreach (['section', 'score', 'problems'] as $field) {
                if (! array_key_exists($field, $section)) {
                    throw new InvalidArgumentException("The AI reply is missing '{$field}' on {$where}.");
                }
            }

            if (! is_string($section['section']) || trim($section['section']) === '') {
                throw new InvalidArgumentException("The AI reply has an empty section name on {$where}.");
            }

            if (! is_int($section['score']) || $section['score'] < 0 || $section['score'] > 100) {
                throw new InvalidArgumentException("The AI reply has a score outside 0-100 on {$where}.");
            }

            if (! is_array($section['problems'])) {
                throw new InvalidArgumentException("The AI reply has a malformed problems list on {$where}.");
            }

            foreach ($section['problems'] as $j => $problem) {
                $at = "problem #{$j} of {$where}";

                foreach (['what', 'why', 'fix', 'severity', 'category'] as $field) {
                    if (! array_key_exists($field, $problem)) {
                        throw new InvalidArgumentException("The AI reply is missing '{$field}' on {$at}.");
                    }
                }

                if (! is_int($problem['severity']) || $problem['severity'] < 1 || $problem['severity'] > 5) {
                    throw new InvalidArgumentException("The AI reply has a severity outside 1-5 on {$at}.");
                }

                if (! in_array($problem['category'], self::ALLOWED_CATEGORIES, true)) {
                    throw new InvalidArgumentException("The AI reply used an unknown category '{$problem['category']}' on {$at}.");
                }
            }
        }

        return $decoded;
    }
}
