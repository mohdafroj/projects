<?php

namespace App\Modules\Capture\Seeders;

use App\Modules\Core\Models\Block;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\Slot;
use Illuminate\Database\Seeder;

class DemoBlockSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->blocks() as $index => $row) {
            [$slotCode, $start, $end, $original, $chief, $aiAction, $rosterId, $text] = $row;
            $slot = Slot::query()->where('code', $slotCode)->firstOrFail();
            $memberId = $rosterId ? Member::query()->where('roster_id', $rosterId)->value('id') : null;

            Block::query()->updateOrCreate(
                ['slot_id' => $slot->id, 'sequence' => $this->sequenceFor($index, $slotCode)],
                [
                    'start_ms' => (int) round(($start * 1000) - $slot->start_offset_ms),
                    'end_ms' => (int) round(($end * 1000) - $slot->start_offset_ms),
                    'original_lang' => $original,
                    'chief_lang' => $chief,
                    'ai_action' => $aiAction,
                    'ai_text' => $text,
                    'text' => $text,
                    'translated_text' => null,
                    'member_id' => $memberId,
                    'custom_member_id' => null,
                    'version' => 1,
                    'reporter_edit_count' => 0,
                ],
            );
        }
    }

    private function sequenceFor(int $index, string $slotCode): int
    {
        static $seen = [];
        $seen[$slotCode] = ($seen[$slotCode] ?? 0) + 1;

        return $seen[$slotCode];
    }

    private function blocks(): array
    {
        return [
            ['1A', 1.5, 14.0, 'en', 'en', 'native', 'C001', 'The House will now take up Zero Hour mentions. I call upon Shri R. Patil, Honourable Member from Karnataka, to make his submission on the National Highway 75 expansion project.'],
            ['1A', 14.5, 62.0, 'hi', 'hi', 'native', 'R025', 'महोदय, मैं इस माननीय सभा का ध्यान कर्नाटक राज्य के लोगों को प्रभावित करने वाले राष्ट्रीय राजमार्ग 75 विस्तार की विलंबित परियोजना की ओर आकर्षित करता हूँ।'],
            ['1A', 62.5, 88.0, 'en', 'en', 'native', 'R025', 'Sir, the cost overrun has crossed one thousand four hundred crore rupees and the villages of Hassan, Sakleshpur, and Mangaluru remain cut off during monsoon months.'],
            ['1A', 88.5, 130.0, 'ta', 'en', 'translated', 'R026', 'Sir, the bridges across the Cauvery river in Erode district have not been inspected since twenty nineteen and visible cracks have appeared after recent floods.'],
            ['1A', 130.5, 158.0, 'ur', 'hi', 'native', 'R027', 'صدر صاحب، ضلع لکھنؤ کے دیہی علاقوں میں صاف پانی کی فراہمی پچھلے چھ مہینوں سے بری طرح متاثر ہے۔'],
            ['1A', 158.5, 178.0, 'en', 'en', 'native', 'C001', 'Honourable Members, your submissions are taken on record. Slot 1A concludes. We move to the next mention.'],
            ['1B', 300.5, 325.0, 'en', 'en', 'native', 'C001', 'I now call upon Shri V. Joshi from Maharashtra to raise his submission on the implementation of the National Education Policy.'],
            ['1B', 325.5, 402.0, 'hi', 'hi', 'native', 'R029', 'महोदय, राष्ट्रीय शिक्षा नीति 2020 के क्रियान्वयन को पाँच वर्ष पूरे होने जा रहे हैं, परंतु शिक्षक प्रशिक्षण के लिए निधि समय पर नहीं मिल रही है।'],
            ['1B', 402.5, 445.0, 'en', 'en', 'native', 'R029', 'Sir, the Centre had promised additional funding under Samagra Shiksha and I demand that the Honourable Minister explain the delay.'],
            ['1B', 445.5, 498.0, 'ur', 'hi', 'native', 'R030', 'محترم سر، مدارس کی منظوری کا عمل ابھی بھی بہت سست ہے اور وزارت کو فوری طور پر اس عمل کو تیز کرنا چاہیے۔'],
            ['1B', 498.5, 560.0, 'en', 'en', 'native', 'C001', 'The submissions are on record. The Ministry of Education will be informed through the appropriate channel. Slot 1B closes.'],
            ['1C', 600.5, 638.0, 'en', 'en', 'native', 'C001', 'I now call upon Smt. T. Roy, Honourable Member from West Bengal, on coastal erosion in the Sundarbans delta region.'],
            ['1C', 638.5, 720.0, 'bn', 'en', 'translated', 'R028', 'Sir, the islands of the Sundarbans are vanishing at an alarming rate and climate refugees deserve a national rehabilitation policy.'],
            ['1C', 720.5, 795.0, 'bn', 'en', 'translated', 'R028', 'I demand a comprehensive plan within ninety days and realistic compensation packages for displaced coastal families.'],
            ['1C', 795.5, 860.0, 'hi', 'hi', 'native', 'R031', 'महोदय, यह केवल पश्चिम बंगाल का प्रश्न नहीं है; हमें एक राष्ट्रीय तटीय पुनर्वास नीति की आवश्यकता है।'],
            ['1C', 860.5, 895.0, 'en', 'en', 'native', 'C001', 'The matter is on record. The relevant Ministries will be notified. Zero Hour concludes.'],
            ['1D', 900.5, 952.0, 'en', 'en', 'native', 'R032', 'Sir, my Starred Question No. 23 is addressed to the Honourable Minister of Power on the National Smart Grid Mission.'],
            ['1D', 952.5, 1075.0, 'hi', 'hi', 'native', 'M011', 'महोदय, राष्ट्रीय स्मार्ट ग्रिड मिशन वर्ष 2015 में आरंभ हुआ और देश के 67 शहरों में स्मार्ट मीटर लगाए जा चुके हैं।'],
            ['1D', 1075.5, 1130.0, 'en', 'en', 'native', 'R032', 'Supplementary, Sir. What is the Government doing for rural distribution networks where power cuts continue during peak season?'],
            ['1D', 1130.5, 1185.0, 'hi', 'hi', 'native', 'M011', 'महोदय, ग्रामीण विद्युतीकरण के लिए अतिरिक्त निवेश स्वीकृत किया गया है और फीडर पृथक्करण तेजी से चल रहा है।'],
            ['1E', 1200.5, 1265.0, 'mr', 'en', 'translated', 'R033', 'Sir, my question relates to the Mumbai-Aurangabad-Nagpur Industrial Corridor and the reasons for cost escalation.'],
            ['1E', 1265.5, 1370.0, 'hi', 'hi', 'native', 'M008', 'महोदय, लागत बढ़ने के तीन कारण हैं: भूमि अधिग्रहण मुआवजा, वैश्विक कीमतें और परियोजना के दायरे का विस्तार।'],
            ['1E', 1370.5, 1430.0, 'en', 'en', 'native', 'R033', 'Sir, what monitoring mechanism is in place to prevent further cost overruns?'],
            ['1E', 1430.5, 1490.0, 'en', 'en', 'native', 'M008', 'A high-level monitoring committee chaired by the Cabinet Secretary has been constituted and reports are placed before this House.'],
            ['1F', 1500.5, 1560.0, 'en', 'en', 'native', 'R060', 'Sir, my Starred Question relates to PMGSY connectivity in hill districts and the North East.'],
            ['1F', 1560.5, 1645.0, 'hi', 'hi', 'native', 'M015', 'महोदय, प्रधानमंत्री ग्राम सड़क योजना के तीसरे चरण में हिमालयी राज्यों के लिए विशेष आवंटन है।'],
            ['1F', 1645.5, 1710.0, 'ur', 'hi', 'native', 'R061', 'محترم سر، کیا وزارت ان دور دراز علاقوں میں سڑک کے ساتھ ڈیجیٹل کنیکٹیویٹی بھی فراہم کر رہی ہے؟'],
            ['1F', 1710.5, 1780.0, 'en', 'en', 'native', 'M015', 'Yes Sir. Bharat Net runs in parallel and optical fibre is laid alongside road connectivity.'],
            ['1F', 1780.5, 1798.0, 'en', 'en', 'native', 'C001', 'Question Hour concludes. The House will now take up Papers Laid on the Table.'],
            ['2A', 1800.5, 1860.0, 'en', 'en', 'native', 'R049', 'Sir, my Starred Question No. 47 is addressed to the Honourable Minister of Defence on indigenous production targets.'],
            ['2A', 1860.5, 1985.0, 'hi', 'hi', 'native', 'M003', 'महोदय, आत्मनिर्भर भारत के अंतर्गत स्वदेशी रक्षा उत्पादन ने अभूतपूर्व प्रगति की है।'],
            ['2A', 1985.5, 2050.0, 'en', 'en', 'native', 'R049', 'Supplementary, Sir. What is the share of the private sector, particularly MSMEs, in indigenous production?'],
            ['2A', 2050.5, 2098.0, 'hi', 'hi', 'native', 'M003', 'महोदय, निजी क्षेत्र की भागीदारी 21 प्रतिशत तक पहुँच चुकी है और रक्षा निर्यात ने नया कीर्तिमान बनाया है।'],
            ['2B', 2100.5, 2155.0, 'ur', 'hi', 'native', 'R030', 'محترم سر، میرا سوال وزارت ریلوے سے ہے کہ ریلوے سیفٹی آڈٹ کتنے اسٹیشنوں پر مکمل ہو چکا ہے؟'],
            ['2B', 2155.5, 2295.0, 'hi', 'hi', 'native', 'M009', 'महोदय, गत तीन वर्षों में 7,234 स्टेशनों और 92,400 किलोमीटर ट्रैक सेक्शन पर सेफ्टी ऑडिट पूर्ण हो चुका है।'],
            ['2B', 2295.5, 2370.0, 'en', 'en', 'native', 'R030', 'Supplementary. Can the Minister assure the House that the remaining critical issues will be addressed in a time-bound manner?'],
            ['2C', 2400.5, 2440.0, 'en', 'en', 'native', 'C001', 'I now call upon Shri K. Khan to raise a Calling Attention Motion on deteriorating air quality in NCR.'],
            ['2C', 2440.5, 2575.0, 'ur', 'hi', 'native', 'R030', 'صدر صاحب، این سی آر میں ہوا کی کوالٹی ایک قومی ہنگامی صورتحال بن چکی ہے۔'],
            ['2C', 2575.5, 2660.0, 'en', 'en', 'native', 'R055', 'Sir, I endorse this submission completely and ask why enforcement fails every winter.'],
            ['2C', 2660.5, 2698.0, 'en', 'en', 'native', 'C001', 'The matter is on record. The Ministry of Environment will be informed for a structured response.'],
            ['2D', 2700.5, 2820.0, 'en', 'en', 'native', 'R024', 'Sir, under Rule 180A, I rise to make a Special Mention on the plight of marine fishermen.'],
            ['2D', 2820.5, 2945.0, 'hi', 'hi', 'native', 'R029', 'महोदय, महाराष्ट्र के कोंकण क्षेत्र में मछुआरा समुदाय की स्थिति अत्यंत दयनीय है।'],
            ['2E', 3000.5, 3070.0, 'en', 'en', 'native', 'C001', 'The House will now take up Papers Laid on the Table. I call upon the Honourable Minister of Parliamentary Affairs.'],
            ['2E', 3070.5, 3180.0, 'en', 'en', 'native', 'M013', 'Sir, I beg to lay on the Table copies of Annual Reports for the financial year twenty twenty-three to twenty twenty-four.'],
            ['2E', 3180.5, 3260.0, 'hi', 'hi', 'native', 'M014', 'महोदय, मैं कृषि एवं किसान कल्याण मंत्रालय की निष्पादन रिपोर्ट सभा पटल पर रखता हूँ।'],
            ['2F', 3300.5, 3380.0, 'en', 'en', 'native', 'C001', 'Honourable Members, before we adjourn, I thank the House for a productive sitting.'],
            ['2F', 3380.5, 3460.0, 'en', 'en', 'native', 'M013', 'Sir, on behalf of the Government, I assure the House that responses given today will be followed up in writing.'],
            ['2F', 3460.5, 3540.0, 'hi', 'hi', 'native', 'C001', 'सदस्यगणों का धन्यवाद। मैं अब इस सभा को मध्याह्न दो बजे तक के लिए स्थगित करता हूँ।'],
            ['2F', 3540.5, 3595.0, 'en', 'en', 'native', null, '(Gavel strikes. House adjourns until 14:00 IST.)'],
        ];
    }
}
