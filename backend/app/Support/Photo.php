<?php

namespace App\Support;

/**
 * Mutfak (kategori) fotoğrafları. Kaynak ve lisans: public/img/KAYNAKLAR.txt (Unsplash Lisansı).
 * Restoranın kendi fotoğrafı değildir; yalnızca mutfağı temsil eder.
 */
final class Photo
{
    public static function category(?string $slug, bool $small = false): ?string
    {
        if (! $slug) {
            return null;
        }
        $rel = 'img/mutfak/'.($small ? 'kucuk/' : '').$slug.'.webp';

        return is_file(public_path($rel)) ? '/'.$rel : null;
    }
}
