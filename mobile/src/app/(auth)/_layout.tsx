import { Stack } from 'expo-router';
import { type ColorValue, Platform } from 'react-native';
import { WebBack } from '@/components/web-back';
import { colors } from '@/theme';

export const unstable_settings = { initialRouteName: 'giris' };

export default function AuthLayout() {
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
                <WebBack canGoBack={p.canGoBack} tintColor={p.tintColor as string | undefined} fallback="/giris" />
              ),
            }
          : {}),
      }}
    >
      <Stack.Screen name="giris" options={{ headerShown: false }} />
      <Stack.Screen name="kayit" options={{ title: 'Üye ol' }} />
      <Stack.Screen name="sifre-sifirla" options={{ title: 'Şifremi unuttum' }} />
    </Stack>
  );
}
