<?php

namespace Database\Factories;

use App\Models\LegalDocumentDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalDocumentDraft>
 */
class LegalDocumentDraftFactory extends Factory
{
    protected $model = LegalDocumentDraft::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'document_type' => 'claim',
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(3, true),
            'version' => 1,
            'analysis' => [
                'score' => 75,
                'breakdown' => [
                    'structure' => 20,
                    'legal_basis' => 25,
                    'style' => 15,
                    'completeness' => 15,
                ],
                'recommendations' => [
                    'Davlat boji to‘langanligi to‘g‘risidagi kvitansiyani ilovalar qismiga qo‘shing.',
                ],
                'strengths' => [
                    'Taraflarning to‘liq rekvizitlari ko‘rsatilgan.',
                ],
                'missing_elements' => [],
                'detected_articles' => ['FK 236'],
            ],
            'status' => 'analyzed',
        ];
    }
}
