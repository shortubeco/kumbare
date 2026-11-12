<?php

namespace App\Support;

/** Panel ekranlarındaki durum adları ve rozet renkleri. */
final class Labels
{
    private const MAP = [
        'business' => [
            'pending' => ['Başvuru bekliyor', 'warn'],
            'approved' => ['Aktif', 'ok'],
            'rejected' => ['Reddedildi', 'bad'],
            'suspended' => ['Askıya alındı', 'bad'],
            'closed' => ['Kapatıldı', ''],
        ],
        'branch' => [
            'active' => ['Aktif', 'ok'],
            'passive' => ['Pasif', 'warn'],
            'deleted' => ['Silindi', ''],
        ],
        'customer' => [
            'active' => ['Aktif', 'ok'],
            'passive' => ['Pasif', 'warn'],
        ],
        'payment' => [
            'pending' => ['Bekliyor', 'warn'],
            'approved' => ['Onaylandı', 'ok'],
            'rejected' => ['Reddedildi', 'bad'],
        ],
        'subscription' => [
            'active' => ['Aktif', 'ok'],
            'cancelled' => ['İptal edildi', 'bad'],
            'expired' => ['Süresi doldu', ''],
        ],
        'sponsorship' => [
            'pending' => ['Başlayacak', 'info'],
            'active' => ['Yayında', 'ok'],
            'ended' => ['Bitti', ''],
            'cancelled' => ['İptal edildi', 'bad'],
        ],
        'campaign' => [
            'draft' => ['Taslak', ''],
            'active' => ['Yayında', 'ok'],
            'ended' => ['Sona erdi', ''],
        ],
        'review' => [
            'published' => ['Yayında', 'ok'],
            'removed' => ['Kaldırıldı', 'bad'],
        ],
        'purpose' => [
            'subscription' => ['Profesyonel paket', 'pro'],
            'sponsorship' => ['Sponsorluk', 'info'],
        ],
        'cycle' => [
            'monthly' => ['Aylık', ''],
            'yearly' => ['Yıllık', ''],
        ],
        'tx' => [
            'earn' => ['Kazanım', 'ok'],
            'redeem' => ['Kullanım', 'warn'],
            'correction' => ['Düzeltme', 'info'],
        ],
        'approval' => [
            'pending' => ['Müşteri onayı bekliyor', 'warn'],
            'approved' => ['Onaylandı', 'ok'],
            'rejected' => ['Reddedildi', 'bad'],
            'withdrawn' => ['Geri çekildi', ''],
        ],
        'role' => [
            'platform_admin' => ['Platform yöneticisi', 'pro'],
            'business_owner' => ['İşletme yöneticisi', 'info'],
            'branch_manager' => ['Şube yöneticisi', 'info'],
            'staff' => ['Çalışan', ''],
        ],
    ];

    public static function text(string $group, ?string $value): string
    {
        return self::MAP[$group][$value][0] ?? (string) $value;
    }

    public static function badge(string $group, ?string $value): string
    {
        $class = self::MAP[$group][$value][1] ?? '';

        return '<span class="badge '.$class.'">'.e(self::text($group, $value)).'</span>';
    }

    /** "2026-11-01" → "Kasım 2026" */
    public static function month(\DateTimeInterface|string|null $date): string
    {
        if (! $date) {
            return '';
        }
        $d = \Carbon\Carbon::parse($date);
        $months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

        return $months[$d->month - 1].' '.$d->year;
    }

    /** Tarih + saat: "12 Kasım 2026 14:05" */
    public static function dateTime(\DateTimeInterface|string|null $date): string
    {
        if (! $date) {
            return '';
        }
        $d = \Carbon\Carbon::parse($date)->timezone(config('app.timezone'));

        return Format::date($d).' '.$d->format('H:i');
    }

    public static function date(\DateTimeInterface|string|null $date): string
    {
        return $date ? Format::date(\Carbon\Carbon::parse($date)) : '';
    }
}
