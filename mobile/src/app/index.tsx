// Başlangıç: oturuma göre giriş ekranına veya ana sayfaya yönlendirir.
import { Redirect } from 'expo-router';
import { useAuth } from '@/providers/auth';

export default function Index() {
  const { status } = useAuth();
  return <Redirect href={status === 'signedIn' ? '/ana-sayfa' : '/giris'} />;
}
