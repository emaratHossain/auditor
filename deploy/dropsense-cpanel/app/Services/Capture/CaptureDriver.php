<?php

namespace App\Services\Capture;

use App\Models\Audit;

interface CaptureDriver
{
    /**
     * Photograph the page and write one screenshot_sections row per image.
     *
     * @return int how many sections were captured
     */
    public function capture(Audit $audit): int;

    /**
     * Which driver this is, recorded on the audit.
     *
     * The report needs to know whether anything ever actually opened the page,
     * because a stub capture invents both the pictures and the words.
     */
    public function name(): string;
}
