<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchHour;
use App\Models\BranchPhoto;
use App\Models\PanelUser;
use App\Models\RewardRateHistory;
use App\Support\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Şube yöneticisi: ödül oranı, şube bilgileri, çalışma saatleri, fotoğraflar. */
class BranchSettingsController extends Controller
{
    public const MAX_PHOTOS = 10;

    private function branch(Request $request): Branch
    {
        return $request->attributes->get('branch');
    }

    private function manager(): PanelUser
    {
        /** @var PanelUser */
        return Auth::guard('web')->user();
    }

    // ------------------------------------------------------------ Ödül oranı

    public function rate(Request $request): View
    {
        $branch = $this->branch($request);

        return view('panel.settings.rate', [
            'branch' => $branch,
            'history' => RewardRateHistory::query()->where('branch_id', $branch->id)
                ->with('changer')->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function updateRate(Request $request): RedirectResponse
    {
        $branch = $this->branch($request);
        $request->merge(['reward_rate' => str_replace(['%', ' '], '', str_replace(',', '.', (string) $request->input('reward_rate')))]);

        $data = $request->validate([
            'reward_rate' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'reward_terms' => ['nullable', 'string', 'max:2000'],
        ], [
            'reward_rate.regex' => 'Oranı en fazla iki ondalıkla yazın (örn. 7,5).',
        ], ['reward_rate' => 'ödül oranı', 'reward_terms' => 'kullanım koşulları']);

        $new = number_format((float) $data['reward_rate'], 2, '.', '');
        $old = (string) $branch->reward_rate;

        DB::transaction(function () use ($branch, $data, $new, $old) {
            $branch->update(['reward_rate' => $new, 'reward_terms' => $data['reward_terms'] ?: null]);
            if ($new !== $old) {
                RewardRateHistory::query()->create([
                    'branch_id' => $branch->id, 'old_rate' => $old, 'new_rate' => $new, 'changed_by' => $this->manager()->id,
                ]);
                AuditLog::record($this->manager(), 'branch.rate_changed', $branch, ['from' => $old, 'to' => $new]);
            }
        });

        return back()->with('status', $new !== $old
            ? 'Ödül oranı güncellendi. Yeni oran bundan sonraki işlemlerde geçerli; geçmiş işlemler değişmez.'
            : 'Kullanım koşulları kaydedildi.');
    }

    // ------------------------------------------------------------ Şube bilgileri + saatler

    public function info(Request $request): View
    {
        $branch = $this->branch($request)->load(['hours', 'photos' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'), 'city', 'district']);
        $hours = collect(range(1, 7))->mapWithKeys(fn ($d) => [$d => $branch->hours->firstWhere('weekday', $d)]);

        return view('panel.settings.info', [
            'branch' => $branch,
            'hours' => $hours,
            'maxPhotos' => self::MAX_PHOTOS,
        ]);
    }

    public function updateInfo(Request $request): RedirectResponse
    {
        $branch = $this->branch($request);
        $request->merge([
            'latitude' => $request->filled('latitude') ? str_replace(',', '.', (string) $request->input('latitude')) : null,
            'longitude' => $request->filled('longitude') ? str_replace(',', '.', (string) $request->input('longitude')) : null,
            'phone' => $request->filled('phone') ? preg_replace('/\s+/', '', (string) $request->input('phone')) : null,
        ]);

        $data = $request->validate([
            'address' => ['required', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+]{10,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:35,43', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:25,45', 'required_with:latitude'],
            'hours' => ['array'],
            'hours.*.closed' => ['nullable', 'boolean'],
            'hours.*.opens' => ['nullable', 'date_format:H:i'],
            'hours.*.closes' => ['nullable', 'date_format:H:i'],
        ], [
            'latitude.between' => 'Enlem Türkiye sınırları içinde olmalıdır (35–43).',
            'longitude.between' => 'Boylam Türkiye sınırları içinde olmalıdır (25–45).',
            'phone.regex' => 'Telefonu yalnızca rakamlarla yazın (örn. 02161234567).',
        ], ['address' => 'adres', 'phone' => 'telefon', 'email' => 'e-posta', 'description' => 'açıklama', 'latitude' => 'enlem', 'longitude' => 'boylam']);

        // Açık günlerde açılış ve kapanış saati zorunlu
        $errors = [];
        foreach (range(1, 7) as $d) {
            $h = $data['hours'][$d] ?? [];
            if (empty($h['closed']) && (empty($h['opens']) xor empty($h['closes']))) {
                $errors["hours.$d.opens"] = \App\Support\Format::DAYS[$d].' için açılış ve kapanış saatinin ikisini de yazın veya "Kapalı" işaretleyin.';
            }
        }
        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }

        DB::transaction(function () use ($branch, $data) {
            $branch->update([
                'address' => $data['address'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'description' => $data['description'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);

            foreach (range(1, 7) as $d) {
                $h = $data['hours'][$d] ?? [];
                $closed = ! empty($h['closed']);
                if (! $closed && empty($h['opens'])) {
                    // Saat girilmemiş gün: kayıt silinir (sitede "belirtilmemiş" görünür)
                    BranchHour::query()->where('branch_id', $branch->id)->where('weekday', $d)->delete();

                    continue;
                }
                BranchHour::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'weekday' => $d],
                    ['is_closed' => $closed, 'opens_at' => $closed ? null : $h['opens'], 'closes_at' => $closed ? null : $h['closes']]
                );
            }

            AuditLog::record($this->manager(), 'branch.info_updated', $branch);
        });

        return back()->with('status', 'Şube bilgileri kaydedildi.');
    }

    // ------------------------------------------------------------ Fotoğraflar

    public function uploadPhotos(Request $request): RedirectResponse
    {
        $branch = $this->branch($request);
        $current = BranchPhoto::query()->where('branch_id', $branch->id)->count();
        $room = self::MAX_PHOTOS - $current;

        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.max(1, $room)],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ], [
            'photos.required' => 'Yüklenecek fotoğrafı seçin.',
            'photos.max' => 'En fazla '.self::MAX_PHOTOS.' fotoğraf olabilir; şu an '.max(0, $room).' fotoğraf daha ekleyebilirsiniz.',
            'photos.*.image' => 'Yalnızca fotoğraf dosyası yükleyin.',
            'photos.*.mimes' => 'Fotoğraf JPG, PNG veya WEBP olmalıdır.',
            'photos.*.max' => 'Her fotoğraf en fazla 6 MB olabilir.',
        ]);

        if ($room <= 0) {
            return back()->with('error', 'En fazla '.self::MAX_PHOTOS.' fotoğraf olabilir. Yenisini eklemek için önce birini silin.');
        }

        $order = (int) BranchPhoto::query()->where('branch_id', $branch->id)->max('sort_order');
        foreach ($request->file('photos') as $file) {
            $path = ImageStore::store($file, 'subeler/'.$branch->id);
            BranchPhoto::query()->create(['branch_id' => $branch->id, 'path' => $path, 'sort_order' => ++$order]);
        }
        AuditLog::record($this->manager(), 'branch.photos_uploaded', $branch, ['count' => count($request->file('photos'))]);

        return back()->with('status', 'Fotoğraflar yüklendi.');
    }

    public function deletePhoto(Request $request, int $photo): RedirectResponse
    {
        $branch = $this->branch($request);
        $p = BranchPhoto::query()->where('branch_id', $branch->id)->findOrFail($photo);
        ImageStore::delete($p->path);
        $p->delete();
        AuditLog::record($this->manager(), 'branch.photo_deleted', $branch, ['photo_id' => $photo]);

        return back()->with('status', 'Fotoğraf silindi.');
    }

    /** Seçilen fotoğrafı kapak (ilk sıra) yapar. */
    public function coverPhoto(Request $request, int $photo): RedirectResponse
    {
        $branch = $this->branch($request);
        $p = BranchPhoto::query()->where('branch_id', $branch->id)->findOrFail($photo);
        $min = (int) BranchPhoto::query()->where('branch_id', $branch->id)->min('sort_order');
        $p->update(['sort_order' => $min - 1]);

        return back()->with('status', 'Kapak fotoğrafı değiştirildi.');
    }
}
