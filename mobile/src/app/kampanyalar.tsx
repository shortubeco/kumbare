/**
 * Kampanyalar: kayıtlı ilçenizdeki restoranların yürürlükteki kampanyaları.
 * Liste açılışta ve "Yenile" düğmesiyle çekilir. İlçe Profil'den değiştirilebilir.
 */
import { FlatList, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { CampaignCard } from '@/components/campaign-card';
import { EmptyState, ErrorState } from '@/components/state-views';
import { Button, Loading } from '@/components/ui';
import { useCampaigns } from '@/lib/queries';
import { colors, font, space } from '@/theme';

export default function CampaignsScreen() {
  const q = useCampaigns();

  if (q.isLoading) return <Loading />;
  if (q.isError && !q.data) return <ErrorState message={q.error.message} onRetry={() => void q.refetch()} />;

  const d = q.data?.district;
  const items = q.data?.data ?? [];

  return (
    <FlatList
      style={{ backgroundColor: colors.paper }}
      contentContainerStyle={styles.wrap}
      data={items}
      keyExtractor={(c) => String(c.id)}
      renderItem={({ item }) => <CampaignCard c={item} />}
      refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => void q.refetch()} />}
      ListHeaderComponent={
        <View style={styles.top}>
          <Text style={styles.intro}>{d?.name ? `${d.city ?? ''} / ${d.name} bölgesindeki kampanyalar` : 'İlçenizdeki kampanyalar'}</Text>
          <Button title="Yenile" variant="secondary" onPress={() => void q.refetch()} loading={q.isRefetching} style={{ minHeight: 40 }} />
        </View>
      }
      ListEmptyComponent={
        <EmptyState
          title="Şu an kampanya yok"
          text="İlçenizdeki restoranlar kampanya başlattığında burada görünür. Başka bölgelerdeki restoranlara Keşfet'ten bakabilirsiniz."
        />
      }
    />
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.md, width: '100%', maxWidth: 560, alignSelf: 'center', flexGrow: 1 },
  top: { flexDirection: 'row', alignItems: 'center', gap: space.md },
  intro: { flex: 1, fontSize: font.md, fontWeight: '600', color: colors.ink },
});
