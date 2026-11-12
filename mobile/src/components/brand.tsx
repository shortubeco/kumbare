// Kumbare yazı logosu + kumbara yuvası (sitedeki "adisyon + kumbara" çizgisi).
import { StyleSheet, Text, View } from 'react-native';
import { colors } from '@/theme';

export function Brand({ size = 34, light = false }: { size?: number; light?: boolean }) {
  return (
    <View style={styles.row} accessibilityRole="header" accessibilityLabel="Kumbare">
      <View style={[styles.slot, { width: size * 0.9, height: size * 0.9, borderRadius: size * 0.22 }]}>
        <View style={[styles.coin, { width: size * 0.5, height: size * 0.12, borderRadius: size }]} />
      </View>
      <Text style={[styles.word, { fontSize: size, color: light ? colors.white : colors.ink }]}>kumbare</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  slot: { backgroundColor: colors.gold, alignItems: 'center', justifyContent: 'center' },
  coin: { backgroundColor: colors.ink },
  word: { fontWeight: '800', letterSpacing: -0.5 },
});
