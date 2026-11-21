<div class="flex flex-col gap-4 rounded-2xl bg-white p-6">
    @foreach (['situation' => 'Vaziyat', 'facts' => 'Faktlar', 'legal_issue' => 'Huquqiy muammo', 'legislation' => 'Qonunchilik va manbalar', 'analysis_questions' => 'Tahlil savollari'] as $field => $label)
        <section><h2 class="font-semibold">{{ __($label) }}</h2><p class="mt-1 whitespace-pre-line">{{ $legalCase->$field }}</p></section>
    @endforeach
</div>
