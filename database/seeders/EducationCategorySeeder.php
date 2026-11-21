<?php

namespace Database\Seeders;

use App\Models\EducationCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EducationCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Konstitutsiyaviy huquq', 'Fuqarolik huquqi', 'Jinoyat huquqi', 'Ma’muriy huquq', 'Mehnat huquqi', 'Oila huquqi',
            'Moliya huquqi', 'Soliq huquqi', 'Tadbirkorlik huquqi', 'Xalqaro huquq', 'Ekologiya huquqi', 'Yer huquqi',
            'Fuqarolik protsessual huquqi', 'Jinoyat-protsessual huquqi'] as $name) {
            $slug = Str::slug($name);
            EducationCategory::query()
                ->where('slug', $slug)
                ->orWhere('name', $name)
                ->firstOrCreate([], ['name' => $name, 'slug' => $slug]);
        }
    }
}
