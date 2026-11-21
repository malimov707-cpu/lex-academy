<?php

use App\Models\LibraryResource;
use App\Models\User;
use App\Services\LegalDocumentExtractor;
use App\Services\LibraryContentImporter;
use App\Services\LibraryReader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/** A source-shaped fixture with editorial commentary and toolbar noise. */
function localLawFixture(): string
{
    return '<html><div id="divCont"><div class="ACT_TITLE lx_elem"><div class="lx_elem2">Toolbar</div><div>QONUN</div></div>'
        .'<div class="CLAUSE_DEFAULT lx_elem"><div>1-modda.</div></div><div class="ACT_TEXT lx_elem"><div class="lx_elem2">Send feedback</div><div>'.str_repeat('Rasmiy matn. ', 100).'</div></div>'
        .'<div class="COMMENTLEXUZ lx_elem">Editorial commentary</div><script>evil()</script></div></html>';
}

it('extracts official paragraphs and contents without editorial commentary or interface text', function () {
    $result = app(LegalDocumentExtractor::class)->extract(localLawFixture());

    expect($result['body'])->toContain('1-modda.', 'Rasmiy matn.')->not->toContain('Toolbar', 'Send feedback', 'Editorial commentary', 'evil()');
    expect($result['contents'])->toHaveCount(2);
});

it('imports a legal document locally with provenance checksum and a usable contents list', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    Http::fake(['https://lex.uz/docs/-6445145' => Http::response(localLawFixture(), 200)]);
    $resource = LibraryResource::factory()->create(['catalog_key' => 'lex-6445145', 'type' => 'constitution', 'url' => 'https://lex.uz/docs/-6445145']);

    app(LibraryContentImporter::class)->importLaw($resource);

    $resource->refresh();
    Storage::disk('local')->assertExists($resource->local_path);
    expect(hash('sha256', Storage::disk('local')->get($resource->local_path)))->toBe($resource->content_sha256);
    expect($resource->body)->toContain('Rasmiy matn.');
    expect($resource->contents)->toHaveCount(2);
    expect($resource->import_source_url)->toBe('https://lex.uz/docs/-6445145');
    Http::assertSentCount(1);
});

it('preserves the existing snapshot when extraction fails', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    Http::fake(['https://lex.uz/docs/-6445145' => Http::response('<html>Temporary error page</html>', 200)]);
    $resource = LibraryResource::factory()->create(['url' => 'https://lex.uz/docs/-6445145', 'body' => 'Existing complete text', 'local_path' => 'library/old.txt']);

    expect(fn () => app(LibraryContentImporter::class)->importLaw($resource))->toThrow(RuntimeException::class);

    expect($resource->fresh()->body)->toBe('Existing complete text');
    expect($resource->fresh()->local_path)->toBe('library/old.txt');
    Http::assertSentCount(1);
});

it('refuses arbitrary hosts and closed books before making a request', function () {
    Http::preventStrayRequests();
    $resource = LibraryResource::factory()->create(['url' => 'https://127.0.0.1/private']);
    expect(fn () => app(LibraryContentImporter::class)->importLaw($resource))->toThrow(RuntimeException::class);
    expect(fn () => app(LibraryContentImporter::class)->importBook('tsul-unlicensed'))->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

it('imports a licensed book as a local PDF retaining attribution', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    $key = 'openstax-intellectual-property-2021';
    $url = config('library_content.books.'.$key.'.download_url');
    Http::fake([$url => Http::response('%PDF-1.7'.str_repeat('x', 12000).'%%EOF', 200, ['Content-Type' => 'application/pdf'])]);

    app(LibraryContentImporter::class)->importBook($key);

    $resource = LibraryResource::query()->where('catalog_key', $key)->sole();
    expect($resource->license)->toBe('CC BY 4.0');
    expect($resource->attribution)->toContain('David Kline', 'David Kappos');
    expect($resource->local_format)->toBe('pdf');
    Storage::disk('local')->assertExists($resource->local_path);
    Http::assertSentCount(1);
});

it('rejects non-PDF book responses without publishing a fake local book', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    $key = 'openstax-intellectual-property-2021';
    Http::fake([config('library_content.books.'.$key.'.download_url') => Http::response(str_repeat('error', 5000), 200)]);

    expect(fn () => app(LibraryContentImporter::class)->importBook($key))->toThrow(RuntimeException::class);

    expect(LibraryResource::query()->where('catalog_key', $key)->exists())->toBeFalse();
    expect(Storage::disk('local')->allFiles('library'))->toBeEmpty();
    Http::assertSentCount(1);
});

