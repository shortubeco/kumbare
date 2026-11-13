// Android uygulamasında yeni APK yayımlanmışsa "Yeni sürüm var" kutusu (ana sayfa ve profil).
// İndirme bağlantısı tarayıcıda açılır; Android indirilen APK'yı mevcut uygulamanın üzerine kurar (veriler korunur).
import { Linking, StyleSheet, Text, View } from 'react-native';
import { Button } from '@/components/ui';
import { CURRENT_VERSION_NAME, useAndroidUpdate } from '@/lib/app-update';
import { colors, font, radius, space } from '@/theme';

export function UpdateHint() {
  const r = useAndroidUpdate();
  if (!r) return null;

  return (
    <View style={styles.box} accessibilityRole="summary">
      <Text style={styles.title}>Yeni sürüm var: {r.version_name}</Text>
      <Text style={styles.text}>
        Kullandığınız sürüm {CURRENT_VERSION_NAME}. Yeni sürümü indirip açın, ardından &quot;Güncelle&quot;ye dokunun; bilgileriniz
        korunur.
      </Text>
      {r.notes ? <Text style={styles.notes}>{r.notes}</Text> : null}
      <Button title="Yeni sürümü indir" onPress={() => void Linking.openURL(r.url)} />
    </View>
  );
}

const styles = StyleSheet.create({
  box: { gap: space.sm, backgroundColor: colors.goldSoft, borderRadius: radius.md, padding: space.md, borderWidth: 1, borderColor: colors.gold },
  title: { fontSize: font.md, fontWeight: '800', color: colors.ink },
  text: { fontSize: font.sm, color: colors.ink, lineHeight: 20 },
  notes: { fontSize: font.sm, color: colors.gray, lineHeight: 20 },
});
