// Web sürümü (iPhone "Ana ekrana ekle"): güvenli depo yok; tarayıcının yerel deposu kullanılır.
const KEY = 'kumbare_token';

export async function getToken(): Promise<string | null> {
  try {
    return window.localStorage.getItem(KEY);
  } catch {
    return null;
  }
}

export async function setToken(token: string): Promise<void> {
  try {
    window.localStorage.setItem(KEY, token);
  } catch {
    // özel tarama modunda depo kapalı olabilir; oturum yalnızca bu açılışta sürer
  }
}

export async function clearToken(): Promise<void> {
  try {
    window.localStorage.removeItem(KEY);
  } catch {
    // yoksay
  }
}
