<?php

namespace App\Http\Controllers;

use App\Models\LibraryBookmark;
use App\Models\LibraryResource;
use App\Services\LibraryReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', Rule::in(array_keys(LibraryResource::types()))],
            'topic' => ['nullable', 'string', 'max:100'], 'saved' => ['nullable', 'boolean'], 'local' => ['nullable', 'boolean']]);
        $userId = $request->user()->id;
        $query = LibraryResource::query()->where('status', 'published')
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search->where('title', 'like', '%'.$term.'%')->orWhere('description', 'like', '%'.$term.'%')->orWhere('publisher', 'like', '%'.$term.'%')->orWhere('body', 'like', '%'.$term.'%')))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['topic'] ?? null, fn ($query, $topic) => $query->where('topic', $topic))
            ->when($request->boolean('local'), fn ($query) => $query->where(fn ($local) => $local->whereNotNull('local_path')->orWhereNotNull('body')))
            ->when($request->boolean('saved'), fn ($query) => $query->whereHas('bookmarks', fn ($bookmarks) => $bookmarks->where('user_id', $userId)))
            ->with(['bookmarks' => fn ($query) => $query->where('user_id', $userId)]);

        return view('library.index', ['resources' => $query->select(['id', 'title', 'type', 'topic', 'description', 'publisher', 'year', 'access', 'local_path', 'local_format', 'imported_at'])->selectRaw('CASE WHEN body IS NOT NULL THEN 1 ELSE 0 END as has_body')->orderBy('type')->orderBy('title')->paginate(18)->withQueryString(),
            'topics' => LibraryResource::query()->where('status', 'published')->distinct()->orderBy('topic')->pluck('topic'),
            'types' => LibraryResource::types(), 'filters' => $filters,
            'counts' => LibraryResource::query()->where('status', 'published')->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type')]);
    }

    public function show(Request $request, LibraryResource $resource): View
    {
        abort_unless($resource->status === 'published' || $request->user()->isAdmin(), 404);

        return view('library.show', ['resource' => $resource, 'types' => LibraryResource::types(),
            'bookmark' => $resource->bookmarks()->where('user_id', $request->user()->id)->first(),
            'related' => LibraryResource::query()->where('status', 'published')->where('topic', $resource->topic)->whereKeyNot($resource->id)->limit(4)->get()]);
    }

    public function reader(Request $request, LibraryResource $resource, LibraryReader $reader): View
    {
        abort_unless($resource->status === 'published' || $request->user()->isAdmin(), 404);
        abort_unless($resource->body || $resource->local_path, 404);
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'find' => ['nullable', 'string', 'max:100']]);
        $pages = $reader->pages($resource->body ?? '');
        $page = (int) ($data['page'] ?? 1);
        abort_if($page > max(1, count($pages)), 404);
        $contents = collect($resource->contents ?? [])->map(fn ($item): array => $item + ['page' => $reader->pageAt($pages, $item['offset'])]);

        return view('library.reader', ['resource' => $resource, 'page' => $page, 'totalPages' => count($pages),
            'pageText' => $pages[$page - 1]['text'] ?? '', 'contents' => $contents,
            'find' => $data['find'] ?? '', 'matches' => $reader->find($resource->body ?? '', $data['find'] ?? '', $pages)]);
    }

    public function file(Request $request, LibraryResource $resource): BinaryFileResponse
    {
        abort_unless($resource->status === 'published' || $request->user()->isAdmin(), 404);
        abort_unless($resource->local_path && preg_match('~\Alibrary/[a-zA-Z0-9_-]+/[a-f0-9]{64}\.(txt|pdf)\z~', $resource->local_path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($resource->local_path), 404);
        $headers = ['Content-Type' => $resource->local_format === 'pdf' ? 'application/pdf' : 'text/plain; charset=UTF-8',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($request->boolean('download') || $resource->local_format !== 'pdf') {
            return response()->download($disk->path($resource->local_path), $resource->catalog_key.'.'.$resource->local_format, $headers);
        }

        return response()->file($disk->path($resource->local_path), $headers);
    }

    public function bookmark(Request $request, LibraryResource $resource): RedirectResponse
    {
        abort_unless($resource->status === 'published', 404);
        $data = $request->validate(['saved' => ['required', 'boolean']]);
        $identity = ['user_id' => $request->user()->id, 'library_resource_id' => $resource->id];
        if ($data['saved']) {
            LibraryBookmark::query()->firstOrCreate($identity, ['state' => 'saved']);
        } else {
            LibraryBookmark::query()->where($identity)->delete();
        }

        return back()->with('status', __('O‘qish ro‘yxati yangilandi.'));
    }

    public function reading(Request $request, LibraryResource $resource): RedirectResponse
    {
        abort_unless($resource->status === 'published', 404);
        $data = $request->validate(['state' => ['required', 'in:saved,reading,completed'], 'notes' => ['nullable', 'string', 'max:10000']]);
        LibraryBookmark::query()->updateOrCreate(['user_id' => $request->user()->id, 'library_resource_id' => $resource->id], $data);

        return back()->with('status', __('Shaxsiy konspekt saqlandi.'));
    }
}
