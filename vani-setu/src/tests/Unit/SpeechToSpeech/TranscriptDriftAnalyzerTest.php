<?php

namespace Tests\Unit\SpeechToSpeech;

use App\Modules\SpeechToSpeech\Services\Recheck\TranscriptDriftAnalyzer;
use Tests\TestCase;

class TranscriptDriftAnalyzerTest extends TestCase
{
    public function test_identical_text_scores_one_and_passes(): void
    {
        $analyzer = new TranscriptDriftAnalyzer();
        $v = $analyzer->compare('budget discussion is underway', 'Budget discussion is underway.', 0.95);

        $this->assertSame('passed', $v['state']);
        $this->assertSame(1.0, $v['score']);
        $this->assertSame(0.0, $v['wer']);
        $this->assertNull($v['corrected_text']);
    }

    public function test_minor_punctuation_and_case_drift_still_passes(): void
    {
        $analyzer = new TranscriptDriftAnalyzer();
        $v = $analyzer->compare(
            'The Chair recognizes the Member from Bihar',
            'The Chair recognizes the Member from Bihar.',
            0.95,
        );

        $this->assertSame('passed', $v['state']);
        $this->assertSame(1.0, $v['score']);
    }

    public function test_small_word_change_within_threshold_passes(): void
    {
        // 1 substitution in a 9-token sentence => WER ≈ 0.111 < 0.15 → passes
        $analyzer = new TranscriptDriftAnalyzer();
        $v = $analyzer->compare(
            'The Honourable Member raised a point of order today',
            'The Honourable Member raised a point of order now',
            0.92,
        );

        $this->assertSame('passed', $v['state']);
        $this->assertGreaterThan(0.85, $v['score']);
    }

    public function test_high_wer_low_confidence_marks_drift_without_correction(): void
    {
        $analyzer = new TranscriptDriftAnalyzer(driftThreshold: 0.15, correctionConfidence: 0.85);
        // 4 substitutions in 8 tokens => WER 0.5; low conf => no correction
        $v = $analyzer->compare(
            'budget for ministry of railways is approved today',
            'audit for company of automakers is rejected today',
            0.55,
        );

        $this->assertSame('drift', $v['state']);
        $this->assertNull($v['corrected_text']);
        $this->assertGreaterThan(0.3, $v['wer']);
    }

    public function test_high_wer_high_confidence_writes_correction(): void
    {
        $analyzer = new TranscriptDriftAnalyzer(driftThreshold: 0.15, correctionConfidence: 0.85);
        $v = $analyzer->compare(
            'the honourable member raise point of order',
            'The Honourable Member raised a point of order.',
            0.95,
        );

        $this->assertSame('corrected', $v['state']);
        $this->assertNotNull($v['corrected_text']);
        $this->assertSame('The Honourable Member raised a point of order.', $v['corrected_text']);
    }

    public function test_empty_inputs_pass(): void
    {
        $analyzer = new TranscriptDriftAnalyzer();
        $v = $analyzer->compare('', '', 1.0);
        $this->assertSame('passed', $v['state']);
        $this->assertSame(1.0, $v['score']);
    }

    public function test_source_empty_but_candidate_present_flags_drift(): void
    {
        $analyzer = new TranscriptDriftAnalyzer();
        $v = $analyzer->compare('', 'unexpected transcribed text here', 0.5);

        $this->assertSame('drift', $v['state']);
        $this->assertSame(0.0, $v['score']);
    }

    public function test_devanagari_text_pairs_normalize_through_tokenization(): void
    {
        $analyzer = new TranscriptDriftAnalyzer();
        $v = $analyzer->compare('बजट चर्चा जारी है', 'बजट चर्चा जारी है।', 0.9);

        $this->assertSame('passed', $v['state']);
        $this->assertSame(1.0, $v['score']);
    }
}
