<?php

use App\Models\LibraryBookmark;
use App\Models\LibraryResource;
use App\Models\User;
use Database\Seeders\LegalLibrarySeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('imports the curated library once and preserves subsequent administrator changes', function () {
    $this->seed(LegalLibrarySeeder::class);
    $labor = LibraryResource::query()->where('catalog_key', 'lex-6257288')->sole();
    expect($labor->url)->toBe('https://lex.uz/docs/-6257288');
    $labor->update(['status' => 'archived', 'description' => 'Administrator reviewed description']);

    $this->seed(LegalLibrarySeeder::class);

    assertDatabaseCount('library_resources', 49);
    expect($labor->fresh()->status)->toBe('archived');
    expect($labor->fresh()->description)->toBe('Administrator reviewed description');
    assertDatabaseHas('library_resources', ['catalog_key' => 'lex-3517337', 'type' => 'code']);
    assertDatabaseHas('library_resources', ['catalog_key' => 'lex-5307951', 'title' => 'Shaharsozlik kodeksi']);
    expect(LibraryResource::query()->where('type', 'textbook')->where('access', 'account')->count())->toBe(8);
});

it('requires authentication for the library', function () {
    $this->get(route('library.index'))->assertRedirect(route('login'));
});

it('allows research without course enrollment and hides unpublished materials', function () {
    $published = LibraryResource::factory()->create(['title' => 'Public code']);
    $draft = LibraryResource::factory()->create(['title' => 'Unpublished draft', 'status' => 'draft']);
    actingAs(User::factory()->student()->create())->get(route('library.index'))->assertOk()->assertSee('Public code')->assertDontSee('Unpublished draft');
    $this->get(route('library.show', $published))->assertOk();
    $this->get(route('library.show', $draft))->assertNotFound();
    $this->put(route('library.bookmark', $draft), ['saved' => true])->assertNotFound();
    $this->put(route('library.reading', $draft), ['state' => 'completed'])->assertNotFound();
});

it('combines search topic and resource type filters and paginates results', function () {
    LibraryResource::factory()->count(19)->create(['title' => 'Civil research', 'type' => 'code', 'topic' => 'Fuqarolik huquqi']);
    LibraryResource::factory()->create(['title' => 'Civil textbook', 'type' => 'textbook', 'topic' => 'Fuqarolik huquqi']);
    LibraryResource::factory()->create(['title' => 'Foreign topic', 'type' => 'code', 'topic' => 'Mehnat huquqi']);
    actingAs(User::factory()->student()->create())->get(route('library.index', ['q' => 'Civil', 'type' => 'code', 'topic' => 'Fuqarolik huquqi', 'page' => 2]))->assertOk()
        ->assertViewHas('resources', fn ($resources) => $resources->total() === 19 && $resources->count() === 1)
        ->assertDontSee('Civil textbook')->assertDontSee('Foreign topic');
});

it('keeps reading lists and notes private even when a different user id is submitted', function () {
    $owner = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $resource = LibraryResource::factory()->create();
    LibraryBookmark::factory()->create(['user_id' => $owner->id, 'library_resource_id' => $resource->id, 'notes' => 'Private owner notes', 'state' => 'reading']);
    actingAs($other)->get(route('library.index', ['saved' => 1]))->assertOk()->assertViewHas('resources', fn ($rows) => $rows->total() === 0);
    $this->get(route('library.show', $resource))->assertOk()->assertDontSee('Private owner notes');

    $this->put(route('library.reading', $resource), ['user_id' => $owner->id, 'state' => 'completed', 'notes' => 'My own notes'])->assertRedirect();

    assertDatabaseHas('library_bookmarks', ['user_id' => $owner->id, 'notes' => 'Private owner notes', 'state' => 'reading']);
    assertDatabaseHas('library_bookmarks', ['user_id' => $other->id, 'notes' => 'My own notes', 'state' => 'completed']);
    assertDatabaseCount('lesson_progress', 0);
});

it('saves bookmarks idempotently without clearing existing reading notes', function () {
    $user = User::factory()->student()->create();
    $resource = LibraryResource::factory()->create();
    LibraryBookmark::factory()->create(['user_id' => $user->id, 'library_resource_id' => $resource->id, 'notes' => 'Keep these notes', 'state' => 'reading']);
    actingAs($user)->put(route('library.bookmark', $resource), ['saved' => true])->assertRedirect();
    $this->put(route('library.bookmark', $resource), ['saved' => true])->assertRedirect();
    assertDatabaseCount('library_bookmarks', 1);
    assertDatabaseHas('library_bookmarks', ['state' => 'reading', 'notes' => 'Keep these notes']);

    $this->put(route('library.bookmark', $resource), ['saved' => false])->assertRedirect();

    assertDatabaseCount('library_bookmarks', 0);
});

it('escapes library titles and private reading notes', function () {
    $user = User::factory()->student()->create();
    $resource = LibraryResource::factory()->create(['title' => '<script>alert(1)</script>']);
    LibraryBookmark::factory()->create(['user_id' => $user->id, 'library_resource_id' => $resource->id, 'notes' => '</textarea><script>alert(2)</script>']);

    actingAs($user)->get(route('library.show', $resource))->assertOk()->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<script>alert(2)</script>', false);
});

it('prevents unprivileged roles from managing the shared catalogue', function (string $role) {
    $resource = LibraryResource::factory()->create();
    actingAs(User::factory()->create(['role' => $role]))->get(route('admin.library.index'))->assertForbidden();
    $this->post(route('admin.library.store'), [])->assertForbidden();
    $this->put(route('admin.library.update', $resource), [])->assertForbidden();
})->with(['student', 'teacher', 'mentor']);

it('allows administrators to publish original guides and rejects unsafe external links', function () {
    $admin = User::factory()->academyAdmin()->create();
    $data = ['title' => 'Original guide', 'type' => 'guide', 'topic' => 'Research', 'description' => 'Study methods', 'body' => 'Read the primary source and note its date.',
        'publisher' => 'Academy', 'language' => 'uz-latin', 'access' => 'open', 'status' => 'published'];
    actingAs($admin)->post(route('admin.library.store'), [...$data, 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
    assertDatabaseCount('library_resources', 0);

    $this->post(route('admin.library.store'), $data)->assertRedirect();

    assertDatabaseHas('library_resources', ['title' => 'Original guide', 'created_by' => $admin->id, 'status' => 'published']);
    $this->get(route('admin.library.index'))->assertOk()->assertSee('Original guide');
    $this->get(route('admin.library.edit', LibraryResource::query()->sole()))->assertOk();
});

it('clears old link verification metadata when an administrator changes the URL', function () {
    $resource = LibraryResource::factory()->create(['checked_at' => '2026-11-17']);
    $data = $resource->only(['title', 'type', 'topic', 'description', 'publisher', 'language', 'access', 'status']);
    actingAs(User::factory()->academyAdmin()->create())->put(route('admin.library.update', $resource), $data + ['url' => 'https://lex.uz/uz/'])->assertRedirect();

    expect($resource->fresh()->checked_at)->toBeNull();
});

it('includes published library materials in global research search', function () {
    $resource = LibraryResource::factory()->create(['title' => 'Kodeks for research']);
    LibraryResource::factory()->create(['title' => 'Kodeks hidden draft', 'status' => 'draft']);

    actingAs(User::factory()->student()->create())->get(route('search', ['q' => 'Kodeks']))->assertOk()
        ->assertSee('Kodeks for research')->assertSee(route('library.show', $resource), false)->assertDontSee('Kodeks hidden draft');
});
