<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\EducationCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EducationCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => EducationCategory::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        EducationCategory::query()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::random(4),
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('status', __('Kategoriya yaratildi.'));
    }

    public function update(Request $request, EducationCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($data);

        return back()->with('status', __('Kategoriya yangilandi.'));
    }

    public function destroy(EducationCategory $category): RedirectResponse
    {
        abort_if(Course::query()->where('category_id', $category->id)->whereHas('certificates')->exists(), 409, __('Kurslari bor kategoriyani o‘chirib bo‘lmaydi.'));
        $category->delete();

        return back()->with('status', __('Kategoriya o\'chirildi.'));
    }
}
