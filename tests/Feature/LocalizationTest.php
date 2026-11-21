<?php

use App\Models\User;
use App\Notifications\AcademyNotification;

use function Pest\Laravel\actingAs;

it('persists a guest language choice and renders Russian login labels', function () {
    $this->post(route('locale.update'), ['locale' => 'ru'])->assertRedirect()->assertSessionHas('locale', 'ru');

    $this->get(route('login'))->assertOk()->assertSee('lang="ru"', false)->assertSee('Вход в систему')->assertSee('Пароль');
});

it('uses saved user locale and updates that preference', function () {
    $user = User::factory()->student()->create(['locale' => 'uz-cyr']);
    actingAs($user)->get(route('student.dashboard'))->assertOk()->assertSee('lang="uz-Cyrl"', false)->assertSee('Ўқувчи панели');

    actingAs($user)->post(route('locale.update'), ['locale' => 'ru'])->assertRedirect();

    expect($user->fresh()->locale)->toBe('ru');
    actingAs($user)->get(route('student.dashboard'))->assertOk()->assertSee('Панель студента');
});

it('rejects unsupported locale paths without changing the saved language', function () {
    $user = User::factory()->student()->create(['locale' => 'uz-latin']);

    actingAs($user)->post(route('locale.update'), ['locale' => '../../config'])->assertSessionHasErrors('locale');

    expect($user->fresh()->locale)->toBe('uz-latin');
});

it('translates notification text in the recipients preferred locale', function () {
    $user = User::factory()->student()->create(['locale' => 'ru']);
    $user->notify(new AcademyNotification('Yangi shaxsiy xabar', '/conversations'));

    expect($user->notifications()->firstOrFail()->data['title'])->toBe('Новое личное сообщение');
});

it('shows the empty group list in the administrator’s language', function (string $locale, string $emptyMessage): void {
    $admin = User::factory()->academyAdmin()->create(['locale' => $locale]);

    actingAs($admin)->get(route('admin.groups.index'))
        ->assertOk()
        ->assertSeeText($emptyMessage);
})->with([
    'Uzbek Latin' => ['uz-latin', 'Hozircha guruh yo‘q'],
    'Uzbek Cyrillic' => ['uz-cyr', 'Ҳозирча гуруҳ йўқ'],
    'Russian' => ['ru', 'Пока нет групп'],
]);

it('includes the unread count in the Uzbek Cyrillic notification label', function (): void {
    $admin = User::factory()->academyAdmin()->create(['locale' => 'uz-cyr']);
    $admin->notify(new AcademyNotification('Yangi shaxsiy xabar', '/conversations'));

    actingAs($admin)->get(route('admin.groups.index'))
        ->assertOk()
        ->assertSee('aria-label="Билдиришномалар: 1 та янги"', false);
});
