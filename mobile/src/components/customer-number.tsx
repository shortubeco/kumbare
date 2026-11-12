// Müşteri numarası kartı: ödemede personele söylenir, kopyalanabilir.
import * as Clipboard from 'expo-clipboard';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { colors, font, radius, space } from '@/theme';

export function CustomerNumber({ number, compact = false }: { number: number | undefined; compact?: boolean }) {
  const [copied, setCopied] = useState(false);

  async function copy() {
    if (!number) return;
    await Clipboard.setStringAsync(String(number));
    setCopied(true);
    setTimeout(() => setCopied(false), 1800);
  }

  return (
    <View style={[styles.card, compact && { paddingVertical: space.lg }]}>
      <Text style={styles.label}>MÜŞTERİ NUMARAM</Text>
      <Text style={[styles.no, compact && { fontSize: 40 }]} selectable accessibilityLabel={`Müşteri numaram ${number ?? ''}`}>
        {number ?? '—'}
      </Text>
      <Text style={styles.help}>Ödeme yaparken bu numarayı söyleyin.</Text>
      <Pressable onPress={copy} style={({ pressed }) => [styles.copy, pressed && { opacity: 0.8 }]} accessibilityRole="button">
        <Text style={styles.copyText}>{copied ? 'Kopyalandı ✓' : 'Numarayı kopyala'}</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.ink,
    borderRadius: radius.lg,
    paddingVertical: space.xl,
    paddingHorizontal: space.lg,
    alignItems: 'center',
    gap: space.sm,
  },
  label: { color: colors.gold, fontSize: font.xs, fontWeight: '800', letterSpacing: 1.5 },
  no: { color: colors.white, fontSize: 56, fontWeight: '800', fontVariant: ['tabular-nums'], letterSpacing: 2 },
  help: { color: '#c9d8d5', fontSize: font.sm },
  copy: {
    marginTop: space.xs,
    borderWidth: 1,
    borderColor: colors.gold,
    borderRadius: radius.pill,
    paddingVertical: 8,
    paddingHorizontal: space.lg,
  },
  copyText: { color: colors.gold, fontWeight: '700', fontSize: font.sm },
});
