<?php

namespace App\Modules\Capture\Seeders;

use App\Modules\Core\Models\Member;
use Illuminate\Database\Seeder;

class MemberRosterSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            Member::query()->updateOrCreate(
                ['roster_id' => $row['roster_id']],
                $row,
            );
        }
    }

    private function rows(): array
    {
        return [
            ['roster_id' => 'C001', 'category' => 'chair', 'name_en' => 'THE CHAIRMAN', 'name_hi' => 'सभापति महोदय', 'party' => null, 'state_jur' => null, 'role_title' => 'Chairman, Rajya Sabha'],
            ['roster_id' => 'C002', 'category' => 'chair', 'name_en' => 'THE DEPUTY CHAIRMAN', 'name_hi' => 'उप-सभापति महोदय', 'party' => null, 'state_jur' => null, 'role_title' => 'Deputy Chairman'],
            ['roster_id' => 'C003', 'category' => 'chair', 'name_en' => 'THE VICE-CHAIRMAN', 'name_hi' => 'उप-सभापति', 'party' => null, 'state_jur' => null, 'role_title' => 'Vice-Chairman (Panel)'],
            ['roster_id' => 'C004', 'category' => 'chair', 'name_en' => 'SHRI HARIVANSH', 'name_hi' => 'श्री हरिवंश', 'party' => 'JD(U)', 'state_jur' => 'Bihar', 'role_title' => 'Deputy Chairman, Rajya Sabha'],
            ['roster_id' => 'M001', 'category' => 'minister', 'name_en' => 'THE PRIME MINISTER (SHRI NARENDRA MODI)', 'name_hi' => 'प्रधान मंत्री (श्री नरेन्द्र मोदी)', 'party' => 'BJP', 'state_jur' => 'Gujarat', 'role_title' => 'Prime Minister'],
            ['roster_id' => 'M002', 'category' => 'minister', 'name_en' => 'THE MINISTER OF HOME AFFAIRS (SHRI AMIT SHAH)', 'name_hi' => 'गृह मंत्री (श्री अमित शाह)', 'party' => 'BJP', 'state_jur' => 'Gujarat', 'role_title' => 'Home Affairs'],
            ['roster_id' => 'M003', 'category' => 'minister', 'name_en' => 'THE MINISTER OF DEFENCE (SHRI RAJNATH SINGH)', 'name_hi' => 'रक्षा मंत्री (श्री राजनाथ सिंह)', 'party' => 'BJP', 'state_jur' => 'Uttar Pradesh', 'role_title' => 'Defence'],
            ['roster_id' => 'M004', 'category' => 'minister', 'name_en' => 'THE MINISTER OF FINANCE (SMT. NIRMALA SITHARAMAN)', 'name_hi' => 'वित्त मंत्री (श्रीमती निर्मला सीतारमण)', 'party' => 'BJP', 'state_jur' => 'Karnataka', 'role_title' => 'Finance'],
            ['roster_id' => 'M005', 'category' => 'minister', 'name_en' => 'THE MINISTER OF EXTERNAL AFFAIRS (DR. S. JAISHANKAR)', 'name_hi' => 'विदेश मंत्री (डॉ. एस. जयशंकर)', 'party' => 'BJP', 'state_jur' => 'Gujarat', 'role_title' => 'External Affairs'],
            ['roster_id' => 'M006', 'category' => 'minister', 'name_en' => 'THE MINISTER OF EDUCATION (SHRI DHARMENDRA PRADHAN)', 'name_hi' => 'शिक्षा मंत्री (श्री धर्मेन्द्र प्रधान)', 'party' => 'BJP', 'state_jur' => 'Odisha', 'role_title' => 'Education'],
            ['roster_id' => 'M007', 'category' => 'minister', 'name_en' => 'THE MINISTER OF AGRICULTURE (SHRI SHIVRAJ SINGH CHOUHAN)', 'name_hi' => 'कृषि मंत्री (श्री शिवराज सिंह चौहान)', 'party' => 'BJP', 'state_jur' => 'Madhya Pradesh', 'role_title' => 'Agriculture & Farmers Welfare'],
            ['roster_id' => 'M008', 'category' => 'minister', 'name_en' => 'THE MINISTER OF COMMERCE AND INDUSTRY (SHRI PIYUSH GOYAL)', 'name_hi' => 'वाणिज्य एवं उद्योग मंत्री (श्री पीयूष गोयल)', 'party' => 'BJP', 'state_jur' => 'Maharashtra', 'role_title' => 'Commerce & Industry'],
            ['roster_id' => 'M009', 'category' => 'minister', 'name_en' => 'THE MINISTER OF RAILWAYS (SHRI ASHWINI VAISHNAW)', 'name_hi' => 'रेल मंत्री (श्री अश्विनी वैष्णव)', 'party' => 'BJP', 'state_jur' => 'Odisha', 'role_title' => 'Railways, Electronics & IT'],
            ['roster_id' => 'M010', 'category' => 'minister', 'name_en' => 'THE MINISTER OF HEALTH AND FAMILY WELFARE (SHRI J.P. NADDA)', 'name_hi' => 'स्वास्थ्य एवं परिवार कल्याण मंत्री (श्री जे.पी. नड्डा)', 'party' => 'BJP', 'state_jur' => 'Himachal Pradesh', 'role_title' => 'Health & Family Welfare'],
            ['roster_id' => 'M011', 'category' => 'minister', 'name_en' => 'THE MINISTER OF POWER (SHRI MANOHAR LAL KHATTAR)', 'name_hi' => 'विद्युत मंत्री (श्री मनोहर लाल खट्टर)', 'party' => 'BJP', 'state_jur' => 'Haryana', 'role_title' => 'Power, Housing & Urban Affairs'],
            ['roster_id' => 'M012', 'category' => 'minister', 'name_en' => 'THE MINISTER OF ROAD TRANSPORT AND HIGHWAYS (SHRI NITIN GADKARI)', 'name_hi' => 'सड़क परिवहन एवं राजमार्ग मंत्री (श्री नितिन गडकरी)', 'party' => 'BJP', 'state_jur' => 'Maharashtra', 'role_title' => 'Road Transport & Highways'],
            ['roster_id' => 'M013', 'category' => 'minister', 'name_en' => 'THE MINISTER OF PARLIAMENTARY AFFAIRS (SHRI KIREN RIJIJU)', 'name_hi' => 'संसदीय कार्य मंत्री (श्री किरण रिजिजू)', 'party' => 'BJP', 'state_jur' => 'Arunachal Pradesh', 'role_title' => 'Parliamentary Affairs'],
            ['roster_id' => 'M014', 'category' => 'minister', 'name_en' => 'THE MINISTER OF STATE FOR AGRICULTURE (SHRI BHAGIRATH CHOUDHARY)', 'name_hi' => 'कृषि राज्य मंत्री (श्री भागीरथ चौधरी)', 'party' => 'BJP', 'state_jur' => 'Rajasthan', 'role_title' => 'MoS Agriculture'],
            ['roster_id' => 'M015', 'category' => 'minister', 'name_en' => 'THE MINISTER OF RURAL DEVELOPMENT (SHRI B. RAWAT)', 'name_hi' => 'ग्रामीण विकास मंत्री (श्री बी. रावत)', 'party' => 'BJP', 'state_jur' => 'Uttarakhand', 'role_title' => 'Rural Development'],
            ['roster_id' => 'L001', 'category' => 'minister', 'name_en' => 'THE LEADER OF OPPOSITION (SHRI MALLIKARJUN KHARGE)', 'name_hi' => 'विपक्ष के नेता (श्री मल्लिकार्जुन खरगे)', 'party' => 'INC', 'state_jur' => 'Karnataka', 'role_title' => 'Leader of Opposition'],
            ['roster_id' => 'L002', 'category' => 'minister', 'name_en' => 'THE LEADER OF THE HOUSE (SHRI J.P. NADDA)', 'name_hi' => 'सदन के नेता (श्री जे.पी. नड्डा)', 'party' => 'BJP', 'state_jur' => 'Himachal Pradesh', 'role_title' => 'Leader of the House'],
            ...$this->memberRows(),
        ];
    }

    private function memberRows(): array
    {
        $rows = [
            ['R024', 'SHRI TIRUCHI SIVA', 'श्री तिरुचि शिवा', 'DMK', 'Tamil Nadu'],
            ['R025', 'SHRI R. PATIL', 'श्री आर. पाटिल', 'BJP', 'Karnataka'],
            ['R026', 'SMT. P. DEVI', 'श्रीमती पी. देवी', 'DMK', 'Tamil Nadu'],
            ['R027', 'SHRI A. KHAN', 'श्री ए. खान', 'SP', 'Uttar Pradesh'],
            ['R028', 'SMT. T. ROY', 'श्रीमती टी. रॉय', 'AITC', 'West Bengal'],
            ['R029', 'SHRI V. JOSHI', 'श्री वी. जोशी', 'BJP', 'Maharashtra'],
            ['R030', 'SHRI Z. RIZVI', 'श्री ज़ेड. रिज़वी', 'SP', 'Uttar Pradesh'],
            ['R031', 'SHRI A. BANERJEE', 'श्री ए. बनर्जी', 'AITC', 'West Bengal'],
            ['R032', 'SHRI K. AGARWAL', 'श्री के. अग्रवाल', 'BJP', 'Rajasthan'],
            ['R033', 'SHRI P. CHAVAN', 'श्री पी. चव्हाण', 'INC', 'Maharashtra'],
            ['R034', 'SHRI RAGHAV CHADHA', 'श्री राघव चड्ढा', 'AAP', 'Punjab'],
            ['R035', 'DR. SASMIT PATRA', 'डॉ. शसमित पात्रा', 'BJD', 'Odisha'],
            ['R036', "SHRI DEREK O'BRIEN", "श्री डेरेक ओ'ब्रायन", 'AITC', 'West Bengal'],
            ['R037', 'SHRI JAIRAM RAMESH', 'श्री जयराम रमेश', 'INC', 'Karnataka'],
            ['R038', 'SHRI P. CHIDAMBARAM', 'श्री पी. चिदम्बरम', 'INC', 'Tamil Nadu'],
            ['R039', 'SHRI KAPIL SIBAL', 'श्री कपिल सिब्बल', 'IND', 'Uttar Pradesh'],
            ['R040', 'SHRI DIGVIJAYA SINGH', 'श्री दिग्विजय सिंह', 'INC', 'Madhya Pradesh'],
            ['R041', 'SHRI SANJAY RAUT', 'श्री संजय राउत', 'SS(UBT)', 'Maharashtra'],
            ['R042', 'SHRI MANOJ K. JHA', 'श्री मनोज के. झा', 'RJD', 'Bihar'],
            ['R043', 'SHRI PREM CHAND GUPTA', 'श्री प्रेम चन्द गुप्ता', 'RJD', 'Bihar'],
            ['R044', 'SHRI RAM CHANDER JANGRA', 'श्री राम चन्दर जांगड़ा', 'BJP', 'Haryana'],
            ['R045', 'SHRI SAKET GOKHALE', 'श्री साकेत गोखले', 'AITC', 'West Bengal'],
            ['R046', 'SMT. SUDHA MURTY', 'श्रीमती सुधा मूर्ति', 'NOM', 'Nominated'],
            ['R047', 'SHRI SUSHIL KUMAR GUPTA', 'श्री सुशील कुमार गुप्ता', 'AAP', 'Delhi'],
            ['R048', 'DR. JOHN BRITTAS', 'डॉ. जॉन ब्रिटास', 'CPI(M)', 'Kerala'],
            ['R049', 'SHRI NARESH BANSAL', 'श्री नरेश बंसल', 'BJP', 'Uttarakhand'],
            ['R050', 'SHRI VAIKO', 'श्री वाइको', 'MDMK', 'Tamil Nadu'],
            ['R051', 'SHRI BINOY VISWAM', 'श्री बिनॉय विश्वम', 'CPI', 'Kerala'],
            ['R052', 'SHRI K.T.S. TULSI', 'श्री के.टी.एस. तुलसी', 'IND', 'Punjab'],
            ['R053', 'SHRI VIVEK K. TANKHA', 'श्री विवेक के. तंखा', 'INC', 'Madhya Pradesh'],
            ['R054', 'SHRI ABHISHEK MANU SINGHVI', 'श्री अभिषेक मनु सिंघवी', 'INC', 'Telangana'],
            ['R055', 'SMT. RAMILABEN BARA', 'श्रीमती रमिलाबेन बारा', 'BJP', 'Gujarat'],
            ['R056', 'SHRI K. KESHAVA RAO', 'श्री के. केशव राव', 'BRS', 'Telangana'],
            ['R057', 'DR. SANJAY JAISWAL', 'डॉ. संजय जायसवाल', 'BJP', 'Bihar'],
            ['R058', 'SHRI RAM NATH THAKUR', 'श्री राम नाथ ठाकुर', 'JD(U)', 'Bihar'],
            ['R059', 'SHRI M. SHANMUGAM', 'श्री एम. षण्मुगम', 'DMK', 'Tamil Nadu'],
            ['R060', 'SHRI A. KAUR', 'श्रीमती ए. कौर', 'INC', 'Punjab'],
            ['R061', 'SHRI H. RIZVI', 'श्री एच. रिज़वी', 'SP', 'Uttar Pradesh'],
            ['R062', 'SHRI SANDOSH KUMAR P', 'श्री संदोष कुमार पी', 'CPI', 'Kerala'],
        ];

        return array_map(fn ($row) => [
            'roster_id' => $row[0],
            'category' => 'member',
            'name_en' => $row[1],
            'name_hi' => $row[2],
            'party' => $row[3],
            'state_jur' => $row[4],
            'role_title' => null,
            'is_active' => true,
        ], $rows);
    }
}
