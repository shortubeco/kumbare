// Ana sayfa: onay uyarısı, müşteri numarası ve restoran blokları (ilçemde popüler, öne çıkanlar, kampanyalı, en yüksek ödül, yeni).
import { router } from 'expo-router';
import { FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { BranchTile } from '@/components/branch-card';
import { CustomerNumber } from '@/components/customer-number';
import { InstallHint } from '@/components/install-hint';
import { Card } from '@/components/ui';
import { useApprovals, useHome } from '@/lib/queries';
import type { BranchCard } from '@/lib/types';
import { useMe } from '@/lib/use-me';
import { colors, font, radius, space } from '@/theme';

function Block({ title, items, more }: { title: string; items: BranchCard[] | undefined; more?: () => void }) {
  if (!items?.length) return null;
  return (
    <View style={{ gap: space.sm }}>
      <View style={styles.blockHead}>
        <Text style={styles.blockTitle}>{title}</Text>
        {more ? (
          <Pressable onPress={more} hitSlop={8} accessibilityRole="button">
            <Text style={styles.more}>Tümü ›</Text>
          </Pressable>
        ) : null}
      </View>
      <FlatList
        horizontal
        data={items}
        keyExtractor={(b) => String(b.id)}
        renderItem={({ item }) => <BranchTile b={item} />}
        contentContainerStyle={{ gap: space.sm, paddingRight: space.lg }}
        showsHorizontalScrollIndicator={false}
      />
    </View>
  );
}

export default function HomeScreen() {
  const me = useMe();
  const c = me.data;
  const approvals = useApprovals();
  const pending = approvals.data?.length ?? 0;
  const home = useHome(c?.district?.id);
  const h = home.data;

  return (
    <ScrollView
      style={{ backgroundColor: colors.paper }}
      contentContainerStyle={styles.wrap}
      refreshControl={
        <RefreshControl
          refreshing={me.isRefetching || approvals.isRefetching || home.isRefetching}
          onRefresh={() => {
            void me.refetch();
            void approvals.refetch();
            void home.refetch();
          }}
        />
      }
    >
      <Text style={styles.hello}>Merhaba{c ? `, ${c.first_name}` : ''}</Text>

      {pending ? (
        <Pressable
          onPress={() => router.push('/bildirimler')}
          style={({ pressed }) => [styles.alert, pressed && { opacity: 0.85 }]}
          accessibilityRole="button"
        >
          <Text style={styles.alertTitle}>Onayınızı bekleyen istek var ({pending})</Text>
          <Text style={styles.alertText}>Bir restoran işlem için onayınızı bekliyor. Görmek için dokunun.</Text>
        </Pressable>
      ) : null}

      <InstallHint />

      <CustomerNumber number={c?.customer_no} compact />

      <Block
        title={c?.district ? `${c.district.name} bölgesinde popüler` : 'Bölgenizde popüler'}
        items={h?.nearby}
        more={() => router.navigate('/kesfet')}
      />
      <Block title="Öne çıkanlar" items={h?.sponsored} />
      <Block title="Kampanyası olanlar" items={h?.with_campaign} more={() => router.push('/kampanyalar')} />
      <Block title="En yüksek ödül oranı" items={h?.top_rate} />
      <Block title="Kumbare'ye yeni katılanlar" items={h?.newest} />
      {home.isError ? <Text style={styles.muted}>Restoranlar yüklenemedi. Aşağı çekerek yenileyin.</Text> : null}

      <Card>
        <Text style={styles.cardTitle}>Nasıl çalışır?</Text>
        <View style={{ gap: 6 }}>
          <Text style={styles.text}>1. Kumbare&apos;li bir restoranda ödemede numaranızı söyleyin.</Text>
          <Text style={styles.text}>2. Harcamanızın restoranın belirlediği oranı kadar ödül, o restoranın kumbarasına eklenir.</Text>
          <Text style={styles.text}>3. Ödülünüzü aynı restoranda kullanırken uygulamadan onay verirsiniz.</Text>
        </View>
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.lg, width: '100%', maxWidth: 640, alignSelf: 'center' },
  alert: { backgroundColor: colors.gold, borderRadius: radius.lg, padding: space.lg, gap: 4 },
  alertTitle: { fontSize: font.md, fontWeight: '800', color: colors.ink },
  alertText: { fontSize: font.sm, color: colors.ink, lineHeight: 20 },
  hello: { fontSize: font.xl, fontWeight: '800', color: colors.ink },
  blockHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' },
  blockTitle: { fontSize: font.lg, fontWeight: '700', color: colors.ink },
  more: { fontSize: font.sm, fontWeight: '700', color: colors.ink2 },
  cardTitle: { fontSize: font.lg, fontWeight: '700', color: colors.ink },
  text: { fontSize: font.md, color: colors.gray, lineHeight: 22 },
  muted: { fontSize: font.sm, color: colors.gray },
});
