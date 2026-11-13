/**
 * Android uygulaması (siteden indirilen APK) için "yeni sürüm var" kontrolü.
 * Sunucudaki {SITE}/indir/android.json dosyası en son APK'yı tanımlar; içindeki version_code
 * uygulamanın kendi versionCode'undan büyükse kullanıcıya indirme bağlantısı gösterilir.
 * Yeni APK yayımlanırken: app.json'da version + android.versionCode artırılır, APK derlenir,
 * kumbare.apk ve android.json sunucuda güncellenir.
 */
import { useQuery } from '@tanstack/react-query';
import Constants from 'expo-constants';
import { Platform } from 'react-native';
import { SITE_URL } from '@/lib/site';

export type AndroidRelease = {
  version_code: number;
  version_name: string;
  url: string;
  notes?: string | null;
};

/** Bu kurulumun sürüm numarası (app.json → android.versionCode). */
export const CURRENT_VERSION_CODE = Number(Constants.expoConfig?.android?.versionCode ?? 0);
export const CURRENT_VERSION_NAME = Constants.expoConfig?.version ?? '1.0.0';

async function fetchRelease(): Promise<AndroidRelease | null> {
  const res = await fetch(`${SITE_URL}/indir/android.json?t=${Date.now()}`, { headers: { Accept: 'application/json' } });
  if (!res.ok) return null;
  const data = (await res.json()) as Partial<AndroidRelease>;
  if (typeof data.version_code !== 'number' || typeof data.url !== 'string') return null;
  return { version_code: data.version_code, version_name: String(data.version_name ?? ''), url: data.url, notes: data.notes ?? null };
}

/** Yalnızca Android uygulamasında çalışır; yeni sürüm yoksa ya da sorgu başarısızsa null döner. */
export function useAndroidUpdate() {
  const q = useQuery({
    queryKey: ['android-update'],
    queryFn: fetchRelease,
    enabled: Platform.OS === 'android',
    staleTime: 6 * 60 * 60 * 1000,
    retry: false,
  });
  const r = q.data;
  return r && r.version_code > CURRENT_VERSION_CODE ? r : null;
}
