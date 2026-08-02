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
}
