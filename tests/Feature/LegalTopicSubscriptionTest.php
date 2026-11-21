<?php

use App\Models\LegalSource;
use App\Models\LegalTopicSubscription;
use App\Models\LibraryResource;
use App\Models\User;

it('lets an authenticated user manage subscriptions to published library topics', function (): void {
    $user = User::factory()->student()->create();
    LibraryResource::factory()->create(['topic' => 'Mehnat huquqi', 'status' => 'published']);
    LibraryResource::factory()->create(['topic' => 'Fuqarolik huquqi', 'status' => 'published']);
    LibraryResource::factory()->create(['topic' => 'Yashirin mavzu', 'status' => 'draft']);

    $this->actingAs($user)->get(route('legal-updates.index'))
        ->assertOk()
        ->assertSee('Qiziqqan mavzularingiz bo‘yicha xabar oling')
        ->assertSee('Mehnat huquqi')
        ->assertSee('Fuqarolik huquqi')
        ->assertDontSee('Yashirin mavzu');

    $this->put(route('legal-updates.subscriptions.update'), ['topics' => ['Mehnat huquqi']])
        ->assertRedirect();
    expect($user->legalTopicSubscriptions()->pluck('topic')->all())->toBe(['Mehnat huquqi']);

    $this->put(route('legal-updates.subscriptions.update'), [])
        ->assertRedirect();
    expect($user->legalTopicSubscriptions()->count())->toBe(0);
});

it('rejects subscription topics that are not currently published', function (): void {
    $user = User::factory()->student()->create();
    LibraryResource::factory()->create(['topic' => 'Arxiv mavzusi', 'status' => 'archived']);

    $this->actingAs($user)->put(route('legal-updates.subscriptions.update'), ['topics' => ['Arxiv mavzusi']])
        ->assertSessionHasErrors('topics.0');

    expect(LegalTopicSubscription::query()->count())->toBe(0);
});

it('requires authentication to manage legal update subscriptions', function (): void {
    $this->put(route('legal-updates.subscriptions.update'), [])->assertRedirect(route('login'));
});

it('lets an administrator assign only a published library topic to a legal source', function (): void {
    $admin = User::factory()->academyAdmin()->create();
    LibraryResource::factory()->create(['topic' => 'Mehnat huquqi', 'status' => 'published']);
    LibraryResource::factory()->create(['topic' => 'Arxiv mavzusi', 'status' => 'archived']);
    $sourceData = [
        'course_id' => null,
        'title' => 'Rasmiy mehnat manbasi',
        'url' => 'https://lex.uz/docs/-777',
        'excerpt' => 'Tasdiqlash uchun o‘quv parchasi.',
        'topic' => 'Mehnat huquqi',
    ];

    $this->actingAs($admin)->post(route('admin.legal-sources.store'), $sourceData)->assertRedirect();
    $source = LegalSource::query()->sole();
    expect($source->topic)->toBe('Mehnat huquqi');

    $this->patch(route('admin.legal-sources.update', $source), [
        'status' => 'verified',
        'confirmed' => '1',
        'topic' => 'Arxiv mavzusi',
    ])->assertSessionHasErrors('topic');
    expect($source->fresh()->status)->toBe('draft');
});
