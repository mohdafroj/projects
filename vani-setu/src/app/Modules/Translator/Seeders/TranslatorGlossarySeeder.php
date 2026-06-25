<?php

namespace App\Modules\Translator\Seeders;

use App\Modules\Core\Models\User;
use App\Modules\Translator\Models\TranslatorGlossary;
use Illuminate\Database\Seeder;

class TranslatorGlossarySeeder extends Seeder
{
    public function run(): void
    {
        $approver = User::query()->where('employee_id', 'TRN-EN-001')->first();
        $terms = [
            ['Treasury Benches', 'सत्ता पक्ष की बेंचें', 'parliamentary'],
            ['Opposition Benches', 'विपक्षी बेंचें', 'parliamentary'],
            ['Honourable Member', 'माननीय सदस्य', 'parliamentary'],
            ['Chairman', 'सभापति', 'parliamentary'],
            ['Deputy Chairman', 'उपसभापति', 'parliamentary'],
            ['Leader of the House', 'सदन के नेता', 'parliamentary'],
            ['Leader of Opposition', 'विपक्ष के नेता', 'parliamentary'],
            ['Point of Order', 'व्यवस्था का प्रश्न', 'parliamentary'],
            ['Question Hour', 'प्रश्न काल', 'parliamentary'],
            ['Zero Hour', 'शून्य काल', 'parliamentary'],
            ['Papers Laid on the Table', 'सभा पटल पर रखे गए पत्र', 'parliamentary'],
            ['Motion of Thanks', 'धन्यवाद प्रस्ताव', 'parliamentary'],
            ['Private Members Bill', 'गैर-सरकारी सदस्य विधेयक', 'legal'],
            ['Private Members Resolution', 'गैर-सरकारी सदस्य संकल्प', 'legal'],
            ['Appropriation Bill', 'विनियोग विधेयक', 'legal'],
            ['Constitution Amendment Bill', 'संविधान संशोधन विधेयक', 'legal'],
            ['Clause', 'खंड', 'legal'],
            ['Schedule', 'अनुसूची', 'legal'],
            ['Statutory Resolution', 'सांविधिक संकल्प', 'legal'],
            ['Contingency Fund', 'भारत की आकस्मिकता निधि', 'economic'],
            ['Consolidated Fund', 'भारत की संचित निधि', 'economic'],
            ['Finance Bill', 'वित्त विधेयक', 'economic'],
            ['Gross Domestic Product', 'सकल घरेलू उत्पाद', 'economic'],
            ['Fiscal Deficit', 'राजकोषीय घाटा', 'economic'],
            ['Revenue Expenditure', 'राजस्व व्यय', 'economic'],
            ['Capital Expenditure', 'पूंजीगत व्यय', 'economic'],
            ['PM-KISAN Scheme', 'पीएम-किसान योजना', 'economic'],
            ['Ministry of Agriculture and Farmers Welfare', 'कृषि एवं किसान कल्याण मंत्रालय', 'parliamentary'],
            ['High Court of West Bengal', 'पश्चिम बंगाल उच्च न्यायालय', 'legal'],
            ['Supreme Court', 'उच्चतम न्यायालय', 'legal'],
            ['Bill as passed by Lok Sabha', 'लोक सभा द्वारा पारित विधेयक', 'legal'],
            ['Select Committee', 'प्रवर समिति', 'parliamentary'],
            ['Standing Committee', 'स्थायी समिति', 'parliamentary'],
            ['Committee Report', 'समिति प्रतिवेदन', 'parliamentary'],
            ['Division', 'मत-विभाजन', 'parliamentary'],
            ['Voice Vote', 'ध्वनिमत', 'parliamentary'],
            ['Adjourned', 'स्थगित', 'parliamentary'],
            ['The House stands adjourned', 'सदन की कार्यवाही स्थगित की जाती है', 'parliamentary'],
            ['Interruption', 'व्यवधान', 'parliamentary'],
            ['Uncorrected', 'असंशोधित', 'parliamentary'],
            ['Floor Version', 'फ्लोर वर्जन', 'parliamentary'],
            ['Synopsis', 'सारांश', 'parliamentary'],
            ['Official Debates', 'आधिकारिक वाद-विवाद', 'parliamentary'],
            ['Bound Volume', 'बद्ध खंड', 'parliamentary'],
            ['Question List', 'प्रश्न सूची', 'parliamentary'],
            ['Starred Question', 'तारांकित प्रश्न', 'parliamentary'],
            ['Unstarred Question', 'अतारांकित प्रश्न', 'parliamentary'],
            ['Table Office', 'टेबल कार्यालय', 'parliamentary'],
            ['Secretariat', 'सचिवालय', 'parliamentary'],
            ['Rajya Sabha', 'राज्य सभा', 'parliamentary'],
        ];

        foreach ($terms as [$source, $target, $domain]) {
            TranslatorGlossary::query()->updateOrCreate(
                ['term_source' => $source, 'language_pair' => 'en_to_hi'],
                [
                    'term_target' => $target,
                    'domain' => $domain,
                    'notes' => 'Seeded deterministic parliamentary glossary.',
                    'created_by' => $approver?->id,
                    'approved_by' => $approver?->id,
                    'approved_at' => now(),
                ],
            );
        }
    }
}
