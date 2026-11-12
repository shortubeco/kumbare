// Restoran (şube) kartları: listelerde satır, ana sayfa bloklarında dar kart.
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Tabela } from '@/components/tabela';
import type { BranchCard } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';

const open = (id: number) => router.push({ pathname: '/sube/[id]', params: { id: String(id) } });

/** "8.7 / 10 — 245 değerlendirme" → kısa gösterim "8.7 / 10 (245)" */
function ratingShort(b: BranchCard) {
  return b.rating.count > 0 ? `${b.rating.average} / 10 (${b.rating.count})` : 'Değerlendirme yok';
}

function Tags({ b }: { b: BranchCard }) {
  return (
    <View style={styles.tags}>
      <Text style={[styles.tag, Number(b.reward_rate) > 0 ? styles.tagGold : styles.tagMuted]}>{b.reward_label}</Text>
      {b.has_campaign ? <Text style={[styles.tag, styles.tagInk]}>Kampanya</Text> : null}
      {b.is_sponsored ? <Text style={[styles.tag, styles.tagLine]}>Öne çıkan</Text> : null}
    </View>
  );
}

export function BranchRow({ b }: { b: BranchCard }) {
  return (
    <Pressable
      onPress={() => open(b.id)}
      style={({ pressed }) => [styles.row, pressed && { opacity: 0.85 }]}
      accessibilityRole="button"
      accessibilityLabel={`${b.display_name}, ${b.reward_label}, ${b.rating.label}`}
    >
      <Tabela name={b.business.name} categoryId={b.category?.id} photo={b.business.logo_url} size={60} />
      <View style={{ flex: 1, gap: 3 }}>
        <Text style={styles.name} numberOfLines={2}>
          {b.display_name}
        </Text>
        <Text style={styles.meta} numberOfLines={1}>
          {[
            b.category?.name,
            b.location.district,
            b.location.distance_km != null ? `${String(b.location.distance_km).replace('.', ',')} km` : null,
          ]
            .filter(Boolean)
            .join(' · ')}
        </Text>
        <Text style={styles.rating}>{ratingShort(b)}</Text>
        <Tags b={b} />
      </View>
    </Pressable>
  );
}

export function BranchTile({ b }: { b: BranchCard }) {
  return (
    <Pressable
      onPress={() => open(b.id)}
      style={({ pressed }) => [styles.tile, pressed && { opacity: 0.85 }]}
      accessibilityRole="button"
      accessibilityLabel={`${b.display_name}, ${b.reward_label}`}
    >
      <Tabela
        name={b.business.name}
        categoryId={b.category?.id}
        photo={b.business.logo_url}
        size={164}
        style={{ width: '100%', height: 92 }}
      />
      <Text style={styles.name} numberOfLines={2}>
        {b.display_name}
      </Text>
      <Text style={styles.meta} numberOfLines={1}>
        {b.location.district ?? ''}
      </Text>
      <Text style={styles.rating}>{ratingShort(b)}</Text>
      <Tags b={b} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: space.md,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
  },
  tile: {
    width: 188,
    gap: 4,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
  },
  name: { fontSize: font.md, fontWeight: '700', color: colors.ink },
  meta: { fontSize: font.sm, color: colors.gray },
  rating: { fontSize: font.sm, color: colors.ink, fontWeight: '600' },
  tags: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 2 },
  tag: { fontSize: font.xs, fontWeight: '700', paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.pill, overflow: 'hidden' },
  tagGold: { backgroundColor: colors.goldSoft, color: colors.goldDark },
  tagMuted: { backgroundColor: colors.line2, color: colors.gray },
  tagInk: { backgroundColor: colors.ink, color: colors.gold },
  tagLine: { borderWidth: 1, borderColor: colors.line, color: colors.ink2 },
});
