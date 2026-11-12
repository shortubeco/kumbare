// Sunucunun doğrulama hatalarını alan → ilk mesaj sözlüğüne çevirir.
import { ApiError } from '@/lib/api';

export function fieldErrors(e: unknown): Record<string, string> {
  const out: Record<string, string> = {};
  if (e instanceof ApiError && e.errors) {
    for (const [k, v] of Object.entries(e.errors)) out[k] = v[0];
  }
  return out;
}

export function errorMessage(e: unknown, fallback = 'İşlem tamamlanamadı.'): string {
  if (e instanceof ApiError) return e.errors ? 'Lütfen işaretli alanları kontrol edin.' : e.message;
  return fallback;
}
