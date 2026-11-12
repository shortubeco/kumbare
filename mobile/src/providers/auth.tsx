/**
 * Oturum durumu. Açılışta kayıtlı anahtar okunur; varsa müşteri bilgisi /me ile çekilir.
 * Sunucu 401 dönerse (anahtar silinmiş / hesap pasif) oturum kapanır.
 */
import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { api, ApiError, setUnauthorizedHandler } from '@/lib/api';
import { clearToken, getToken, setToken } from '@/lib/token-store';

export type Customer = {
  id: number;
  customer_no: number;
  first_name: string;
  last_name: string;
  phone: string;
  email: string;
  birth_date: string | null;
  city: { id: number; name: string } | null;
  district: { id: number; name: string } | null;
  display_name_mode: 'initials' | 'full';
  public_name: string;
};

type Status = 'loading' | 'signedOut' | 'signedIn';

type AuthValue = {
  status: Status;
  customer: Customer | null;
  signIn: (token: string, customer: Customer) => Promise<void>;
  signOut: () => Promise<void>;
  setCustomer: (c: Customer) => void;
};

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<Status>('loading');
  const [customer, setCustomer] = useState<Customer | null>(null);
  const queryClient = useQueryClient();

  const reset = useCallback(async () => {
    await clearToken();
    queryClient.clear();
    setCustomer(null);
    setStatus('signedOut');
  }, [queryClient]);

  useEffect(() => {
    setUnauthorizedHandler(() => {
      void reset();
    });
    return () => setUnauthorizedHandler(null);
  }, [reset]);

  useEffect(() => {
    let alive = true;
    (async () => {
      const token = await getToken();
      if (!token) {
        if (alive) setStatus('signedOut');
        return;
      }
      try {
        const res = await api<{ data: Customer }>('/me');
        if (!alive) return;
        setCustomer(res.data);
        setStatus('signedIn');
      } catch (e) {
        if (!alive) return;
        // 401 → işleyici zaten oturumu kapattı. Bağlantı hatasında oturum açık kalır; bilgiler sonra çekilir.
        if (!(e instanceof ApiError && e.status === 401)) setStatus('signedIn');
      }
    })();
    return () => {
      alive = false;
    };
  }, []);

  const signIn = useCallback(async (token: string, c: Customer) => {
    await setToken(token);
    setCustomer(c);
    setStatus('signedIn');
  }, []);

  const signOut = useCallback(async () => {
    try {
      await api('/auth/logout', { method: 'POST' });
    } catch {
      // sunucuya ulaşılamasa da telefondaki oturum kapatılır
    }
    await reset();
  }, [reset]);

  const value = useMemo(() => ({ status, customer, signIn, signOut, setCustomer }), [status, customer, signIn, signOut]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth, AuthProvider içinde kullanılmalı');
  return ctx;
}
