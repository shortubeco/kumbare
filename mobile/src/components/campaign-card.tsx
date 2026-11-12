// Kampanya kartı: Kampanyalar ekranında ve şube ayrıntısında kullanılır.
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { date, rate } from '@/lib/format';
import type { Campaign } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';

export function CampaignCard({ c, showBranch = true }: { c: Campaign; showBranch?: boolean }) {
  const body = (
    <>
      {c.image_url ? (
        <Image source={{ uri: c.image_url }} style={styles.image} contentFit="cover" accessibilityIgnoresInvertColors />
      ) : null}
      <View style={styles.body}>
        <View style={styles.head}>
          {c.type === 'rate_override' && c.reward_rate ? (
            <Text style={styles.rate}>{rate(c.reward_rate)} ödül</Text>
          ) : (
            <Text style={styles.kind}>DUYURU</Text>
          )}
          {c.ends_at ? <Text style={styles.until}>Son gün: {date(c.ends_at)}</Text> : null}
        </View>
        <Text style={styles.title}>{c.title}</Text>
        {showBranch && c.branch ? <Text style={styles.branch}>{c.branch.display_name}</Text> : null}
        {c.description ? <Text style={styles.desc}>{c.description}</Text> : null}
        {c.conditions.map((t) => (
          <Text key={t} style={styles.cond}>
            • {t}
          </Text>
        ))}
        {c.terms ? <Text style={styles.terms}>{c.terms}</Text> : null}
        {c.type === 'rate_override' ? (
          <Text style={styles.hint}>Ödemede kasada bu kampanyayı söyleyin; personel ödülünüzü bu oranla hesaplar.</Text>
        ) : null}
      </View>
    </>
  );

  if (showBranch && c.branch) {
    const id = c.branch.id;
    return (
      <Pressable
        onPress={() => router.push({ pathname: '/sube/[id]', params: { id: String(id) } })}
        style={({ pressed }) => [styles.card, pressed && { opacity: 0.88 }]}
        accessibilityRole="button"
      >
        {body}
      </Pressable>
    );
  }
  return <View style={styles.card}>{body}</View>;
}

const styles = StyleSheet.create({
  card: { backgroundColor: colors.white, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, overflow: 'hidden' },
  image: { width: '100%', aspectRatio: 2 },
  body: { padding: space.lg, gap: 6 },
  head: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: space.sm },
  rate: {
    backgroundColor: colors.gold,
    color: colors.ink,
    fontWeight: '800',
    fontSize: font.sm,
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: radius.pill,
    overflow: 'hidden',
  },
  kind: { fontSize: font.xs, fontWeight: '800', letterSpacing: 1.2, color: colors.ink2 },
  until: { fontSize: font.xs + 1, color: colors.gray },
  title: { fontSize: font.lg, fontWeight: '700', color: colors.ink },
  branch: { fontSize: font.sm, fontWeight: '600', color: colors.ink2 },
  desc: { fontSize: font.md, color: colors.ink, lineHeight: 22 },
  cond: { fontSize: font.sm, color: colors.gray },
  terms: { fontSize: font.xs + 1, color: colors.gray, lineHeight: 18 },
  hint: { fontSize: font.xs + 1, color: colors.goldDark, marginTop: 2 },
});
