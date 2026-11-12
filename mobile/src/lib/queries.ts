/**
 * Sunucudan çekilen listeler. Onay istekleri iş planı gereği yalnızca uygulama açılışında
 * (ön plana gelişte de) ve "Yenile" düğmesinde çekilir; push bildirim yok.
 */
import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { ApprovalRequest, BranchCard, BranchDetail, Campaign, Lookup, Paginated, Review, Transaction, Wallet } from '@/lib/types';

export const keys = {
  approvals: ['approvals'] as const,
  wallets: ['wallets'] as const,
  wallet: (id: number) => ['wallet', id] as const,
  transactions: (id: number) => ['wallet', id, 'transactions'] as const,
};

export function useApprovals() {
  return useQuery({
    queryKey: keys.approvals,
    queryFn: () => api<{ data: ApprovalRequest[] }>('/approvals').then((r) => r.data),
    staleTime: Infinity,
    refetchOnWindowFocus: 'always',
  });
}

export function useWallets() {
  return useQuery({
    queryKey: keys.wallets,
    queryFn: () => api<{ data: Wallet[] }>('/wallets').then((r) => r.data),
  });
}

export function useWallet(id: number) {
  return useQuery({
    queryKey: keys.wallet(id),
    queryFn: () => api<{ data: Wallet }>(`/wallets/${id}`).then((r) => r.data),
  });
}

export function useTransactions(id: number) {
  return useInfiniteQuery({
    queryKey: keys.transactions(id),
    queryFn: ({ pageParam }) => api<Paginated<Transaction>>(`/wallets/${id}/transactions?page=${pageParam}`),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  });
}

// ---- Keşfet / şube / kampanya ----

export type BranchFilters = {
  q?: string;
  city?: number | null;
  district?: number | null;
  category?: number | null;
  sort?: string;
  has_campaign?: boolean;
  min_rate?: number;
};

function toQuery(f: Record<string, unknown>): string {
  const p = Object.entries(f)
    .filter(([, v]) => v !== undefined && v !== null && v !== '' && v !== false)
    .map(([k, v]) => `${k}=${encodeURIComponent(v === true ? 1 : String(v))}`);
  return p.length ? `?${p.join('&')}` : '';
}

export function useHome(district: number | undefined) {
  return useQuery({
    queryKey: ['home', district ?? null],
    queryFn: () =>
      api<{ data: Record<'sponsored' | 'nearby' | 'top_rate' | 'with_campaign' | 'newest', BranchCard[]> }>(
        `/home${toQuery({ district })}`,
      ).then((r) => r.data),
    staleTime: 5 * 60_000,
  });
}

export function useBranches(filters: BranchFilters) {
  return useInfiniteQuery({
    queryKey: ['branches', filters],
    queryFn: ({ pageParam }) => api<Paginated<BranchCard>>(`/branches${toQuery({ ...filters, page: pageParam })}`),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
    staleTime: 60_000,
  });
}

export function useBranch(id: number) {
  return useQuery({
    queryKey: ['branch', id],
    queryFn: () => api<{ data: BranchDetail }>(`/branches/${id}`).then((r) => r.data),
  });
}

export function useBranchReviews(id: number, sort: string) {
  return useInfiniteQuery({
    queryKey: ['branch', id, 'reviews', sort],
    queryFn: ({ pageParam }) => api<Paginated<Review>>(`/branches/${id}/reviews${toQuery({ sort, page: pageParam })}`),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  });
}

/** Kayıtlı ilçedeki kampanyalar: açılışta ve Yenile düğmesinde çekilir. */
export function useCampaigns() {
  return useQuery({
    queryKey: ['campaigns'],
    queryFn: () => api<{ district: { id: number; name: string | null; city: string | null }; data: Campaign[] }>('/campaigns'),
    staleTime: Infinity,
  });
}

export function useFavorites() {
  return useQuery({
    queryKey: ['favorites'],
    queryFn: () => api<{ data: BranchCard[] }>('/favorites').then((r) => r.data),
  });
}

export function useCategories() {
  return useQuery({
    queryKey: ['categories'],
    queryFn: () => api<{ data: Lookup[] }>('/categories', { auth: false }).then((r) => r.data),
    staleTime: Infinity,
  });
}

export function useCities() {
  return useQuery({
    queryKey: ['cities'],
    queryFn: () => api<{ data: Lookup[] }>('/cities', { auth: false }).then((r) => r.data),
    staleTime: Infinity,
  });
}

export function useDistricts(cityId: number | null | undefined) {
  return useQuery({
    queryKey: ['districts', cityId ?? null],
    queryFn: () => api<{ data: Lookup[] }>(`/cities/${cityId}/districts`, { auth: false }).then((r) => r.data),
    enabled: !!cityId,
    staleTime: Infinity,
  });
}