it('paginates readable text without dropping characters and links searches to the correct page', function () {
    $reader = app(LibraryReader::class);
    $body = str_repeat("First paragraph.\n\n", 1100).'UNIQUE ARTICLE '.str_repeat("Last paragraph.\n\n", 1000);
    $pages = $reader->pages($body);

    expect(implode('', array_column($pages, 'text')))->toBe($body);
    expect($pages)->toHaveCount(3);
    $matches = $reader->find($body, 'UNIQUE ARTICLE', $pages);
    expect($matches)->toHaveCount(1);
    expect($matches[0]['page'])->toBe(2);
});

it('reads local text and downloads its file without external requests', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    $text = 'A local legal document body.';
    $hash = hash('sha256', $text);
    $path = 'library/example/'.$hash.'.txt';
    Storage::disk('local')->put($path, $text);
    $resource = LibraryResource::factory()->create(['catalog_key' => 'example', 'body' => $text, 'local_path' => $path, 'local_format' => 'txt', 'content_sha256' => $hash, 'attribution' => 'Original source credit']);

    actingAs(User::factory()->student()->create())->get(route('library.reader', $resource))->assertOk()->assertSee($text)->assertSee('Original source credit');
    $this->get(route('library.file', $resource))->assertOk()->assertDownload('example.txt');
    Http::assertNothingSent();
});

it('uses a same-origin PDF viewer and enforces file access for unpublished resources', function () {
    Storage::fake('local');
    $pdf = '%PDF-1.7 sample';
    $path = 'library/book/'.hash('sha256', $pdf).'.pdf';
    Storage::disk('local')->put($path, $pdf);
    $resource = LibraryResource::factory()->create(['local_path' => $path, 'local_format' => 'pdf']);
    $student = User::factory()->student()->create();
    actingAs($student)->get(route('library.reader', $resource))->assertOk()->assertSee(route('library.file', $resource), false);
    $this->get(route('library.file', $resource))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $resource->update(['status' => 'draft']);
    $this->get(route('library.file', $resource))->assertNotFound();
    $this->get(route('library.reader', $resource))->assertNotFound();
});

it('cannot read a path outside private library storage', function () {
    $resource = LibraryResource::factory()->create(['local_path' => '../.env', 'local_format' => 'txt']);

    actingAs(User::factory()->student()->create())->get(route('library.file', $resource))->assertNotFound();
});

it('filters to locally readable materials and searches their full text', function () {
    $local = LibraryResource::factory()->create(['body' => 'Only body has secretneedle', 'title' => 'Readable']);
    LibraryResource::factory()->create(['title' => 'Remote catalogue']);
    actingAs(User::factory()->student()->create())->get(route('library.index', ['local' => 1, 'q' => 'secretneedle']))->assertOk()
        ->assertViewHas('resources', fn ($rows) => $rows->pluck('id')->all() === [$local->id])
        ->assertSee(route('library.reader', $local), false)->assertDontSee('Remote catalogue');
});

it('skips already imported snapshots instead of silently refreshing them', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    $path = 'library/existing/'.str_repeat('a', 64).'.txt';
    Storage::disk('local')->put($path, 'Already imported');
    LibraryResource::factory()->create(['catalog_key' => 'lex-6445145', 'url' => 'https://lex.uz/docs/-6445145', 'body' => 'Already imported', 'local_path' => $path]);

    $this->artisan('library:import-content', ['--laws-only' => true, '--key' => ['lex-6445145']])->assertSuccessful();

    Http::assertNothingSent();
});

it('shows safe search excerpts and rejects pages outside the local document', function () {
    $resource = LibraryResource::factory()->create(['body' => str_repeat('Law text. ', 1800).'<script>needle</script>']);
    actingAs(User::factory()->student()->create())->get(route('library.reader', [$resource, 'find' => 'needle']))->assertOk()
        ->assertSee('needle')->assertDontSee('<script>needle</script>', false);
    $this->get(route('library.reader', [$resource, 'page' => 999]))->assertNotFound();
    $this->get(route('library.reader', [$resource, 'page' => -1]))->assertSessionHasErrors('page');
});

it('requires authentication before serving local files', function () {
    $resource = LibraryResource::factory()->create(['local_path' => 'library/book/'.str_repeat('a', 64).'.pdf', 'local_format' => 'pdf']);

    $this->get(route('library.file', $resource))->assertRedirect(route('login'));
    $this->get(route('library.reader', $resource))->assertRedirect(route('login'));
});

it('does not let metadata edits change an imported snapshot body independently of its file', function () {
    $resource = LibraryResource::factory()->create(['body' => 'Original imported body', 'local_path' => 'library/book/'.str_repeat('a', 64).'.txt', 'local_format' => 'txt']);
    $data = $resource->only(['title', 'type', 'topic', 'description', 'url', 'publisher', 'language', 'access', 'status']);

    actingAs(User::factory()->academyAdmin()->create())->put(route('admin.library.update', $resource), $data + ['body' => 'Forged source body'])->assertRedirect();

    expect($resource->fresh()->body)->toBe('Original imported body');
});
