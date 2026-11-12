import { focusManager, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { AppState, type ColorValue, Platform } from 'react-native';
import { Loading } from '@/components/ui';
import { ApiError } from '@/lib/api';
import { AuthProvider, useAuth } from '@/providers/auth';
import { WebBack } from '@/components/web-back';
import { colors } from '@/theme';

function RootStack() {
  const { status } = useAuth();
  if (status === 'loading') return <Loading />;
  const signedIn = status === 'signedIn';

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: colors.ink },
        headerTintColor: colors.white,
        headerTitleStyle: { fontWeight: '700' },
        contentStyle: { backgroundColor: colors.paper },
        headerBackButtonDisplayMode: 'minimal',
        ...(Platform.OS === 'web'
          ? {
              headerLeft: (p: { canGoBack?: boolean; tintColor?: ColorValue }) => (
                <WebBack canGoBack={p.canGoBack} tintColor={p.tintColor as string | undefined} fallback="/ana-sayfa" />
              ),
            }
          : {}),
      }}
    >
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Protected guard={!signedIn}>
        <Stack.Screen name="(auth)" options={{ headerShown: false }} />
      </Stack.Protected>
      <Stack.Protected guard={signedIn}>
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="hosgeldin" options={{ headerShown: false, gestureEnabled: false }} />
        <Stack.Screen name="bildirimler" options={{ title: 'Bildirimler' }} />
        <Stack.Screen name="kampanyalar" options={{ title: 'Kampanyalar' }} />
        <Stack.Screen name="kumbara/[id]" options={{ title: 'Kumbara' }} />
        <Stack.Screen name="sube/[id]" options={{ title: 'Restoran' }} />
        <Stack.Screen name="favoriler" options={{ title: 'Favorilerim' }} />
        <Stack.Screen name="profil-duzenle" options={{ title: 'Bilgilerimi düzenle' }} />
        <Stack.Screen name="sifre-degistir" options={{ title: 'Şifre değiştir' }} />
        <Stack.Screen name="degerlendirmelerim" options={{ title: 'Değerlendirmelerim' }} />
        <Stack.Screen name="hesabi-sil" options={{ title: 'Hesabımı sil' }} />
      </Stack.Protected>
    </Stack>
  );
}

// Telefonda uygulama ön plana gelince "odak" sayılır; onay istekleri o anda yeniden çekilir.
function useAppFocus() {
  useEffect(() => {
    if (Platform.OS === 'web') return;
    const sub = AppState.addEventListener('change', (s) => focusManager.setFocused(s === 'active'));
    return () => sub.remove();
  }, []);
}

export default function RootLayout() {
  useAppFocus();
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            refetchOnWindowFocus: false,
            retry: (count, error) => !(error instanceof ApiError && error.status >= 400 && error.status < 500) && count < 2,
          },
        },
      }),
  );

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <StatusBar style="light" />
        <RootStack />
      </AuthProvider>
    </QueryClientProvider>
  );
}
