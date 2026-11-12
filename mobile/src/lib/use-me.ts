// Giriş yapan müşterinin bilgisi. Oturum açılırken gelen bilgi başlangıç değeri olarak kullanılır.
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { type Customer, useAuth } from '@/providers/auth';

export function useMe() {
  const { customer, setCustomer } = useAuth();
  return useQuery({
    queryKey: ['me'],
    queryFn: async () => {
      const res = await api<{ data: Customer }>('/me');
      setCustomer(res.data);
      return res.data;
    },
    initialData: customer ?? undefined,
    staleTime: 60_000,
  });
}
