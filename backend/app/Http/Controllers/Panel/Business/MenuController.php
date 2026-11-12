<?php

namespace App\Http\Controllers\Panel\Business;

use App\Models\AuditLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * İşletme yöneticisi: menü. Menü ve fiyatlar markaya aittir; tüm şubelerde aynıdır.
 * Ürün silinince geçmiş için yumuşak silinir (deleted_at). Sıralama yukarı/aşağı düğmeleriyle.
 */
class MenuController extends BaseController
{
    private function category(Request $request, int $id): MenuCategory
    {
        return MenuCategory::query()->where('business_id', $this->business($request)->id)->findOrFail($id);
    }

    private function item(Request $request, int $id): MenuItem
    {
        return MenuItem::query()->where('business_id', $this->business($request)->id)->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $business = $this->business($request);

        return view('panel.business.menu.index', [
            'business' => $business,
            'categories' => MenuCategory::query()->where('business_id', $business->id)
                ->orderBy('sort_order')->orderBy('id')->with('items')->get(),
        ]);
    }

    // ------------------------------------------------------------ Kategoriler

    public function storeCategory(Request $request): RedirectResponse
    {
        $business = $this->business($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'kategori adı']);
        $order = (int) MenuCategory::query()->where('business_id', $business->id)->max('sort_order') + 1;
        $c = MenuCategory::query()->create(['business_id' => $business->id, 'name' => $data['name'], 'sort_order' => $order, 'is_active' => true]);
        AuditLog::record($this->owner(), 'menu.category_created', $c);

