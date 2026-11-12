/**
 * Kumbare mobil API (/api/v1) istemcisi.
 * Adres EXPO_PUBLIC_API_URL ile değiştirilebilir (yerel geliştirme için .env); varsayılan canlı API.
 * Hata mesajları sunucunun Türkçe `message` alanından gelir.
 */
import { getToken } from './token-store';

export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'https://kumbareapi.trthabercanli.com/api/v1').replace(/\/$/, '');

export class ApiError extends Error {
  status: number;
  code?: string;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, code?: string, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.code = code;
    this.errors = errors;
  }

  /** Alan bazında ilk hata mesajı (form alanlarının altında göstermek için). */
  field(name: string): string | undefined {
    return this.errors?.[name]?.[0];
  }
}

/** 401 gelince oturumu kapatmak için kimlik sağlayıcı burayı doldurur. */
let onUnauthorized: (() => void) | null = null;
export function setUnauthorizedHandler(fn: (() => void) | null) {
  onUnauthorized = fn;
}

type Options = { method?: string; body?: unknown; auth?: boolean; signal?: AbortSignal };

export async function api<T = any>(path: string, { method = 'GET', body, auth = true, signal }: Options = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (auth) {
    const token = await getToken();
    if (token) headers.Authorization = `Bearer ${token}`;
  }

  let res: Response;
  try {
    res = await fetch(API_URL + path, { method, headers, body: body !== undefined ? JSON.stringify(body) : undefined, signal });
  } catch (e: any) {
    if (e?.name === 'AbortError') throw e;
    throw new ApiError('Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edin.', 0, 'network');
  }

  let data: any = null;
  const text = await res.text();
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = null;
    }
  }

  if (!res.ok) {
    if (res.status === 401 && auth) onUnauthorized?.();
    const message =
      data?.message ||
      (res.status === 429 ? 'Çok fazla deneme yapıldı. Biraz sonra tekrar deneyin.' : 'Bir hata oluştu. Lütfen tekrar deneyin.');
    throw new ApiError(message, res.status, data?.code, data?.errors);
  }

  return data as T;
}
