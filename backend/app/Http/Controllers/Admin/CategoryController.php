<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Restoran (mutfak) kategorileri. Kullanılan kategori silinmez, pasif yapılır. */
class CategoryController extends AdminController
{
    public function index(): View
    {
        return view('admin.categories', [
            'categories' => Category::query()
                ->withCount('businesses')
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $category = Category::query()->create($data);
        AuditLog::record($this->admin(), 'category.created', $category, $data);

        return back()->with('status', "\"{$category->name}\" kategorisi eklendi.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $category->update($data);
        AuditLog::record($this->admin(), 'category.updated', $category, $data);

        return back()->with('status', "\"{$category->name}\" kategorisi güncellendi.");
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $request->merge([
            'slug' => Str::slug(strtr((string) ($request->input('slug') ?: $request->input('name')), ['ı' => 'i', 'İ' => 'i'])),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['name' => 'kategori adı', 'slug' => 'adres (slug)', 'sort_order' => 'sıra']);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
