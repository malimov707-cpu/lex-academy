<?php

use App\Models\Course;
use App\Models\LegalSource;
use App\Models\LibraryResource;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('shows the claim checker only to authenticated users', function (): void {
    $this->get(route('legal-information-check.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->student()->create())
        ->get(route('legal-information-check.index'))
        ->assertOk()
        ->assertSee('Shubhali huquqiy ma’lumotni tekshirish')
        ->assertSee('Manbalar bilan solishtirish')
        ->assertSee('Matn saqlanmaydi');
});

it('returns a claim analysis with validated quotes from matching approved global sources', function (): void {
    $student = User::factory()->student()->create();
    $reviewer = User::factory()->academyAdmin()->create();
    $quote = 'Sinov parchasi: haftalik ish vaqti 40 soatdan oshmaydi.';
    $source = LegalSource::factory()->create([
        'title' => 'Mehnat mavzusidagi rasmiy manba',
        'excerpt' => 'Mavzu bo‘yicha tasdiqlangan o‘quv parchasi. '.$quote,
        'status' => 'verified',
        'verified_by' => $reviewer->id,
        'verified_at' => now(),
    ]);
    $claim = 'Mehnat mavzusidagi post haftalik ish vaqti 60 soat ekanini da’vo qilmoqda.';
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'claims' => [[
            'claim' => $claim,
            'status' => 'contradicted_by_verified_sources',
            'explanation' => 'Berilgan parcha da’voga zid raqam keltiradi; bu dastlabki tahlil.',
            'evidence' => [['source_ref' => 'SOURCE-'.$source->id, 'quote' => $quote]],
        ]],
    ], JSON_UNESCAPED_UNICODE)]]]], 200)]);

    $this->actingAs($student)->postJson(route('legal-information-check.store'), ['text' => $claim])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('overall_status', 'contradicted_by_verified_sources')
        ->assertJsonPath('claims.0.claim', $claim)
        ->assertJsonPath('claims.0.evidence.0.reference', 'SOURCE-'.$source->id)
        ->assertJsonPath('claims.0.evidence.0.quote', $quote)
        ->assertJsonPath('claims.0.evidence.0.url', $source->url);

    Http::assertSent(fn ($request): bool => str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), $source->excerpt));
});

it('does not call AI or label a claim false when no matching global verified source is available', function (): void {
    $student = User::factory()->student()->create();
    $admin = User::factory()->academyAdmin()->create();
    LegalSource::factory()->create(['status' => 'draft', 'title' => 'Tasdiqlanmagan manba']);
    LegalSource::factory()->create([
        'course_id' => Course::factory()->create()->id,
        'title' => 'Boshqa kursning manbasi',
        'status' => 'verified',
        'verified_by' => $admin->id,
        'verified_at' => now(),
    ]);
    LibraryResource::factory()->create([
        'title' => 'Raqamli huquqlar kodeksi',
        'type' => 'code',
        'body' => 'Raqamli huquqlar bo‘yicha tasdiqlanmagan mustaqil matn.',
        'url' => 'https://lex.uz/docs/-987654',
        'status' => 'published',
    ]);
    $claim = 'Raqamli huquqlar bo‘yicha ushbu da’voni tekshirish kerak.';
    $this->actingAs($student)->postJson(route('legal-information-check.store'), ['text' => $claim])
        ->assertOk()
        ->assertJsonPath('overall_status', 'cannot_be_verified')
        ->assertJsonPath('claims.0.status', 'cannot_be_verified')
        ->assertJsonPath('claims.0.evidence', [])
        ->assertJsonPath('claims.0.explanation', 'Berilgan matnga mos, tekshirishga yaroqli tasdiqlangan manba parchasi topilmadi. Bu da’vo yolg‘on degani emas.');

    Http::assertNothingSent();
});

