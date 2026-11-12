// Şube ayrıntısı — Bilgiler bölümü: açıklama, adres, iletişim, çalışma saatleri, fotoğraflar, diğer şubeler.
import { Image } from 'expo-image';
import { router } from 'expo-router';
import { Linking, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Card } from '@/components/ui';
import { rate } from '@/lib/format';
import type { BranchDetail } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';

const DAYS = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];

function todayIndex() {
  const d = new Date().getDay(); // 0 = Pazar
  return d === 0 ? 7 : d;
}

function mapsUrl(b: BranchDetail) {
  const lat = b.location.latitude;
  const lng = b.location.longitude;
  const label = encodeURIComponent(b.display_name);
  if (lat && lng) {
    return Platform.OS === 'ios'
      ? `http://maps.apple.com/?ll=${lat},${lng}&q=${label}`
      : `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
  }
  const q = encodeURIComponent([b.address, b.location.district, b.location.city].filter(Boolean).join(', '));
  return `https://www.google.com/maps/search/?api=1&query=${q}`;
}

export function BranchInfo({ b }: { b: BranchDetail }) {
  const today = todayIndex();
  const hours = [...b.hours].sort((x, y) => x.weekday - y.weekday);

  return (
    <View style={{ gap: space.md }}>
      {b.description || b.business_description ? (
        <Card>
          <Text style={styles.h}>Hakkında</Text>
          {b.description ? <Text style={styles.p}>{b.description}</Text> : null}
          {b.business_description && b.business_description !== b.description ? (
            <Text style={styles.p}>{b.business_description}</Text>
          ) : null}
        </Card>
      ) : null}

      {b.photos.length ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: space.sm }}>
          {b.photos.map((uri) => (
            <Image key={uri} source={{ uri }} style={styles.photo} contentFit="cover" />
          ))}
        </ScrollView>
      ) : null}

      <Card>
        <Text style={styles.h}>Adres ve iletişim</Text>
        <Text style={styles.p}>{[b.address, b.location.district, b.location.city].filter(Boolean).join(', ')}</Text>
        <View style={styles.links}>
          <Pressable onPress={() => void Linking.openURL(mapsUrl(b))} accessibilityRole="link" hitSlop={6}>
            <Text style={styles.link}>Haritada aç</Text>
          </Pressable>
          {b.phone ? (
            <Pressable onPress={() => void Linking.openURL(`tel:${b.phone!.replace(/\s/g, '')}`)} accessibilityRole="link" hitSlop={6}>
              <Text style={styles.link}>Ara: {b.phone}</Text>
            </Pressable>
          ) : null}
        </View>
      </Card>

      {hours.length ? (
        <Card>
          <Text style={styles.h}>Çalışma saatleri</Text>
          {hours.map((h) => (
            <View key={h.weekday} style={[styles.hourRow, h.weekday === today && styles.today]}>
              <Text style={[styles.day, h.weekday === today && { fontWeight: '800' }]}>
                {DAYS[h.weekday - 1]}
                {h.weekday === today ? ' (bugün)' : ''}
              </Text>
              <Text style={[styles.time, h.is_closed && { color: colors.error }]}>
                {h.is_closed ? 'Kapalı' : `${h.opens_at ?? '?'} – ${h.closes_at ?? '?'}`}
              </Text>
            </View>
          ))}
        </Card>
      ) : null}

      <Card>
        <Text style={styles.h}>Ödül</Text>
        <Text style={styles.p}>
          {Number(b.reward_rate) > 0
            ? `Harcamanızın ${rate(b.reward_rate)} kadarı bu restoranın kumbarasına ödül olarak eklenir.`
            : 'Bu restoran şu an ödül vermiyor.'}
        </Text>
        {b.reward_terms ? <Text style={styles.small}>{b.reward_terms}</Text> : null}
      </Card>

      {b.other_branches.length ? (
        <Card>
          <Text style={styles.h}>{b.business.name} diğer şubeleri</Text>
          <Text style={styles.small}>Her şubenin kumbarası ayrıdır; ödül yalnızca kazanıldığı şubede kullanılır.</Text>
          {b.other_branches.map((o) => (
            <Pressable
              key={o.id}
              onPress={() => router.push({ pathname: '/sube/[id]', params: { id: String(o.id) } })}
              style={styles.other}
              accessibilityRole="button"
            >
              <Text style={styles.otherName}>
                {o.name}
                {o.district ? ` · ${o.district}` : ''}
              </Text>
              <Text style={styles.otherRate}>{Number(o.reward_rate) > 0 ? rate(o.reward_rate) : 'Ödül yok'} ›</Text>
            </Pressable>
          ))}
        </Card>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  h: { fontSize: font.md, fontWeight: '700', color: colors.ink },
  p: { fontSize: font.md, color: colors.ink, lineHeight: 22 },
  small: { fontSize: font.sm, color: colors.gray, lineHeight: 20 },
  photo: { width: 220, height: 150, borderRadius: radius.md, backgroundColor: colors.line2 },
  links: { flexDirection: 'row', flexWrap: 'wrap', gap: space.lg, marginTop: 2 },
  link: { color: colors.ink2, fontWeight: '700', textDecorationLine: 'underline', fontSize: font.sm },
  hourRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 6, paddingHorizontal: 6, borderRadius: radius.sm },
  today: { backgroundColor: colors.goldSoft },
  day: { fontSize: font.sm, color: colors.ink },
  time: { fontSize: font.sm, color: colors.ink, fontVariant: ['tabular-nums'] },
  other: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 10,
    borderTopWidth: 1,
    borderTopColor: colors.line2,
    gap: space.md,
  },
  otherName: { flex: 1, fontSize: font.sm, color: colors.ink, fontWeight: '600' },
  otherRate: { fontSize: font.sm, color: colors.ink2 },
});
