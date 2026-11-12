/**
 * Bildirimler: restoranların onayınızı beklediği ödül kullanım ve düzeltme istekleri.
 * Liste uygulama açılışında ve "Yenile" düğmesiyle sunucudan çekilir (push bildirim yok).
 */
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { EmptyState, ErrorState } from '@/components/state-views';
import { Button, Loading, Notice } from '@/components/ui';
import { api, ApiError } from '@/lib/api';
import { confirm } from '@/lib/confirm';
import { dateTime, money } from '@/lib/format';
import { keys, useApprovals } from '@/lib/queries';
import type { ApprovalRequest } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';

type Result = { tone: 'success' | 'error' | 'info'; text: string };

function describe(r: ApprovalRequest): string {
  const amount = money(Math.abs(Number(r.amount)));
  if (r.type === 'redeem') return `Kumbaranızdaki ödülden ${amount} kullanılacak.`;
  return Number(r.amount) > 0 ? `Kumbaranıza ${amount} eklenecek.` : `Kumbaranızdan ${amount} düşülecek.`;
}

function RequestCard({ r, onDone }: { r: ApprovalRequest; onDone: (res: Result) => void }) {
  const qc = useQueryClient();

  const refreshAll = () => {
    void qc.invalidateQueries({ queryKey: keys.approvals });
    void qc.invalidateQueries({ queryKey: keys.wallets });
    void qc.invalidateQueries({ queryKey: ['wallet'] });
  };

  const respond = useMutation({
    mutationFn: (action: 'approve' | 'reject') => api<{ message: string }>(`/approvals/${r.id}/${action}`, { method: 'POST' }),
    onSuccess: (res, action) => {
      onDone({ tone: action === 'approve' ? 'success' : 'info', text: `${r.branch.display_name}: ${res.message}` });
      refreshAll();
    },
    onError: (e) => {
      onDone({ tone: 'error', text: `${r.branch.display_name}: ${e instanceof ApiError ? e.message : 'İşlem tamamlanamadı.'}` });
      refreshAll();
    },
  });

  async function approve() {
    const ok = await confirm('Onaylıyor musunuz?', `${r.branch.display_name}\n${describe(r)}`, 'Onayla');
    if (ok) respond.mutate('approve');
  }

  async function reject() {
    const text = r.type === 'redeem' ? 'Reddederseniz ödülünüz kullanılmaz, bakiyeniz değişmez.' : 'Reddederseniz bu düzeltme uygulanmaz.';
    const ok = await confirm('İsteği reddet', text, 'Reddet', true);
    if (ok) respond.mutate('reject');
  }

  const busy = respond.isPending;
  const after = Number(r.balance_after);

  return (
    <View style={styles.card}>
      <View style={styles.cardHead}>
        <Text style={styles.badge}>{r.title.toLocaleUpperCase('tr-TR')}</Text>
        <Text style={styles.date}>{dateTime(r.created_at)}</Text>
      </View>
      <Text style={styles.branch}>{r.branch.display_name}</Text>
      <Text style={styles.desc}>{describe(r)}</Text>

      <View style={styles.amounts}>
        <View style={styles.amountBox}>
          <Text style={styles.amountLabel}>Şu anki bakiye</Text>
          <Text style={styles.amountValue}>{money(r.current_balance)}</Text>
        </View>
        <Text style={styles.arrow}>→</Text>
        <View style={styles.amountBox}>
          <Text style={styles.amountLabel}>Onaydan sonra</Text>
          <Text style={[styles.amountValue, after < 0 && { color: colors.error }]}>{money(r.balance_after)}</Text>
        </View>
      </View>

      {after < 0 ? <Notice text="Bakiyeniz bu istek için yeterli değil; onaylansa da işlem gerçekleşmez." /> : null}
      {r.reason ? <Text style={styles.meta}>Açıklama: {r.reason}</Text> : null}
      {r.related_transaction ? (
        <Text style={styles.meta}>
          Düzeltilen işlem: {dateTime(r.related_transaction.created_at)}, {money(r.related_transaction.amount)}
        </Text>
      ) : null}
      {r.requested_by ? <Text style={styles.meta}>İsteği gönderen: {r.requested_by}</Text> : null}

      <View style={styles.actions}>
        <Button title="Reddet" variant="danger" onPress={reject} disabled={busy} style={{ flex: 1 }} />
        <Button title="Onayla" onPress={approve} loading={busy} style={{ flex: 1.4 }} />
      </View>
    </View>
  );
}

export default function NotificationsScreen() {
  const q = useApprovals();
  const [result, setResult] = useState<Result | null>(null);

  if (q.isLoading) return <Loading />;
  if (q.isError && !q.data) return <ErrorState message={q.error.message} onRetry={() => void q.refetch()} />;

  const items = q.data ?? [];

  return (
    <FlatList
      style={{ backgroundColor: colors.paper }}
      contentContainerStyle={styles.wrap}
      data={items}
      keyExtractor={(r) => String(r.id)}
      renderItem={({ item }) => <RequestCard r={item} onDone={setResult} />}
      refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => void q.refetch()} />}
      ListHeaderComponent={
        <View style={{ gap: space.md }}>
          <View style={styles.top}>
            <Text style={styles.intro}>Restoranlar ödülünüzü kullanmak ya da düzeltme yapmak için onayınızı ister.</Text>
            <Button
              title="Yenile"
              variant="secondary"
              onPress={() => void q.refetch()}
              loading={q.isRefetching}
              style={{ minHeight: 40 }}
            />
          </View>
          {result ? <Notice text={result.text} tone={result.tone} /> : null}
        </View>
      }
      ListEmptyComponent={
        <EmptyState
          title="Onay bekleyen istek yok"
          text="Kasada ödülünüzü kullanmak istediğinizde istek burada görünür. Görmüyorsanız Yenile'ye dokunun."
        />
      }
    />
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.md, width: '100%', maxWidth: 560, alignSelf: 'center', flexGrow: 1 },
  top: { flexDirection: 'row', alignItems: 'center', gap: space.md },
  intro: { flex: 1, color: colors.gray, fontSize: font.sm, lineHeight: 20 },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 2,
    borderColor: colors.gold,
    padding: space.lg,
    gap: space.sm,
  },
  cardHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  badge: { fontSize: font.xs, fontWeight: '800', letterSpacing: 1.2, color: colors.goldDark },
  date: { fontSize: font.xs + 1, color: colors.gray },
  branch: { fontSize: font.lg, fontWeight: '700', color: colors.ink },
  desc: { fontSize: font.md, color: colors.ink, lineHeight: 22 },
  amounts: { flexDirection: 'row', alignItems: 'center', gap: space.sm, marginVertical: space.xs },
  amountBox: { flex: 1, backgroundColor: colors.paper, borderRadius: radius.md, padding: space.md, gap: 2 },
  amountLabel: { fontSize: font.xs, color: colors.gray },
  amountValue: { fontSize: font.lg, fontWeight: '800', color: colors.ink, fontVariant: ['tabular-nums'] },
  arrow: { fontSize: font.lg, color: colors.gray },
  meta: { fontSize: font.sm, color: colors.ink2 },
  actions: { flexDirection: 'row', gap: space.sm, marginTop: space.sm },
});
