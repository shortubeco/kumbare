// Web'de (iPhone "Ana ekrana ekle") üst çubuk geri düğmesi: geçmiş yoksa ana sayfaya döner.
import Ionicons from '@expo/vector-icons/Ionicons';
import { Pressable } from 'react-native';
import { goBack } from '@/lib/nav';

export function WebBack({
  canGoBack,
  tintColor,
  fallback = '/ana-sayfa',
}: {
  canGoBack?: boolean;
  tintColor?: string;
  fallback?: '/ana-sayfa' | '/giris';
}) {
  return (
    <Pressable
      onPress={() => goBack(fallback)}
      hitSlop={10}
      style={{ marginLeft: 12, marginRight: 8 }}
      accessibilityRole="button"
      accessibilityLabel={canGoBack ? 'Geri' : 'Ana sayfa'}
    >
      <Ionicons name={canGoBack ? 'arrow-back' : 'home-outline'} size={22} color={tintColor ?? '#fff'} />
    </Pressable>
  );
}