it('uses a published library text only when its URL is linked to a verified global legal source', function (): void {
    $student = User::factory()->student()->create();
    $reviewer = User::factory()->academyAdmin()->create();
    $url = 'https://lex.uz/docs/-123456';
    $quote = 'Mehnat ta’tilining davomiyligi kalendar kunlarda belgilanadi.';
    $source = LegalSource::factory()->create([
        'title' => 'Mehnat kodeksiga havola',
        'url' => $url,
        'excerpt' => 'Inson tomonidan tasdiqlangan manba yozuvi.',
        'status' => 'verified',
        'verified_by' => $reviewer->id,
        'verified_at' => now(),
    ]);
    LibraryResource::factory()->create([
        'title' => 'Mehnat kodeksi',
        'type' => 'code',
        'topic' => 'Mehnat huquqi',
        'url' => $url,
        'body' => 'Kodeks matni. '.$quote.' Boshqa qoidalar davom etadi.',
        'status' => 'published',
    ]);
    $claim = 'Mehnat kodeksida ta’til davomiyligi kalendar kunlarda belgilanadi.';
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'claims' => [[
            'claim' => $claim,
            'status' => 'supported_by_verified_sources',
            'explanation' => 'Berilgan iqtibos da’voni qo‘llab-quvvatlaydi.',
            'evidence' => [['source_ref' => 'SOURCE-'.$source->id, 'quote' => $quote]],
        ]],
    ], JSON_UNESCAPED_UNICODE)]]]], 200)]);

    $this->actingAs($student)->postJson(route('legal-information-check.store'), ['text' => $claim])
        ->assertOk()
        ->assertJsonPath('claims.0.status', 'supported_by_verified_sources')
        ->assertJsonPath('claims.0.evidence.0.quote', $quote);
});

it('downgrades unsupported status when the cited quote is not present in the source excerpt', function (): void {
    $student = User::factory()->student()->create();
    $reviewer = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create([
        'title' => 'Mehnat bo‘yicha manba',
        'excerpt' => 'Bu parchada keltirilgan test matni.',
        'status' => 'verified',
        'verified_by' => $reviewer->id,
        'verified_at' => now(),
    ]);
    $claim = 'Mehnat bo‘yicha post qonuniy talabni aniq belgilaydi.';
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'claims' => [[
            'claim' => $claim,
            'status' => 'contradicted_by_verified_sources',
            'explanation' => 'AI tomonidan berilgan asoslanmagan xulosa.',
            'evidence' => [['source_ref' => 'SOURCE-'.$source->id, 'quote' => 'Uydirma iqtibos manbada yo‘q.']],
        ]],
    ], JSON_UNESCAPED_UNICODE)]]]], 200)]);

    $this->actingAs($student)->postJson(route('legal-information-check.store'), ['text' => $claim])
        ->assertOk()
        ->assertJsonPath('claims.0.status', 'cannot_be_verified')
        ->assertJsonPath('claims.0.explanation', 'Manbadan ushbu xulosani bevosita tasdiqlaydigan iqtibos topilmadi. Bu da’vo yolg‘on degani emas.')
        ->assertJsonPath('claims.0.evidence', []);
});

it('rejects provider citations that refer to a source outside the supplied evidence set', function (): void {
    $student = User::factory()->student()->create();
    $reviewer = User::factory()->academyAdmin()->create();
    LegalSource::factory()->create([
        'title' => 'Mehnat mavzusidagi manba',
        'excerpt' => 'Mehnat mavzusiga mos tasdiqlangan parcha.',
        'status' => 'verified',
        'verified_by' => $reviewer->id,
        'verified_at' => now(),
    ]);
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'claims' => [[
            'claim' => 'Mehnat mavzusidagi da’voni tekshirish kerak.',
            'status' => 'supported_by_verified_sources',
            'explanation' => 'Izoh.',
            'evidence' => [['source_ref' => 'SOURCE-999', 'quote' => 'Tashqi havola uchun parcha.']],
        ]],
    ], JSON_UNESCAPED_UNICODE)]]]], 200)]);

    $this->actingAs($student)->postJson(route('legal-information-check.store'), ['text' => 'Mehnat mavzusidagi da’voni tekshirish kerak.'])
        ->assertStatus(503)
        ->assertJsonPath('error', 'Tahlil javobi manba va format tekshiruvidan o‘tmadi. Keyinroq qayta urinib ko‘ring.');
});

it('validates input length and throttles repeated checks', function (): void {
    $student = User::factory()->student()->create();
    $this->actingAs($student);
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson(route('legal-information-check.store'), ['text' => 'Juda qisqa'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('text');
    }

    $this->postJson(route('legal-information-check.store'), ['text' => 'Juda qisqa'])
        ->assertTooManyRequests();
});