        return redirect()->to(route('biz.menu').'#kategori-'.$c->id)->with('status', '"'.$c->name.'" kategorisi eklendi.');
    }

    public function updateCategory(Request $request, int $category): RedirectResponse
    {
        $c = $this->category($request, $category);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'kategori adı']);
        $c->update(['name' => $data['name'], 'is_active' => $request->boolean('is_active')]);

        return redirect()->to(route('biz.menu').'#kategori-'.$c->id)->with('status', 'Kategori güncellendi.');
    }

    public function deleteCategory(Request $request, int $category): RedirectResponse
    {
        $c = $this->category($request, $category);
        if (MenuItem::query()->where('menu_category_id', $c->id)->exists()) {
            return back()->with('error', '"'.$c->name.'" kategorisinde ürün var. Önce ürünleri silin veya başka kategoriye taşıyın; ya da kategoriyi gizleyin.');
        }
        // Daha önce silinmiş (yumuşak silinmiş) ürünler kategoriye bağlıdır. Menü ürünlerine başka hiçbir
        // tablo bağlı olmadığından bunlar görselleriyle birlikte kalıcı silinir, sonra kategori silinir.
        DB::transaction(function () use ($c) {
            foreach (MenuItem::onlyTrashed()->where('menu_category_id', $c->id)->get() as $old) {
                ImageStore::delete($old->image_path);
                $old->forceDelete();
            }
            $c->delete();
        });
        AuditLog::record($this->owner(), 'menu.category_deleted', null, ['name' => $c->name]);

        return back()->with('status', '"'.$c->name.'" kategorisi silindi.');
    }

    public function moveCategory(Request $request, int $category): RedirectResponse
    {
        $c = $this->category($request, $category);
        $this->swap(MenuCategory::query()->where('business_id', $c->business_id), $c, $request->input('yon') === 'yukari');

        return redirect()->to(route('biz.menu').'#kategori-'.$c->id);
    }

    // ------------------------------------------------------------ Ürünler

    public function createItem(Request $request): View
    {
        $business = $this->business($request);

        return view('panel.business.menu.item', [
            'item' => null,
            'categories' => MenuCategory::query()->where('business_id', $business->id)->orderBy('sort_order')->orderBy('id')->get(),
            'selected' => (int) $request->query('kategori'),
        ]);
    }

    public function editItem(Request $request, int $item): View
    {
        $i = $this->item($request, $item);

        return view('panel.business.menu.item', [
            'item' => $i,
            'categories' => MenuCategory::query()->where('business_id', $i->business_id)->orderBy('sort_order')->orderBy('id')->get(),
            'selected' => $i->menu_category_id,
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $business = $this->business($request);
        $data = $this->validatedItem($request);

        $order = (int) MenuItem::query()->where('menu_category_id', $data['menu_category_id'])->max('sort_order') + 1;
        $i = new MenuItem($data + ['business_id' => $business->id, 'sort_order' => $order]);
        if ($request->hasFile('image')) {
            $i->image_path = ImageStore::store($request->file('image'), 'menu/'.$business->id, 900);
        }
        $i->save();
        AuditLog::record($this->owner(), 'menu.item_created', $i, ['price' => (string) $i->price]);

        return redirect()->to(route('biz.menu').'#kategori-'.$i->menu_category_id)->with('status', '"'.$i->name.'" eklendi.');
    }

    public function updateItem(Request $request, int $item): RedirectResponse
    {
        $i = $this->item($request, $item);
        $data = $this->validatedItem($request);
        $oldPrice = (string) $i->price;

        if ($data['menu_category_id'] !== $i->menu_category_id) {
            $data['sort_order'] = (int) MenuItem::query()->where('menu_category_id', $data['menu_category_id'])->max('sort_order') + 1;
        }
        $i->fill($data);
        if ($request->boolean('remove_image') && $i->image_path) {
            ImageStore::delete($i->image_path);
            $i->image_path = null;
        }
        if ($request->hasFile('image')) {
            ImageStore::delete($i->image_path);
            $i->image_path = ImageStore::store($request->file('image'), 'menu/'.$i->business_id, 900);
        }
        $i->save();
        AuditLog::record($this->owner(), 'menu.item_updated', $i, $oldPrice !== (string) $i->price ? ['price_from' => $oldPrice, 'price_to' => (string) $i->price] : []);

        return redirect()->to(route('biz.menu').'#kategori-'.$i->menu_category_id)->with('status', '"'.$i->name.'" güncellendi.');
    }

    /** Satışa aç / geçici olarak kapat ("Şu an mevcut değil"). */
    public function toggleItem(Request $request, int $item): RedirectResponse
    {
        $i = $this->item($request, $item);
        $i->update(['is_available' => ! $i->is_available]);

        return redirect()->to(route('biz.menu').'#kategori-'.$i->menu_category_id)
            ->with('status', '"'.$i->name.'" '.($i->is_available ? 'yeniden satışta.' : 'geçici olarak satışa kapatıldı.'));
    }

    public function deleteItem(Request $request, int $item): RedirectResponse
    {
        $i = $this->item($request, $item);
        $i->delete(); // yumuşak silme
        AuditLog::record($this->owner(), 'menu.item_deleted', $i);

        return redirect()->to(route('biz.menu').'#kategori-'.$i->menu_category_id)->with('status', '"'.$i->name.'" menüden kaldırıldı.');
    }

    public function moveItem(Request $request, int $item): RedirectResponse
    {
        $i = $this->item($request, $item);
        $this->swap(MenuItem::query()->where('menu_category_id', $i->menu_category_id), $i, $request->input('yon') === 'yukari');

        return redirect()->to(route('biz.menu').'#kategori-'.$i->menu_category_id);
    }

    /** Sıra numaralarını 1..n olarak yeniden yazar ve kaydı bir üst/alt komşusuyla yer değiştirir. */
    private function swap($query, $model, bool $up): void
    {
        DB::transaction(function () use ($query, $model, $up) {
            $list = (clone $query)->orderBy('sort_order')->orderBy('id')->get();
            $ids = $list->pluck('id')->all();
            $pos = array_search($model->id, $ids, true);
            $target = $up ? $pos - 1 : $pos + 1;
            if ($pos === false || $target < 0 || $target >= count($ids)) {
                return;
            }
            [$ids[$pos], $ids[$target]] = [$ids[$target], $ids[$pos]];
            foreach ($ids as $n => $id) {
                $list->firstWhere('id', $id)->forceFill(['sort_order' => $n + 1])->save();
            }
        });
    }

    private function validatedItem(Request $request): array
    {
        $business = $this->business($request);
        $request->merge(['price' => $this->normalizeMoney($request->input('price'))]);

        $data = $request->validate([
            'menu_category_id' => ['required', 'integer', Rule::exists('menu_categories', 'id')->where('business_id', $business->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ], [
            'price.regex' => 'Fiyatı 125,50 biçiminde yazın.',
            'image.mimes' => 'Görsel JPG, PNG veya WEBP olmalıdır.',
            'image.max' => 'Görsel en fazla 6 MB olabilir.',
        ], ['menu_category_id' => 'kategori', 'name' => 'ürün adı', 'description' => 'açıklama', 'price' => 'fiyat', 'image' => 'görsel']);

        return [
            'menu_category_id' => (int) $data['menu_category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => number_format((float) $data['price'], 2, '.', ''),
            'is_available' => $request->boolean('is_available', true),
        ];
    }

    private function normalizeMoney(mixed $value): ?string
    {
        $value = str_replace([' ', 'TL', '₺'], '', trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return $value;
    }
}
