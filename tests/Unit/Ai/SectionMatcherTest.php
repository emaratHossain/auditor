<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\SectionMatcher;
use PHPUnit\Framework\TestCase;

/**
 * A finding that does not land on a captured section is thrown away later by the
 * evidence guarantee, because there is no section to hang a number on. That is
 * correct behaviour on a genuinely unknown section and a silent, total loss when
 * the model merely spelled a name differently.
 *
 * So the matcher is deliberately lopsided: it tries hard to recognise a name it
 * has really been given, and refuses the moment it would have to guess. A wrong
 * link is worse than no link — it would attach a real number to the wrong part
 * of the page, which is the one thing this product promises never to do.
 */
class SectionMatcherTest extends TestCase
{
    private function matcher(): SectionMatcher
    {
        return new SectionMatcher([
            'Section 1',
            'We Provide Tech Solutions & Help You to',
            'Learn What Arraytics Products Can Do for',
            'WP',
            'SaaS',
            'Our Contributions',
        ]);
    }

    public function test_it_matches_a_name_the_model_copied_exactly(): void
    {
        $this->assertSame('Our Contributions', $this->matcher()->match('Our Contributions'));
    }

    public function test_it_matches_when_only_the_case_and_punctuation_differ(): void
    {
        $this->assertSame('Our Contributions', $this->matcher()->match('our contributions:'));
    }

    public function test_it_matches_a_truncated_capture_against_the_models_fuller_name(): void
    {
        // The capture truncates the headline; a model reading the picture writes
        // the whole sentence out. Same section.
        $this->assertSame(
            'We Provide Tech Solutions & Help You to',
            $this->matcher()->match('We Provide Tech Solutions & Help You to Grow Your Business'),
        );
    }

    public function test_it_matches_a_short_captured_name_inside_a_longer_one(): void
    {
        $this->assertSame('WP', $this->matcher()->match('WP Plugins'));
    }

    public function test_it_refuses_a_name_the_model_invented(): void
    {
        $this->assertNull($this->matcher()->match('Hero Section'));
        $this->assertNull($this->matcher()->match('Mobile Experience'));
        $this->assertNull($this->matcher()->match('Product Showcase'));
    }

    public function test_it_refuses_rather_than_choose_between_two_candidates(): void
    {
        // Mentions both "WP" and "SaaS". Picking either would be a guess.
        $this->assertNull($this->matcher()->match('WP and SaaS Products'));
    }

    public function test_it_does_not_match_on_a_fragment_that_is_not_a_whole_word(): void
    {
        // "WP" must not match because the letters appear inside "WPBakery".
        $this->assertNull($this->matcher()->match('WPBakery Builder Block'));
    }

    public function test_it_refuses_an_empty_name(): void
    {
        $this->assertNull($this->matcher()->match(''));
        $this->assertNull($this->matcher()->match('   '));
    }
}
