<?php

namespace App\Services\Ai;

class DocumentAnalyzer
{
    /**
     * @return array{
     *     overall_score: int,
     *     structure_score: int,
     *     legal_score: int,
     *     style_score: int,
     *     sections_found: list<string>,
     *     missing_sections: list<string>,
     *     citations_found: list<string>,
     *     terminology_warnings: list<string>,
     *     strengths: list<string>,
     *     recommendations: list<string>,
     *     summary: string
     * }
     */
    public function analyze(string $type, string $text): array
    {
        $normalized = mb_strtolower($text);
        $length = mb_strlen(trim($text));

        $sectionsFound = [];
        $missingSections = [];

        // 1. Structure Verification
        $expectedSections = match ($type) {
            'claim' => [
                'kirish' => ['sudga', 'da’vogar', 'javobgar', 'manzili', 'sudining'],
                'bayon' => ['holat', 'vaziyat', 'tuzilgan', 'shartnoma', 'kelishilgan', 'voqea'],
                'asoslar' => ['modda', 'kodeks', 'qonun', 'talabiga ko‘ra', 'muvofiq'],
                'talab' => ['so‘rayman', 'undirish', 'majburlash', 'bekor qilish', 'talab qilaman'],
                'ilova' => ['ilova', 'hujjatlar', 'nusxasi', 'chek', 'to‘lov'],
            ],
            'complaint' => [
                'kirish' => ['sud hay’atiga', 'sudiga', 'shikoyat beruvchi', 'sudining hal qiluv qarori'],
                'bayon' => ['qaror qabul qilingan', 'sud tomonidan', 'ish holatlari'],
                'asoslar' => ['modda', 'kodeks', 'qonun talabi buzilgan', 'asossiz'],
                'talab' => ['so‘rayman', 'o‘zgartirish', 'bekor qilish', 'yangi qaror'],
                'ilova' => ['ilova', 'qaror nusxasi', 'davlat boji'],
            ],
            'contract' => [
                'kirish' => ['shartnoma', 'taraflar', 'bir tomondan', 'ikkinchi tomondan'],
                'predmet' => ['predmeti', 'majburiyat', 'yetkazib berish', 'xizmat ko‘rsatish'],
                'bahosi' => ['narxi', 'to‘lov', 'summa', 'so‘m'],
                'javobgarlik' => ['javobgarlik', 'penya', 'jarima', 'fors-major'],
                'imzolar' => ['rekvizitlar', 'manzil', 'imzo', 'bank'],
            ],
            default => [
                'kirish' => ['ariza', 'rahbariga', 'boshlig‘iga', 'kimga'],
                'bayon' => ['shuni ma’lum qilamanki', 'sababli', 'vaziyat'],
                'talab' => ['so‘rayman', 'amaliy yordam', 'ruxsat berishingizni'],
                'sana_imzo' => ['imzo', 'sana', 'yil'],
            ],
        };

        foreach ($expectedSections as $secKey => $keywords) {
            $found = false;
            foreach ($keywords as $kw) {
                if (str_contains($normalized, $kw)) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $sectionsFound[] = $secKey;
            } else {
                $missingSections[] = $secKey;
            }
        }

        $totalSections = count($expectedSections);
        $structureScore = $totalSections > 0 ? (int) round((count($sectionsFound) / $totalSections) * 100) : 50;

        // 2. Legal Citations Search
        preg_match_all('/(?:(?:modda|moddasi|moddalariga|kodeks|kodeksining)\s*[\d\-]+|[\d\-]+\s*(?:-modda|-moddasi))/iu', $text, $matches);
        $citationsFound = array_unique($matches[0] ?? []);
        $legalScore = count($citationsFound) >= 2 ? 95 : (count($citationsFound) === 1 ? 75 : 40);

        // 3. Style & Terminology
        $terminologyWarnings = [];
        $informalWords = ['iltimos qilaman juda', 'puli yo‘q', 'aldab ketdi', 'yomon odam', 'axmoq'];
        foreach ($informalWords as $badWord) {
            if (str_contains($normalized, $badWord)) {
                $terminologyWarnings[] = "Og‘zaki yoki emotsional ibora ('{$badWord}') o‘rniga qonuniy atamalardan foydalaning.";
            }
        }

        if ($length < 150) {
            $terminologyWarnings[] = 'Hujjat hajmi juda qisqa. Huquqiy faktlar va vajlar to‘liq bayon etilishi lozim.';
        }

        $styleScore = max(30, 90 - (count($terminologyWarnings) * 20));

        // 4. Overall Score Calculation
        $overallScore = (int) round(($structureScore * 0.4) + ($legalScore * 0.4) + ($styleScore * 0.2));

        // 5. Strengths & Recommendations
        $strengths = [];
        $recommendations = [];

        if (count($sectionsFound) >= 3) {
            $strengths[] = 'Matnda tuzilma checklistining bir nechta belgisi topildi; bu rasmiy talablar bajarilganini anglatmaydi.';
        }
        if (count($citationsFound) > 0) {
            $strengths[] = 'Modda raqamiga o‘xshash havola topildi: '.implode(', ', array_slice($citationsFound, 0, 3)).'. Hujjat, modda va amaldagi tahrir tekshirilmagan.';
        }

        if (in_array('ilova', $missingSections, true)) {
            $recommendations[] = 'Agar hujjat turiga mos bo‘lsa, ilovalar ro‘yxatini kiriting va amaldagi rasmiy talablar bilan solishtiring.';
        }
        if (in_array('asoslar', $missingSections, true) || count($citationsFound) === 0) {
            $recommendations[] = 'Tegishli huquqiy manbani LexUZ kabi rasmiy manbadan toping va hujjatga mosligini mustaqil tekshiring.';
        }
        if (in_array('talab', $missingSections, true)) {
            $recommendations[] = 'Hujjatdan ko‘zlangan natijani aniq ifodalang; qabul qiluvchi va hujjat turiga tegishli tartibni rasmiy manbadan tekshiring.';
        }

        if ($recommendations === []) {
            $recommendations[] = 'Ushbu matn checklistida kamchilik aniqlanmadi. Qonuniy asoslar, muddatlar va protsessual talablar bu vositada tekshirilmaydi.';
        }

        $summary = $overallScore >= 80
            ? 'Matn checklistiga moslik ko‘rsatkichi yuqori. Bu yuridik hujjatning to‘g‘riligi yoki topshirishga tayyorligini tasdiqlamaydi.'
            : ($overallScore >= 60
                ? 'Checklist bo‘yicha ayrim matn belgilari topildi; rasmiy talablarni manbadan alohida tekshiring.'
                : 'Checklist bo‘yicha ko‘proq ma’lumot yetishmayapti. Hujjat turiga mos talablarni rasmiy manbadan aniqlang.');

        return [
            'score' => $overallScore,
            'overall_score' => $overallScore,
            'structure_score' => $structureScore,
            'legal_score' => $legalScore,
            'legal_review_status' => 'not_verified',
            'analysis_method' => 'text_pattern_checklist',
            'style_score' => $styleScore,
            'breakdown' => [
                'structure' => (int) round(($structureScore / 100) * 25),
                'legal_basis' => (int) round(($legalScore / 100) * 35),
                'style' => (int) round(($styleScore / 100) * 20),
                'completeness' => (int) round(($structureScore / 100) * 20),
            ],
            'sections_found' => $sectionsFound,
            'missing_sections' => $missingSections,
            'missing_elements' => $missingSections,
            'citations_found' => array_values($citationsFound),
            'detected_articles' => array_values($citationsFound),
            'terminology_warnings' => $terminologyWarnings,
            'strengths' => $strengths,
            'recommendations' => $recommendations,
            'summary' => $summary,
            'disclaimer' => 'Ushbu avtomatik checklist hujjatning qonuniy kuchi, modda havolalarining to‘g‘riligi yoki amaldagi talablarni tekshirmaydi.',
        ];
    }
}
