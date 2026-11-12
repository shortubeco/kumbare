// Henüz yazılmamış ekranlar için geçici içerik.
import { StyleSheet, Text, View } from 'react-native';
import { colors, font, space } from '@/theme';

export function ComingSoon({ title, text }: { title: string; text: string }) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.text}>{text}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: space.xl, gap: space.sm, backgroundColor: colors.paper },
  title: { fontSize: font.lg, fontWeight: '700', color: colors.ink, textAlign: 'center' },
  text: { fontSize: font.md, color: colors.gray, textAlign: 'center', lineHeight: 22 },
});
