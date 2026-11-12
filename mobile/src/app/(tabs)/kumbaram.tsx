// Kumbaram: her restoranda (şubede) ayrı biriken ödül bakiyeleri.
import { router } from 'expo-router';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { EmptyState, ErrorState } from '@/components/state-views';
import { Loading } from '@/components/ui';
import { money, rate } from '@/lib/format';
import { useWallets } from '@/lib/queries';
import type { Wallet } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';

const cents = (v: string) => Math.round(Number(v) * 100);

function WalletCard({ w }: { w: Wallet }) {
  const empty = cents(w.balance) === 0;
  return (
    <Pressable
      onPress={() => router.push({ pathname: '/kumbara/[id]', params: { id: String(w.id) } })}
      style={({ pressed }) => [styles.card, pressed && { opacity: 0.85 }]}
      accessibilityRole="button"
    >
      <View style={{ flex: 1, gap: 4 }}>
        <Text style={styles.name} numberOfLines={2}>
          {w.branch.display_name}
        </Text>
        <Text style={styles.meta}>
          {Number(w.branch.reward_rate) > 0 ? `${rate(w.branch.reward_rate)} ödül oranı` : 'Şu an ödül vermiyor'}
        </Text>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Text style={[styles.balance, empty && { color: colors.gray }]}>{money(w.balance)}</Text>
        <Text style={styles.chev}>Ayrıntı ›</Text>
      </View>
    </Pressable>
  );
}

export default function KumbaramScreen() {
  const q = useWallets();

  if (q.isLoading) return <Loading />;
  if (q.isError && !q.data) return <ErrorState message={q.error.message} onRetry={() => void q.refetch()} />;

  const wallets = q.data ?? [];
  const total = wallets.reduce((s, w) => s + cents(w.balance), 0) / 100;
  const withBalance = wallets.filter((w) => cents(w.balance) > 0).length;

  return (
    <FlatList
      style={{ backgroundColor: colors.paper }}
      contentContainerStyle={styles.wrap}
      data={wallets}
      keyExtractor={(w) => String(w.id)}
      renderItem={({ item }) => <WalletCard w={item} />}
      refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => void q.refetch()} />}
      ListHeaderComponent={
        wallets.length ? (
          <View style={styles.total}>
            <Text style={styles.totalLabel}>TOPLAM ÖDÜL BAKİYEM</Text>
            <Text style={styles.totalValue}>{money(total)}</Text>
            <Text style={styles.totalHelp}>
              Ödül yalnızca kazanıldığı restoranda kullanılır. Bakiyesi olan restoran sayısı: {withBalance}
            </Text>
          </View>
        ) : undefined
      }
      ListEmptyComponent={
        <EmptyState
          title="Henüz kumbaranız yok"
          text="Kumbare'li bir restoranda ödeme yaparken müşteri numaranızı söyleyin; ödülünüz o restoranın kumbarasında birikmeye başlar."
          action={{ title: 'Restoranları keşfet', onPress: () => router.navigate('/kesfet') }}
        />
      }
    />
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.md, width: '100%', maxWidth: 560, alignSelf: 'center', flexGrow: 1 },
  total: { backgroundColor: colors.ink, borderRadius: radius.lg, padding: space.lg, gap: 6, marginBottom: space.xs },
  totalLabel: { color: colors.gold, fontSize: font.xs, fontWeight: '800', letterSpacing: 1.5 },
  totalValue: { color: colors.white, fontSize: font.xxl, fontWeight: '800', fontVariant: ['tabular-nums'] },
  totalHelp: { color: '#c9d8d5', fontSize: font.sm, lineHeight: 19 },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.lg,
  },
  name: { fontSize: font.md, fontWeight: '700', color: colors.ink },
  meta: { fontSize: font.sm, color: colors.gray },
  balance: { fontSize: font.lg, fontWeight: '800', color: colors.ink, fontVariant: ['tabular-nums'] },
  chev: { fontSize: font.xs + 1, color: colors.ink2, marginTop: 2 },
});
