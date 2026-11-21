<?php

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\LegalSource;
use App\Models\LegalTopicSubscription;
use App\Models\LibraryResource;
use App\Models\User;
use App\Services\LegalSourceMonitor;
use Illuminate\Support\Facades\Http;

it('stores a first source snapshot as a baseline without calling it a legal update', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://lex.uz/docs/1' => Http::response('<html><nav>Menu</nav><main>Official legal text.</main></html>', 200, [
            'ETag' => '"rev-1"', 'Last-Modified' => 'Sat, 21 Nov 2026 00:00:00 GMT',
        ]),
    ]);
    $verifier = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create(['url' => 'https://lex.uz/docs/1', 'status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);

    $check = app(LegalSourceMonitor::class)->check($source);

    expect($check->status)->toBe('baseline')
        ->and($check->content_sha256)->toHaveLength(64)
        ->and($check->http_status)->toBe(200)
        ->and($check->etag)->toBe('"rev-1"')
        ->and($check->reviewed_at)->toBeNull();
    Http::assertSentCount(1);
});

it('detects a source page text change and keeps it pending human review', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://lex.uz/docs/2' => Http::sequence()
            ->push('<main>Paragraph one. Paragraph two.</main>')
            ->push('<main>Paragraph one. A changed legal page.</main>'),
    ]);
    $verifier = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create(['url' => 'https://lex.uz/docs/2', 'topic' => 'Mehnat huquqi', 'status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    LibraryResource::factory()->create(['url' => 'https://lex.uz/docs/other', 'topic' => 'Mehnat huquqi', 'status' => 'published']);
    $matchingSubscriber = User::factory()->student()->create();
    LegalTopicSubscription::query()->create(['user_id' => $matchingSubscriber->id, 'topic' => 'Mehnat huquqi']);
    $unrelatedSubscriber = User::factory()->student()->create();
    LegalTopicSubscription::query()->create(['user_id' => $unrelatedSubscriber->id, 'topic' => 'Fuqarolik huquqi']);
    $monitor = app(LegalSourceMonitor::class);

    $first = $monitor->check($source);
    $changed = $monitor->check($source);

    expect($first->status)->toBe('baseline')
        ->and($changed->status)->toBe('changed')
        ->and($changed->reviewed_at)->toBeNull();
    expect($matchingSubscriber->notifications()->count())->toBe(1)
        ->and($matchingSubscriber->notifications()->first()->data['title'])->toContain('Mehnat huquqi')
        ->and($unrelatedSubscriber->notifications()->count())->toBe(0);
    Http::assertSentCount(2);
});

it('treats HTML formatting changes as unchanged text', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://lex.uz/docs/3' => Http::sequence()
            ->push('<main>Paragraph one.  Paragraph two.</main>')
            ->push('<main><p>Paragraph one.</p><p>Paragraph two.</p></main>'),
    ]);
    $verifier = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create(['url' => 'https://lex.uz/docs/3', 'status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    $monitor = app(LegalSourceMonitor::class);

    $monitor->check($source);
    $second = $monitor->check($source);

    expect($second->status)->toBe('unchanged');
});

it('refuses to fetch unverified and non-allow-listed source URLs', function (): void {
    Http::preventStrayRequests();
    $monitor = app(LegalSourceMonitor::class);
    $unverified = LegalSource::factory()->create(['status' => 'draft']);
    $verifier = User::factory()->academyAdmin()->create();
    $external = LegalSource::factory()->create(['url' => 'https://example.com/legal', 'status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);

    expect($monitor->check($unverified)->status)->toBe('unapproved')
        ->and($monitor->check($external)->status)->toBe('unapproved');
    Http::assertNothingSent();
});

it('records remote failures without replacing a prior source snapshot', function (): void {
    Http::preventStrayRequests();
    Http::fake(['https://lex.uz/docs/4' => Http::response('Unavailable', 503)]);
    $verifier = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create(['url' => 'https://lex.uz/docs/4', 'status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);

    $check = app(LegalSourceMonitor::class)->check($source);

    expect($check->status)->toBe('unavailable')
        ->and($check->http_status)->toBe(503)
        ->and($check->content_sha256)->toBeNull();
});

it('allows administrators to acknowledge a detected change but denies students', function (): void {
    $admin = User::factory()->role(UserRole::AcademyAdmin)->create();
    $source = LegalSource::factory()->create(['status' => 'verified', 'verified_by' => $admin->id, 'verified_at' => now()]);
    $check = $source->checks()->create([
        'status' => 'changed', 'content_sha256' => str_repeat('a', 64), 'checked_at' => now(),
    ]);
    $url = route('admin.legal-sources.checks.review', [$source, $check]);

    $this->actingAs(User::factory()->student()->create())->post($url)->assertForbidden();
    $this->actingAs($admin)->post($url, ['impact_status' => 'review_required', 'review_note' => 'Bog‘langan darslarni tekshirish kerak.'])->assertRedirect();

    expect($check->fresh()->reviewed_by)->toBe($admin->id)
        ->and($check->fresh()->reviewed_at)->not->toBeNull()
        ->and($check->fresh()->impact_status)->toBe('review_required')
        ->and($check->fresh()->review_note)->toBe('Bog‘langan darslarni tekshirish kerak.');
});

it('allows the assigned course teacher to record educational impact but not review another course', function (): void {
    $teacher = User::factory()->teacher()->create();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $source = LegalSource::factory()->create(['course_id' => $course->id, 'status' => 'verified', 'verified_by' => User::factory()->academyAdmin()->create()->id, 'verified_at' => now()]);
    $check = $source->checks()->create(['status' => 'changed', 'checked_at' => now()]);
    $url = route('admin.legal-sources.checks.review', [$source, $check]);

    $this->actingAs($teacher)->post($url, ['impact_status' => 'updated'])->assertRedirect();
    expect($check->fresh()->reviewed_by)->toBe($teacher->id)->and($check->fresh()->impact_status)->toBe('updated');

    $otherCourseSource = LegalSource::factory()->create(['status' => 'verified', 'verified_by' => User::factory()->academyAdmin()->create()->id, 'verified_at' => now()]);
    $otherCheck = $otherCourseSource->checks()->create(['status' => 'changed', 'checked_at' => now()]);
    $this->post(route('admin.legal-sources.checks.review', [$otherCourseSource, $otherCheck]), ['impact_status' => 'updated'])->assertForbidden();
});
