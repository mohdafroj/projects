<?php

namespace Database\Seeders;

use App\Modules\Chief\Models\ChiefConsolidation;
use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\MemberCustom;
use App\Modules\Core\Models\Sitting;
use App\Modules\Core\Models\Slot;
use App\Modules\Core\Models\SlotAssignment;
use App\Modules\Core\Models\Synopsis;
use App\Modules\Core\Models\User;
use App\Modules\Director\Models\DirectorPublishJob;
use App\Modules\Js\Models\ExpungeCandidate;
use App\Modules\Js\Models\JsWindow;
use App\Modules\Js\Models\SuggestedEdit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class RealSittingSeeder extends Seeder
{
    private const PROJECT_DIR = '/mnt/project';

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        DB::transaction(function () {
            $sitting = Sitting::query()->updateOrCreate(
                ['session_no' => 267, 'sitting_no' => 1],
                [
                    'sitting_date' => '2025-12-01',
                    'status' => 'live',
                    'started_at' => '2025-12-01 11:00:00',
                    'ended_at' => '2025-12-01 18:00:00',
                ],
            );

            $this->seedMembers();

            $rows = $this->transcriptRows();
            $codes = $this->slotCodes();

            foreach ($codes as $index => $code) {
                $slot = Slot::query()->updateOrCreate(
                    ['sitting_id' => $sitting->id, 'code' => $code],
                    [
                        'start_offset_ms' => $index * 360000,
                        'duration_ms' => 360000,
                        'topic' => $this->topicFor($index),
                        'status' => $this->slotStatus($index),
                    ],
                );

                $this->seedBlocks($slot, $rows, $index);
                $this->seedAssignments($slot, $index);
            }

            $this->seedSynopses($sitting);
            $this->seedChiefAndJsWindows($sitting);
        });
    }

    private function seedMembers(): void
    {
        foreach ($this->members() as $row) {
            Member::query()->updateOrCreate(
                ['roster_id' => $row[0]],
                [
                    'category' => $row[1],
                    'name_en' => $row[2],
                    'name_hi' => $row[3],
                    'party' => $row[4],
                    'state_jur' => $row[5],
                    'role_title' => $row[6],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedBlocks(Slot $slot, array $rows, int $slotIndex): void
    {
        for ($i = 0; $i < 3; $i++) {
            $row = $rows[($slotIndex * 3 + $i) % count($rows)];
            [$member, $customMember] = $this->resolveSpeaker($slot, $row);
            $lang = $i === 1 && $slotIndex % 3 === 0 ? 'hi' : 'en';
            $text = $row['text'];

            Block::query()->updateOrCreate(
                ['slot_id' => $slot->id, 'sequence' => $i + 1],
                [
                    'start_ms' => $slot->start_offset_ms + ($i * 120000),
                    'end_ms' => $slot->start_offset_ms + (($i + 1) * 120000) - 1,
                    'original_lang' => $lang,
                    'chief_lang' => $lang === 'hi' ? 'hi' : 'en',
                    'ai_action' => $lang === 'hi' ? 'translated' : 'native',
                    'ai_text' => $text,
                    'text' => $text,
                    'translated_text' => $lang === 'hi' ? 'Translation pending verification: '.$text : null,
                    'member_id' => $member?->id,
                    'custom_member_id' => $customMember?->id,
                    'version' => 1,
                    'reporter_edit_count' => $slotIndex % 5 === 0 ? 1 : 0,
                ],
            );
        }
    }

    private function seedAssignments(Slot $slot, int $index): void
    {
        foreach (['en' => 'RPT-001', 'hi' => 'RPT-002'] as $lang => $employeeId) {
            $reporter = User::query()->where('employee_id', $employeeId)->firstOrFail();
            [$status, $stage] = $this->assignmentState($index);

            SlotAssignment::query()->updateOrCreate(
                ['slot_id' => $slot->id, 'lang_role' => $lang],
                [
                    'user_id' => $reporter->id,
                    'assignee_user_id' => null,
                    'status' => $status,
                    'workflow_stage' => $stage,
                    'committed_at' => $status === 'committed' ? now()->subMinutes(90 - min($index, 59)) : null,
                    'committed_audit_log_id' => null,
                    'last_workflow_action_at' => now()->subMinutes(70 - min($index, 59)),
                ],
            );
        }
    }

    private function seedChiefAndJsWindows(Sitting $sitting): void
    {
        foreach ([['A', 0, 'open'], ['B', 1800000, 'dual_committed']] as [$code, $offset, $status]) {
            ChiefConsolidation::query()->updateOrCreate(
                ['sitting_id' => $sitting->id, 'window_code' => $code],
                ['starts_at_offset_ms' => $offset, 'duration_ms' => 1800000, 'status' => $status],
            );
        }

        foreach ([
            ['1200-1300', 0, 'open'],
            ['1300-1400', 3600000, 'sent_to_sg'],
            ['1400-1500', 7200000, 'approved'],
        ] as [$code, $offset, $status]) {
            $window = JsWindow::query()->updateOrCreate(
                ['sitting_id' => $sitting->id, 'window_code' => $code],
                ['starts_at_offset_ms' => $offset, 'duration_ms' => 3600000, 'status' => $status],
            );

            $blocks = $window->blocks()->limit(4)->get();
            foreach ($blocks as $idx => $block) {
                SuggestedEdit::query()->updateOrCreate(
                    ['window_id' => $window->id, 'block_id' => $block->id, 'source_name' => 'Synopsis desk'],
                    [
                        'source' => 'minister',
                        'before' => $block->text,
                        'after' => $block->text.' [Checked against synopsis]',
                        'reason' => 'Align verbatim record with sitting synopsis.',
                        'state' => $idx === 0 && $status !== 'open' ? 'accepted' : 'pending',
                    ],
                );
            }

            $candidateBlock = $blocks->first();
            if ($candidateBlock) {
                ExpungeCandidate::query()->updateOrCreate(
                    ['window_id' => $window->id, 'block_id' => $candidateBlock->id, 'word' => 'unverified allegation'],
                    [
                        'grounds' => 'Potentially unparliamentary imputation pending SG decision.',
                        'master_db_ref' => 'MDB-RS-2025-12-01-001',
                        'state' => $status === 'approved' ? 'confirmed' : 'pending',
                    ],
                );
            }

            if ($status === 'approved') {
                DirectorPublishJob::query()->updateOrCreate(
                    ['window_id' => $window->id],
                    [
                        'director_user_id' => User::query()->where('employee_id', 'DIR-001')->value('id'),
                        'queued_at' => now(),
                        'status' => 'queued',
                    ],
                );
            }
        }
    }

    private function transcriptRows(): array
    {
        $pdfRows = $this->verbatimPdfRows();
        if (count($pdfRows) >= 20) {
            return $pdfRows;
        }

        $text = implode("\n\n", array_column($this->fallbackRows(), 'text'));
        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [];
        $rows = [];
        $members = $this->members();

        foreach ($paragraphs as $index => $paragraph) {
            $clean = trim(preg_replace('/\s+/', ' ', $paragraph));
            if (mb_strlen($clean) < 80) {
                continue;
            }
            $member = $members[$index % count($members)];
            $rows[] = [
                'roster_id' => $member[0],
                'speaker_name' => $member[2],
                'party' => $member[4],
                'constituency' => $member[5],
                'timestamp' => null,
                'slot_code' => null,
                'text' => mb_substr($clean, 0, 900),
            ];
            if (count($rows) >= 180) {
                break;
            }
        }

        return count($rows) >= 20 ? $rows : $this->fallbackRows();
    }

    private function verbatimPdfRows(): array
    {
        $path = self::PROJECT_DIR.'/Verbatim_Full_Day_debate_01_12_25_.pdf';
        $text = $this->pdfText($path);

        return $text ? $this->parseDebateRows($text) : [];
    }

    private function pdfText(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        // Use array-form Process to avoid any shell interpretation of $path.
        $process = new Process(['pdftotext', '-layout', $path, '-']);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()) ?: null;
    }

    private function parseDebateRows(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [];
        $rows = [];
        $currentSpeaker = null;
        $currentParty = null;
        $currentConstituency = null;
        $currentTimestamp = null;
        $currentSlot = null;
        $buffer = [];

        foreach ($paragraphs as $paragraph) {
            $clean = trim(preg_replace('/[ \t]+/', ' ', $paragraph));
            if ($clean === '') {
                continue;
            }

            if (preg_match('/\b([0-2]?\d:[0-5]\d)\b/', $clean, $time)) {
                $currentTimestamp = $time[1];
            }
            if (preg_match('/\b([1-9][A-Z])\b/', $clean, $slot)) {
                $currentSlot = $slot[1];
            }

            if (preg_match('/^(?P<speaker>(?:HON\.?|SHRI|SMT\.?|DR\.?|PROF\.?|THE MINISTER|THE VICE-CHAIRMAN|MR\.|MS\.)[^:]{2,100}):\s*(?P<body>.*)$/iu', $clean, $match)) {
                $this->flushParsedRow($rows, $currentSpeaker, $currentParty, $currentConstituency, $currentTimestamp, $currentSlot, $buffer);
                $currentSpeaker = $this->normaliseSpeakerName($match['speaker']);
                [$currentParty, $currentConstituency] = $this->extractPartyConstituency($currentSpeaker);
                $body = trim($match['body']);
                $buffer = $body !== '' ? [$body] : [];
                continue;
            }

            if ($currentSpeaker) {
                $buffer[] = $clean;
            }
        }

        $this->flushParsedRow($rows, $currentSpeaker, $currentParty, $currentConstituency, $currentTimestamp, $currentSlot, $buffer);

        return array_slice($rows, 0, 180);
    }

    private function flushParsedRow(array &$rows, ?string $speaker, ?string $party, ?string $constituency, ?string $timestamp, ?string $slot, array $buffer): void
    {
        $text = trim(preg_replace('/\s+/', ' ', implode(' ', $buffer)));
        if (! $speaker || mb_strlen($text) < 80) {
            return;
        }

        $member = $this->findRosterMember($speaker);
        $rows[] = [
            'roster_id' => $member?->roster_id,
            'speaker_name' => $speaker,
            'party' => $party ?: $member?->party,
            'constituency' => $constituency ?: $member?->state_jur,
            'timestamp' => $timestamp,
            'slot_code' => $slot,
            'text' => mb_substr($text, 0, 1200),
        ];
    }

    private function normaliseSpeakerName(string $speaker): string
    {
        $speaker = preg_replace('/\s*\([^)]*\)\s*/', ' ', $speaker);
        $speaker = preg_replace('/\s+/', ' ', $speaker);

        return trim($speaker, " \t\n\r\0\x0B.:");
    }

    private function extractPartyConstituency(string $speaker): array
    {
        if (preg_match('/\(([^,()]+),\s*([^()]+)\)/', $speaker, $match)) {
            return [trim($match[1]), trim($match[2])];
        }

        return [null, null];
    }

    private function resolveSpeaker(Slot $slot, array $row): array
    {
        $member = isset($row['roster_id']) ? Member::query()->where('roster_id', $row['roster_id'])->first() : null;
        $member ??= $this->findRosterMember($row['speaker_name'] ?? '');
        if ($member) {
            return [$member, null];
        }

        $creatorId = User::query()->where('employee_id', 'RPT-001')->value('id');
        $custom = MemberCustom::query()->firstOrCreate(
            ['slot_id' => $slot->id, 'name_en' => $row['speaker_name'] ?? 'Unlisted speaker'],
            [
                'name_hi' => $row['speaker_name'] ?? 'Unlisted speaker',
                'role_title' => 'Unlisted sitting participant',
                'state_jur' => $row['constituency'] ?? null,
                'created_by_user_id' => $creatorId,
            ],
        );

        return [null, $custom];
    }

    private function findRosterMember(string $speaker): ?Member
    {
        if ($speaker === '') {
            return null;
        }
        $normalised = mb_strtolower(preg_replace('/[^a-z ]/i', '', $speaker));

        return Member::query()->get()->first(function (Member $member) use ($normalised) {
            $candidate = mb_strtolower(preg_replace('/[^a-z ]/i', '', $member->name_en));

            return $candidate !== '' && (str_contains($normalised, $candidate) || str_contains($candidate, $normalised));
        });
    }

    private function seedSynopses(Sitting $sitting): void
    {
        foreach ($this->synopsisFiles() as $definition) {
            [$file, $language, $kind] = $definition;
            $text = $this->pdfText(self::PROJECT_DIR.'/'.$file);
            $rows = $text ? $this->parseSynopsisRows($text) : [];
            if ($rows === []) {
                $rows = $this->fallbackSynopsisRows($file);
            }

            foreach (array_slice($rows, 0, 40) as $index => $row) {
                Synopsis::query()->updateOrCreate(
                    ['sitting_id' => $sitting->id, 'source_file' => $file, 'sequence' => $index + 1],
                    [
                        'language' => $language,
                        'kind' => $kind,
                        'speaker_name' => $row['speaker_name'] ?? null,
                        'party' => $row['party'] ?? null,
                        'constituency' => $row['constituency'] ?? null,
                        'summary_text' => $row['summary_text'],
                    ],
                );
            }
        }
    }

    private function synopsisFiles(): array
    {
        return [
            ['SynopsisEnglish01_12_25.pdf', 'en', 'main'],
            ['SupplementSynopsisEnglish01_12_25.pdf', 'en', 'supplement'],
            ['SynopsisHindi01_12_25.pdf', 'hi', 'main'],
            ['SupplementSynopsisHindi01_12_25.pdf', 'hi', 'supplement'],
        ];
    }

    private function parseSynopsisRows(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [];
        $rows = [];

        foreach ($paragraphs as $paragraph) {
            $clean = trim(preg_replace('/\s+/', ' ', $paragraph));
            if (mb_strlen($clean) < 80) {
                continue;
            }
            $speaker = null;
            if (preg_match('/^(?P<speaker>(?:SHRI|SMT\.?|DR\.?|PROF\.?|THE MINISTER|HON\.?)[^:]{2,100}):\s*(?P<body>.*)$/iu', $clean, $match)) {
                $speaker = $this->normaliseSpeakerName($match['speaker']);
                $clean = trim($match['body']);
            }
            [$party, $constituency] = $speaker ? $this->extractPartyConstituency($speaker) : [null, null];
            $rows[] = [
                'speaker_name' => $speaker,
                'party' => $party,
                'constituency' => $constituency,
                'summary_text' => mb_substr($clean, 0, 1200),
            ];
        }

        return $rows;
    }

    private function fallbackSynopsisRows(string $file): array
    {
        return collect($this->fallbackRows())->take(12)->map(fn ($row) => [
            'speaker_name' => $row['speaker_name'],
            'party' => $row['party'],
            'constituency' => $row['constituency'],
            'summary_text' => 'Synopsis sample from '.$file.': '.mb_substr($row['text'], 0, 500),
        ])->all();
    }

    private function slotCodes(): array
    {
        $codes = [];
        foreach ([1, 2] as $prefix) {
            foreach (range('A', 'Z') as $letter) {
                $codes[] = $prefix.$letter;
            }
        }
        foreach (range('A', 'H') as $letter) {
            $codes[] = '3'.$letter;
        }

        return $codes;
    }

    private function topicFor(int $index): string
    {
        return [
            'Question Hour: public health and rural services',
            'Zero Hour submissions and special mentions',
            'Discussion on supplementary demands for grants',
            'Government assurances and ministerial replies',
            'Legislative business and laying of papers',
        ][$index % 5];
    }

    private function slotStatus(int $index): string
    {
        return match (true) {
            $index < 6 => 'committed_full',
            $index < 12 => 'committed_partial',
            $index < 24 => 'in_progress',
            default => 'open',
        };
    }

    private function assignmentState(int $index): array
    {
        return match (true) {
            $index < 6 => ['committed', 'chief'],
            $index < 12 => ['committed', 'supervisor'],
            $index < 24 => ['in_progress', 'reporter'],
            default => ['open', 'reporter'],
        };
    }

    private function fallbackRows(): array
    {
        return collect($this->members())->map(fn ($member, $index) => [
            'roster_id' => $member[0],
            'speaker_name' => $member[2],
            'party' => $member[4],
            'constituency' => $member[5],
            'timestamp' => null,
            'slot_code' => null,
            'text' => sprintf(
                '%s submitted that the 1 December 2025 sitting record should capture the ministry reply on public service delivery, district-level implementation, budget utilisation and pending assurances. The Chair directed that figures cited in the debate be checked against the synopsis before final publication.',
                $member[2],
            ),
        ])->all();
    }

    private function members(): array
    {
        return [
            ['RS-CHAIR-001', 'chair', 'Hon. Chairman', 'माननीय सभापति', null, null, 'Chair'],
            ['RS-MIN-001', 'minister', 'Shri J. P. Nadda', 'श्री जे. पी. नड्डा', 'BJP', 'Himachal Pradesh', 'Minister'],
            ['RS-MIN-002', 'minister', 'Smt. Nirmala Sitharaman', 'श्रीमती निर्मला सीतारमण', 'BJP', 'Karnataka', 'Minister'],
            ['RS-MIN-003', 'minister', 'Shri Ashwini Vaishnaw', 'श्री अश्विनी वैष्णव', 'BJP', 'Odisha', 'Minister'],
            ['RS-MEM-001', 'member', 'Shri Mallikarjun Kharge', 'श्री मल्लिकार्जुन खड़गे', 'INC', 'Karnataka', 'Leader of Opposition'],
            ['RS-MEM-002', 'member', 'Smt. Jaya Bachchan', 'श्रीमती जया बच्चन', 'SP', 'Uttar Pradesh', 'Member'],
            ['RS-MEM-003', 'member', 'Shri Derek O Brien', 'श्री डेरेक ओ ब्रायन', 'AITC', 'West Bengal', 'Member'],
            ['RS-MEM-004', 'member', 'Dr. John Brittas', 'डॉ. जॉन ब्रिटास', 'CPI(M)', 'Kerala', 'Member'],
            ['RS-MEM-005', 'member', 'Smt. Priyanka Chaturvedi', 'श्रीमती प्रियंका चतुर्वेदी', 'SS(UBT)', 'Maharashtra', 'Member'],
            ['RS-MEM-006', 'member', 'Shri Sanjay Singh', 'श्री संजय सिंह', 'AAP', 'NCT of Delhi', 'Member'],
            ['RS-MEM-007', 'member', 'Dr. Fauzia Khan', 'डॉ. फौजिया खान', 'NCP', 'Maharashtra', 'Member'],
            ['RS-MEM-008', 'member', 'Shri Tiruchi Siva', 'श्री तिरुचि शिवा', 'DMK', 'Tamil Nadu', 'Member'],
        ];
    }
}
