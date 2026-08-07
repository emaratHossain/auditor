<?php

namespace App\Services\Rewrite;

readonly class RewriteResult
{
    public function __construct(
        /** @var array<int,array{text:string,reason:string}> */
        public array $variants,
        public string $model,
        public int $tokens = 0,
    ) {}
}
