// iPhone/iPad Safari'de uygulama ana ekrana eklenmemişse nasıl ekleneceğini anlatan kapatılabilir ipucu.
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, font, radius, space } from '@/theme';

const KEY = 'kumbare_install_hint_closed';

function shouldShow(): boolean {
  try {
    const ios = /iPhone|iPad|iPod/.test(navigator.userAgent);
    const standalone =
      (navigator as Navigator & { standalone?: boolean }).standalone === true || window.matchMedia('(display-mode: standalone)').matches;
    return ios && !standalone && window.localStorage.getItem(KEY) !== '1';
  } catch {
    return false;
  }
}

export function InstallHint() {
  const [show, setShow] = useState(shouldShow);
  if (!show) return null;

  function close() {
    try {
      window.localStorage.setItem(KEY, '1');
    } catch {
      // yoksay
    }
    setShow(false);
  }

  return (
    <View style={styles.box} accessibilityRole="summary">
      <View style={{ flex: 1, gap: 4 }}>
        <Text style={styles.title}>Kumbare&apos;yi ana ekranınıza ekleyin</Text>
        <Text style={styles.text}>
          Safari&apos;nin alt çubuğundaki Paylaş düğmesine (yukarı ok olan kare) dokunun, ardından &quot;Ana Ekrana Ekle&quot;yi seçin.
          Kumbare uygulama gibi açılır.
        </Text>
      </View>
      <Pressable onPress={close} hitSlop={10} accessibilityRole="button" accessibilityLabel="İpucunu kapat">
        <Text style={styles.close}>✕</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  box: {
    flexDirection: 'row',
    gap: space.md,
    backgroundColor: colors.infoSoft,
    borderRadius: radius.md,
    padding: space.md,
    alignItems: 'flex-start',
  },
  title: { fontSize: font.sm, fontWeight: '800', color: colors.info },
  text: { fontSize: font.sm, color: colors.ink, lineHeight: 20 },
  close: { fontSize: font.md, color: colors.gray, paddingHorizontal: 4 },
});
