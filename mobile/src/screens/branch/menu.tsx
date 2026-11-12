// Şube ayrıntısı — Menü bölümü (menü ve fiyatlar işletmeye aittir, tüm şubelerde aynıdır).
import { Image } from 'expo-image';
import { StyleSheet, Text, View } from 'react-native';
import { Card } from '@/components/ui';
import { money } from '@/lib/format';
import type { BranchDetail } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';

export function BranchMenu({ b }: { b: BranchDetail }) {
  if (!b.menu.length) {
    return (
      <Card>
        <Text style={styles.empty}>Bu restoran henüz menüsünü eklemedi.</Text>
      </Card>
    );
  }
  return (
    <View style={{ gap: space.md }}>
      {b.menu.map((cat) => (
        <Card key={cat.id}>
          <Text style={styles.cat}>{cat.name}</Text>
          {cat.items.map((i) => (
            <View key={i.id} style={[styles.item, !i.is_available && { opacity: 0.55 }]}>
              {i.image_url ? <Image source={{ uri: i.image_url }} style={styles.img} contentFit="cover" /> : null}
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={styles.name}>{i.name}</Text>
                {i.description ? <Text style={styles.desc}>{i.description}</Text> : null}
                {!i.is_available ? <Text style={styles.out}>Şu an yok</Text> : null}
              </View>
              <Text style={styles.price}>{money(i.price)}</Text>
            </View>
          ))}
        </Card>
      ))}
      <Text style={styles.note}>Fiyatlar restoran tarafından girilir; kasadaki fiyat geçerlidir.</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  empty: { color: colors.gray, fontSize: font.md, textAlign: 'center' },
  cat: { fontSize: font.lg, fontWeight: '700', color: colors.ink, marginBottom: 2 },
  item: {
    flexDirection: 'row',
    gap: space.md,
    paddingVertical: 10,
    borderTopWidth: 1,
    borderTopColor: colors.line2,
    alignItems: 'flex-start',
  },
  img: { width: 56, height: 56, borderRadius: radius.sm, backgroundColor: colors.line2 },
  name: { fontSize: font.md, fontWeight: '600', color: colors.ink },
  desc: { fontSize: font.sm, color: colors.gray, lineHeight: 19 },
  out: { fontSize: font.xs + 1, color: colors.error, fontWeight: '700' },
  price: { fontSize: font.md, fontWeight: '700', color: colors.ink, fontVariant: ['tabular-nums'] },
  note: { fontSize: font.xs + 1, color: colors.gray, textAlign: 'center' },
});
