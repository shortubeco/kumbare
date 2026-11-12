// Kumbara ayrıntısı: bakiye, restoranın ödül koşulları ve işlem geçmişi.
import { Stack, useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { ErrorState } from '@/components/state-views';
import { Loading } from '@/components/ui';
import { dateTime, money, rate } from '@/lib/format';
import { useTransactions, useWallet } from '@/lib/queries';
import type { Transaction } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';

function signed(amount: string) {
  const n = Number(amount);
  return (n > 0 ? '+' : '') + money(amount);
}

function TxRow({ t }: { t: Transaction }) {
  const positive = Number(t.amount) > 0;
  const details: string[] = [];
  if (t.type === 'earn' && t.spend_amount)
    details.push(`Harcama ${money(t.spend_amount)}${t.reward_rate ? ` · ${rate(t.reward_rate)}` : ''}`);
  if (t.campaign) details.push(`Kampanya: ${t.campaign.title}`);
  if (t.type === 'correction' && t.related_transaction_id) details.push(`Düzeltilen işlem no ${t.related_transaction_id}`);
  if (t.note) details.push(t.note);

  return (
    <View style={styles.row}>
      <View style={{ flex: 1, gap: 2 }}>
        <Text style={styles.rowTitle}>{t.label}</Text>
        <Text style={styles.rowDate}>{dateTime(t.created_at)}</Text>
        {details.map((d, i) => (
          <Text key={i} style={styles.rowDetail}>
            {d}
          </Text>
        ))}
      </View>
      <View style={{ alignItems: 'flex-end', gap: 2 }}>
        <Text style={[styles.amount, { color: positive ? colors.success : colors.ink }]}>{signed(t.amount)}</Text>
        <Text style={styles.after}>Bakiye {money(t.balance_after)}</Text>
      </View>
    </View>
  );
}

export default function WalletDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const walletId = Number(id);
  const wallet = useWallet(walletId);
  const tx = useTransactions(walletId);

  if (wallet.isLoading) return <Loading />;
  if (!wallet.data) return <ErrorState message={wallet.error?.message} onRetry={() => void wallet.refetch()} />;

  const w = wallet.data;
  const items = tx.data?.pages.flatMap((p) => p.data) ?? [];

  return (
    <>
      <Stack.Screen options={{ title: w.branch.display_name }} />
      <FlatList
        style={{ backgroundColor: colors.paper }}
        contentContainerStyle={styles.wrap}
        data={items}
        keyExtractor={(t) => String(t.id)}
        renderItem={({ item }) => <TxRow t={item} />}
        onEndReached={() => tx.hasNextPage && !tx.isFetchingNextPage && void tx.fetchNextPage()}
        onEndReachedThreshold={0.4}
        refreshControl={
          <RefreshControl
            refreshing={wallet.isRefetching || (tx.isRefetching && !tx.isFetchingNextPage)}
            onRefresh={() => {
              void wallet.refetch();
              void tx.refetch();
            }}
          />
        }
        ListHeaderComponent={
          <View style={{ gap: space.md, marginBottom: space.sm }}>
            <View style={styles.hero}>
              <Text style={styles.heroLabel}>KUMBARA BAKİYESİ</Text>
              <Text style={styles.heroValue}>{money(w.balance)}</Text>
              <Text style={styles.heroHelp}>Yalnızca bu restoranda kullanılabilir.</Text>
            </View>
            <View style={styles.stats}>
              <View style={styles.stat}>
                <Text style={styles.statLabel}>Toplam kazanılan</Text>
                <Text style={styles.statValue}>{money(w.total_earned)}</Text>
              </View>
              <View style={styles.stat}>
                <Text style={styles.statLabel}>Toplam kullanılan</Text>
                <Text style={styles.statValue}>{money(w.total_redeemed)}</Text>
              </View>
              <View style={styles.stat}>
                <Text style={styles.statLabel}>Ödül oranı</Text>
                <Text style={styles.statValue}>{rate(w.branch.reward_rate)}</Text>
              </View>
            </View>
            {w.branch.reward_terms ? (
              <View style={styles.terms}>
                <Text style={styles.termsTitle}>Restoranın ödül koşulları</Text>
                <Text style={styles.termsText}>{w.branch.reward_terms}</Text>
              </View>
            ) : null}
            <Text style={styles.section}>İşlem geçmişi</Text>
          </View>
        }
        ListEmptyComponent={
          tx.isLoading ? (
            <ActivityIndicator color={colors.ink} />
          ) : tx.isError ? (
            <ErrorState message={tx.error.message} onRetry={() => void tx.refetch()} />
          ) : (
            <Text style={styles.empty}>Henüz işlem yok.</Text>
          )
        }
        ListFooterComponent={tx.isFetchingNextPage ? <ActivityIndicator color={colors.ink} style={{ margin: space.lg }} /> : undefined}
      />
    </>
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.sm, width: '100%', maxWidth: 560, alignSelf: 'center' },
  hero: { backgroundColor: colors.ink, borderRadius: radius.lg, padding: space.lg, gap: 6, alignItems: 'center' },
  heroLabel: { color: colors.gold, fontSize: font.xs, fontWeight: '800', letterSpacing: 1.5 },
  heroValue: { color: colors.white, fontSize: 40, fontWeight: '800', fontVariant: ['tabular-nums'] },
  heroHelp: { color: '#c9d8d5', fontSize: font.sm },
  stats: { flexDirection: 'row', gap: space.sm },
  stat: {
    flex: 1,
    backgroundColor: colors.white,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
    gap: 4,
  },
  statLabel: { fontSize: font.xs, color: colors.gray },
  statValue: { fontSize: font.md, fontWeight: '700', color: colors.ink, fontVariant: ['tabular-nums'] },
  terms: { backgroundColor: colors.goldSoft, borderRadius: radius.md, padding: space.md, gap: 4 },
  termsTitle: { fontSize: font.sm, fontWeight: '700', color: colors.goldDark },
  termsText: { fontSize: font.sm, color: colors.ink, lineHeight: 20 },
  section: { fontSize: font.lg, fontWeight: '700', color: colors.ink, marginTop: space.sm },
  row: {
    flexDirection: 'row',
    gap: space.md,
    backgroundColor: colors.white,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.md,
  },
  rowTitle: { fontSize: font.md, fontWeight: '700', color: colors.ink },
  rowDate: { fontSize: font.xs + 1, color: colors.gray },
  rowDetail: { fontSize: font.sm, color: colors.ink2 },
  amount: { fontSize: font.md, fontWeight: '800', fontVariant: ['tabular-nums'] },
  after: { fontSize: font.xs + 1, color: colors.gray },
  empty: { textAlign: 'center', color: colors.gray, padding: space.lg },
});
