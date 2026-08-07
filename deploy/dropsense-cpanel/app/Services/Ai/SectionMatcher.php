<?php

namespace App\Services\Ai;

/**
 * Puts a model's section name back onto the section that was actually captured.
 *
 * The prompt asks the model to copy the captured names verbatim, and mostly it
 * does. This exists for the times it does not — a rewrapped headline, a stray
 * colon, a name written out in full where the capture truncated it. Without it
 * those findings arrive with no section, and the evidence guarantee drops every
 * one of them: the report comes back saying nothing could be proven, on a page
 * the model described in accurate detail.
 *
 * It will not guess. Two plausible sections means no match, because linking a
 * finding to the wrong section would put a real number next to the wrong part of
 * the page — a more expensive failure than showing one finding fewer.
 */
class SectionMatcher
{
    /** @var array<string,string> normalised name => the captured name as written */
    private array $byNormalised = [];

    /** @var array<string,array<int,string>> the captured name => its words */
    private array $words = [];

    /** @param array<int,string> $capturedNames */
    public function __construct(array $capturedNames)
    {
        foreach ($capturedNames as $name) {
            $normalised = $this->normalise($name);

            if ($normalised === '') {
                continue;
            }

            // First writing of a name wins, so a duplicate cannot displace it.
            $this->byNormalised[$normalised] ??= $name;
            $this->words[$name] = explode(' ', $normalised);
        }
    }

    /** The captured name this belongs to, or null when that cannot be known. */
    public function match(string $aiName): ?string
    {
        $normalised = $this->normalise($aiName);

        if ($normalised === '') {
            return null;
        }

        if (isset($this->byNormalised[$normalised])) {
            return $this->byNormalised[$normalised];
        }

        $aiWords = explode(' ', $normalised);
        $candidates = [];

        foreach ($this->words as $name => $capturedWords) {
            if ($this->containsRun($aiWords, $capturedWords) || $this->containsRun($capturedWords, $aiWords)) {
                $candidates[] = $name;
            }
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * Whole words only, in order and unbroken. Comparing words rather than
     * characters is what stops "WP" matching "WPBakery".
     *
     * @param  array<int,string>  $haystack
     * @param  array<int,string>  $needle
     */
    private function containsRun(array $haystack, array $needle): bool
    {
        $span = count($needle);

        if ($span === 0 || $span > count($haystack)) {
            return false;
        }

        for ($start = 0; $start + $span <= count($haystack); $start++) {
            if (array_slice($haystack, $start, $span) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** Lower case, punctuation gone, single spaces — "Our Contributions:" and "our contributions" are one name. */
    private function normalise(string $name): string
    {
        $lettersAndDigits = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($name));

        return trim(preg_replace('/\s+/', ' ', (string) $lettersAndDigits));
    }
}
