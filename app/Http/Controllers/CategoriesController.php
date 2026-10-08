<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CategoriesController extends Controller
{
    protected function tenantId(Request $request): ?int
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return $user->tenant_id ?? $user->id;
        }

        return $user->tenant_id;
    }

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId($request);
        $categories = Category::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with('parent:id,name')
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'parent_id' => $c->parent_id,
                'parent_name' => $c->parent?->name,
                'image_url' => $c->imageUrl(),
                'position' => $c->position,
                'is_active' => $c->is_active,
                'is_featured_circle' => $c->is_featured_circle,
                'show_in_menu' => $c->show_in_menu,
                'products_count' => $c->products_count,
                'description' => $c->description,
            ]);

        return Inertia::render('Categorias/Index', [
            'categories' => $categories,
            'pageTitle' => 'Categorias',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured_circle' => ['nullable', 'boolean'],
            'show_in_menu' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
        }

        Category::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?: Category::uniqueSlug($data['name'], $tenantId),
            'parent_id' => $data['parent_id'] ?? null,
            'position' => (int) ($data['position'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_featured_circle' => (bool) ($data['is_featured_circle'] ?? false),
            'show_in_menu' => (bool) ($data['show_in_menu'] ?? true),
            'description' => $data['description'] ?? null,
            'image' => $path,
        ]);

        return back()->with('success', 'Categoria criada.');
    }

    public function update(Request $request, Category $categoria): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        abort_unless((int) $categoria->tenant_id === (int) $tenantId || $request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured_circle' => ['nullable', 'boolean'],
            'show_in_menu' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            if ($categoria->image) {
                Storage::disk('public')->delete($categoria->image);
            }
            $categoria->image = $request->file('image')->store('categories', 'public');
        }

        $categoria->fill([
            'name' => $data['name'],
            'slug' => $data['slug'] ?: $categoria->slug,
            'parent_id' => $data['parent_id'] ?? null,
            'position' => (int) ($data['position'] ?? $categoria->position),
            'is_active' => (bool) ($data['is_active'] ?? $categoria->is_active),
            'is_featured_circle' => (bool) ($data['is_featured_circle'] ?? $categoria->is_featured_circle),
            'show_in_menu' => (bool) ($data['show_in_menu'] ?? $categoria->show_in_menu),
            'description' => $data['description'] ?? null,
        ])->save();

        return back()->with('success', 'Categoria atualizada.');
    }

    public function destroy(Request $request, Category $categoria): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        abort_unless((int) $categoria->tenant_id === (int) $tenantId || $request->user()->isAdmin(), 403);
        $categoria->delete();

        return back()->with('success', 'Categoria removida.');
    }
}
