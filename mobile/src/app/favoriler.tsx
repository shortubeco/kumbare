// Favori restoranlarım.
import { router } from 'expo-router';
import { FlatList, RefreshControl, StyleSheet } from 'react-native';
import { BranchRow } from '@/components/branch-card';
import { EmptyState, ErrorState } from '@/components/state-views';
import { Loading } from '@/components/ui';
import { useFavorites } from '@/lib/queries';
import { colors, space } from '@/theme';

export default function FavoritesScreen() {
  const q = useFavorites();
  if (q.isLoading) return <Loading />;
  if (q.isError && !q.data) return <ErrorState message={q.error.message} onRetry={() => void q.refetch()} />;

  return (
    <FlatList
      style={{ backgroundColor: colors.paper }}
      contentContainerStyle={styles.wrap}
      data={q.data ?? []}
      keyExtractor={(b) => String(b.id)}
      renderItem={({ item }) => <BranchRow b={item} />}
      refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => void q.refetch()} />}
      ListEmptyComponent={
        <EmptyState
          title="Favoriniz yok"
          text="Restoran sayfasındaki kalp düğmesiyle favorilerinize ekleyebilirsiniz."
          action={{ title: 'Restoranları keşfet', onPress: () => router.navigate('/kesfet') }}
        />
      }
    />
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.md, width: '100%', maxWidth: 640, alignSelf: 'center', flexGrow: 1 },
});
