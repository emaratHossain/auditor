<?php

namespace App\Services\Rewrite;

use InvalidArgumentException;

/**
 * The agreed shape of a rewrite reply.
 *
 * Held to the same bar as AuditSchema: a malformed reply throws rather than
 * writing a half-parsed row, because a rewrite that looks finished and is not
 * is worse than an honest failure.
 */
class RewriteSchema
{
    /** More than this is more than anyone reads on stage. */
    public const MAX_VARIANTS = 3;

    /** The JSON shape asked of the model. Shared by every driver. */
    public static function forPrompt(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['variants'],
            'properties' => [
                'variants' => [
                    'type'     => 'array',
                    'minItems' => 2,
                    'maxItems' => self::MAX_VARIANTS,
                    'items'    => [
                        'type'       => 'object',
                        'required'   => ['text', 'reason'],
                        'properties' => [
                            'text'   => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{variants:array<int,array{text:string,reason:string}>}
     *
     * @throws InvalidArgumentException with a message a human can act on
     */
    public function validate(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The rewrite reply was not JSON at all.');
        }

        $variants = $decoded['variants'] ?? null;

        if (! is_array($variants) || $variants === []) {
            throw new InvalidArgumentException('The rewrite reply carried no versions.');
        }

        foreach ($variants as $i => $variant) {
            $where = 'version #'.($i + 1);

            if (! is_array($variant)) {
                throw new InvalidArgumentException("The rewrite reply has a malformed {$where}.");
            }

            foreach (['text', 'reason'] as $field) {
                if (! array_key_exists($field, $variant)) {
                    throw new InvalidArgumentException("The rewrite reply is missing '{$field}' on {$where}.");
                }

                if (! is_string($variant[$field]) || trim($variant[$field]) === '') {
                    throw new InvalidArgumentException("The rewrite reply has an empty '{$field}' on {$where}.");
                }
            }
        }

        // Trimming is not rejecting. Six versions is not malformed, it is just noise.
        $decoded['variants'] = array_slice(array_values($variants), 0, self::MAX_VARIANTS);

        return $decoded;
    }
}
