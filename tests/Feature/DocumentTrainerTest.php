<?php

use App\Enums\UserRole;
use App\Models\LegalDocumentDraft;
use App\Models\User;
use App\Services\Ai\DocumentAnalyzer;

test('students can view document trainer index', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $response = $this->actingAs($student)->get(route('student.document-trainer.index'));

    $response->assertOk();
    $response->assertSee('Yuridik hujjatlar trenajyori');
    $response->assertSee('Da’vo arizasi');
});

test('students can open create template for a document type', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $response = $this->actingAs($student)->get(route('student.document-trainer.create', ['type' => 'claim']));

    $response->assertOk();
    $response->assertSee('Da’vo arizasi');
    $response->assertSee('DA’VO ARIZASI');
    $response->assertSee('o‘quv mashqi');
    $response->assertDontSee('FPK 189');
});

test('demand template does not invent a universal response deadline', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($student)->get(route('student.document-trainer.create', ['type' => 'demand']))
        ->assertOk()
        ->assertSee('Muddat shartnoma yoki amaldagi rasmiy qoidalardan tekshiriladi.')
        ->assertDontSee('10 kun ichida');
});

test('document checklist explicitly marks legal references as unverified', function (): void {
    $analysis = app(DocumentAnalyzer::class)->analyze('claim', 'FK 999-modda bo‘yicha so‘rayman.');

    expect($analysis['legal_review_status'])->toBe('not_verified')
        ->and($analysis['analysis_method'])->toBe('text_pattern_checklist')
        ->and($analysis['disclaimer'])->toContain('tekshirmaydi')
        ->and($analysis['summary'])->not->toContain('professional darajada');
});

test('students can store a document and get instant ai analysis', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);

    $response = $this->actingAs($student)->post(route('student.document-trainer.store'), [
        'document_type' => 'claim',
        'title' => 'Qarz undirish da’vosi',
        'content' => "Fuqarolik ishlari bo‘yicha sudga\nDa’vogar: Aliyev A.\nJavobgar: Karimov B.\nDA’VO ARIZASI\nO‘zR FK 236 va 324-moddalariga asosan qarzni undirishingizni so‘rayman. Sana: 20.11.2026. Imzo: Aliyev",
    ]);

    $draft = LegalDocumentDraft::query()->first();
    expect($draft)->not->toBeNull()
        ->and($draft->user_id)->toBe($student->id)
        ->and($draft->analysis)->toBeArray()
        ->and($draft->analysis['score'])->toBeGreaterThan(0);

    $response->assertRedirect(route('student.document-trainer.show', $draft));
});

test('students can re-analyze updated draft to increment version', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);
    $draft = LegalDocumentDraft::factory()->create([
        'user_id' => $student->id,
        'document_type' => 'contract',
        'version' => 1,
    ]);

    $response = $this->actingAs($student)->post(route('student.document-trainer.analyze', $draft), [
        'title' => 'Yangilangan shartnoma loyihasi',
        'content' => 'Xizmat ko‘rsatish shartnomasi № 1. Buyurtmachi va Ijrochi FK 353-moddasiga binoan shartnoma tuzdilar. Narxi: 1000000 so‘m. Nizolarni hal qilish va rekvizitlar ko‘rsatilgan.',
    ]);

    $draft->refresh();
    expect($draft->version)->toBe(2)
        ->and($draft->title)->toBe('Yangilangan shartnoma loyihasi')
        ->and($draft->analysis['score'])->toBeGreaterThan(0);

    $response->assertRedirect(route('student.document-trainer.show', $draft));
});

test('students cannot view other students drafts', function (): void {
    $student1 = User::factory()->create(['role' => UserRole::Student]);
    $student2 = User::factory()->create(['role' => UserRole::Student]);

    $draft = LegalDocumentDraft::factory()->create([
        'user_id' => $student1->id,
    ]);

    $response = $this->actingAs($student2)->get(route('student.document-trainer.show', $draft));
    $response->assertForbidden();
});
