<?php

namespace App\Modules\Js\Seeders;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\Sitting;
use App\Modules\Js\Models\ExpungeCandidate;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Js\Models\SuggestedEdit;
use Illuminate\Database\Seeder;

class JsWindowDemoSeeder extends Seeder
{
    public function run(): void
    {
        $sitting = Sitting::query()
            ->where('session_no', 2025)
            ->where('sitting_no', 270)
            ->first();

        if (! $sitting) {
            return;
        }

        foreach ([['A', 0], ['B', 1800000]] as [$windowCode, $offset]) {
            ChiefConsolidation::query()->updateOrCreate(
                ['sitting_id' => $sitting->id, 'window_code' => $windowCode],
                ['starts_at_offset_ms' => $offset, 'duration_ms' => 1800000, 'status' => 'dual_committed'],
            );
        }

        $window = JsWindow::query()->updateOrCreate(
            ['sitting_id' => $sitting->id, 'window_code' => '1200-1300'],
            ['starts_at_offset_ms' => 0, 'duration_ms' => 3600000, 'status' => 'open'],
        );

        foreach ($this->suggestedEdits() as $row) {
            $block = $this->block($row['needle']);
            if (! $block) {
                continue;
            }

            SuggestedEdit::query()->updateOrCreate(
                ['window_id' => $window->id, 'block_id' => $block->id, 'source_name' => $row['source_name'], 'reason' => $row['reason']],
                [
                    'source' => $row['source'],
                    'source_member_id' => $this->memberId($row['roster_id'] ?? null),
                    'before' => $block->text,
                    'after' => str_replace($row['from'], $row['to'], $block->text),
                    'state' => 'pending',
                ],
            );
        }

        foreach ($this->expungeCandidates() as $row) {
            $block = $this->block($row['needle']);
            if (! $block) {
                continue;
            }

            ExpungeCandidate::query()->updateOrCreate(
                ['window_id' => $window->id, 'block_id' => $block->id, 'word' => $row['word']],
                [
                    'grounds' => $row['grounds'],
                    'master_db_ref' => $row['master_db_ref'],
                    'state' => 'pending',
                ],
            );
        }
    }

    private function block(string $needle): ?Block
    {
        return Block::query()
            ->where('text', 'like', '%'.$needle.'%')
            ->orderBy('id')
            ->first();
    }

    private function memberId(?string $rosterId): ?int
    {
        if (! $rosterId) {
            return null;
        }

        return Member::query()->where('roster_id', $rosterId)->value('id');
    }

    private function suggestedEdits(): array
    {
        return [
            [
                'source' => 'member',
                'source_name' => 'Shri R. Patil',
                'roster_id' => 'R025',
                'needle' => 'one thousand four hundred crore rupees',
                'from' => 'one thousand four hundred crore rupees',
                'to' => 'one thousand four hundred twenty crore rupees',
                'reason' => 'Budget annexure correction: ₹1400 cr should read ₹1420 cr.',
            ],
            [
                'source' => 'member',
                'source_name' => 'Shri V. Joshi',
                'roster_id' => 'R029',
                'needle' => 'additional funding under Samagra Shiksha',
                'from' => 'additional funding under Samagra Shiksha',
                'to' => 'additional funding of forty-two thousand crore under Samagra Shiksha',
                'reason' => 'Member note says forty-two thousand crore, not forty thousand crore.',
            ],
            [
                'source' => 'minister',
                'source_name' => 'Shri M. Naidu',
                'roster_id' => 'M011',
                'needle' => '67 शहरों',
                'from' => '67 शहरों',
                'to' => '71 शहरों',
                'reason' => 'Power Ministry corrected smart-meter city count.',
            ],
            [
                'source' => 'minister',
                'source_name' => 'Shri Rajnath Singh',
                'roster_id' => 'M003',
                'needle' => 'स्वदेशी रक्षा उत्पादन ने अभूतपूर्व प्रगति',
                'from' => 'अभूतपूर्व प्रगति',
                'to' => '1.29 लाख करोड़ रुपये का उत्पादन',
                'reason' => 'Defence production figure revised from 1.27 to 1.29 lakh crore.',
            ],
            [
                'source' => 'minister',
                'source_name' => 'Shri Ashwini Vaishnaw',
                'roster_id' => 'M009',
                'needle' => '7,234 स्टेशनों',
                'from' => '7,234 स्टेशनों',
                'to' => '7,341 स्टेशनों',
                'reason' => 'Railway safety audit station count corrected.',
            ],
            [
                'source' => 'ai',
                'source_name' => 'AI numeric verifier',
                'needle' => '92,400 किलोमीटर',
                'from' => '92,400 किलोमीटर',
                'to' => '92,800 किलोमीटर',
                'reason' => 'Cross-check against ministry table for track section length.',
            ],
            [
                'source' => 'member',
                'source_name' => 'Smt. T. Roy',
                'roster_id' => 'R028',
                'needle' => 'within ninety days',
                'from' => 'within ninety days',
                'to' => 'within one hundred twenty days',
                'reason' => 'Written submission uses one hundred twenty days.',
            ],
            [
                'source' => 'ai',
                'source_name' => 'AI geography verifier',
                'needle' => 'Hassan, Sakleshpur, and Mangaluru',
                'from' => 'Hassan, Sakleshpur, and Mangaluru',
                'to' => 'Hassan, Sakleshpur, Bantwal, and Mangaluru',
                'reason' => 'Route segment omitted Bantwal.',
            ],
            [
                'source' => 'minister',
                'source_name' => 'Minister of Power office',
                'roster_id' => 'M011',
                'needle' => 'फीडर पृथक्करण तेजी से चल रहा है',
                'from' => 'तेजी से चल रहा है',
                'to' => 'निर्धारित समय-सारिणी के अनुसार चल रहा है',
                'reason' => 'Minister office prefers official phrasing.',
            ],
            [
                'source' => 'ai',
                'source_name' => 'AI style pass',
                'needle' => 'Bharat Net runs in parallel',
                'from' => 'Bharat Net',
                'to' => 'BharatNet',
                'reason' => 'Official programme spelling.',
            ],
            [
                'source' => 'member',
                'source_name' => 'Shri K. Khan',
                'roster_id' => 'R030',
                'needle' => 'air quality in NCR',
                'from' => 'air quality in NCR',
                'to' => 'air quality across the National Capital Region',
                'reason' => 'Calling Attention title should expand NCR.',
            ],
            [
                'source' => 'minister',
                'source_name' => 'Parliamentary Affairs desk',
                'roster_id' => 'M013',
                'needle' => 'twenty twenty-three to twenty twenty-four',
                'from' => 'twenty twenty-three to twenty twenty-four',
                'to' => '2023-24',
                'reason' => 'Annual report period should use financial-year style.',
            ],
        ];
    }

    private function expungeCandidates(): array
    {
        return [
            [
                'needle' => 'visible cracks have appeared after recent floods',
                'word' => 'visible cracks',
                'grounds' => 'Potentially imputes unsafe condition before inspection report is tabled.',
                'master_db_ref' => 'MDB-EXP-2026-0007',
            ],
            [
                'needle' => 'سست',
                'word' => 'سست',
                'grounds' => 'Unparliamentary characterization flagged for SG confirmation.',
                'master_db_ref' => 'MDB-EXP-2026-0012',
            ],
            [
                'needle' => 'why enforcement fails every winter',
                'word' => 'fails',
                'grounds' => 'Direct allegation against enforcement agencies.',
                'master_db_ref' => 'MDB-EXP-2026-0021',
            ],
        ];
    }
}
