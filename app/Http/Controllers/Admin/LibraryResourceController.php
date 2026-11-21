<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LibraryResourceController extends Controller
{
    public function index(): View
    {
        return view('admin.library', ['resources' => LibraryResource::query()->latest('id')->paginate(20), 'types' => LibraryResource::types()]);
    }

    public function store(Request $request): RedirectResponse
    {
        LibraryResource::query()->create($this->validated($request) + ['catalog_key' => 'custom-'.Str::uuid(), 'created_by' => $request->user()->id]);

        return back()->with('status', __('Kutubxona materiali qo‘shildi.'));
    }

    public function edit(LibraryResource $resource): View
    {
        return view('admin.library-edit', ['resource' => $resource, 'types' => LibraryResource::types()]);
    }

    public function update(Request $request, LibraryResource $resource): RedirectResponse
    {
        $data = $this->validated($request, $resource);
        if (($data['url'] ?? null) !== $resource->url) {
            $data['checked_at'] = null;
        }
        $resource->update($data);

        return back()->with('status', __('Kutubxona materiali yangilandi.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?LibraryResource $resource = null): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(array_keys(LibraryResource::types()))],
            'topic' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:5000'],
            'body' => [Rule::excludeIf($resource?->local_path !== null), 'nullable', 'required_if:type,guide', 'string', 'max:100000'], 'url' => ['nullable', 'required_unless:type,guide', 'url:https', 'max:2000'],
            'publisher' => ['required', 'string', 'max:255'], 'language' => ['required', 'in:uz-latin,uz-cyr,ru,en'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'], 'access' => ['required', 'in:open,account'], 'status' => ['required', 'in:draft,published,archived']]);
    }
}
