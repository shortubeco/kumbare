// Değerlendirmelerim: yazdığım puan ve yorumlar; restorana dokununca düzenlemek için şube sayfası açılır.
import { useInfiniteQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { EmptyState, ErrorState } from '@/components/state-views';
import { Loading } from '@/components/ui';
import { api } from '@/lib/api';
import { date } from '@/lib/format';
import type { Paginated, Review } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';

export default function MyReviewsScreen() {
  const q = useInfiniteQuery({
    queryKey: ['my-reviews'],
    queryFn: ({ pageParam }) => api<Paginated<Review>>(`/me/reviews?page=${pageParam}`),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  });

  if (q.isLoading) return <Loading />;
  if (q.isError && !q.data) return <ErrorState message={q.error.message} onRetry={() => void q.refetch()} />;
  const items = q.data?.pages.flatMap((p) => p.data) ?? [];

  return (
    <FlatList
      style={{ backgroundColor: colors.paper }}
      contentContainerStyle={styles.wrap}
      data={items}
      keyExtractor={(r) => String(r.id)}
      onEndReached={() => q.hasNextPage && !q.isFetchingNextPage && void q.fetchNextPage()}
      refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => void q.refetch()} />}
      renderItem={({ item: r }) => (
        <Pressable
          onPress={() => r.branch && router.push({ pathname: '/sube/[id]', params: { id: String(r.branch.id) } })}
          style={({ pressed }) => [styles.card, pressed && { opacity: 0.88 }]}
          accessibilityRole="button"
        >
          <View style={styles.head}>
            <Text style={styles.branch} numberOfLines={2}>
              {r.branch?.display_name}
            </Text>
            <Text style={styles.score}>{r.score_label}</Text>
          </View>
          <Text style={styles.date}>
            {date(r.updated_at)}
            {r.status && r.status !== 'published' ? ' · Yayında değil' : ''}
          </Text>
          {r.comment ? <Text style={styles.p}>{r.comment}</Text> : null}
          {r.reply ? (
            <View style={styles.reply}>
              <Text style={styles.replyTitle}>Restoranın cevabı</Text>
              <Text style={styles.p}>{r.reply.body}</Text>
            </View>
          ) : null}
          <Text style={styles.edit}>Düzenlemek için dokunun ›</Text>
        </Pressable>
      )}
      ListEmptyComponent={
        <EmptyState
          title="Henüz değerlendirmeniz yok"
          text="Ödül kazandığınız restoranların sayfasından puan verip yorum yazabilirsiniz."
        />
      }
      ListFooterComponent={q.isFetchingNextPage ? <ActivityIndicator color={colors.ink} style={{ margin: space.lg }} /> : undefined}
    />
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.md, width: '100%', maxWidth: 560, alignSelf: 'center', flexGrow: 1 },
  card: { backgroundColor: colors.white, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, padding: space.lg, gap: 6 },
  head: { flexDirection: 'row', justifyContent: 'space-between', gap: space.md },
  branch: { flex: 1, fontSize: font.md, fontWeight: '700', color: colors.ink },
  score: { fontSize: font.md, fontWeight: '800', color: colors.ink },
  date: { fontSize: font.xs + 1, color: colors.gray },
  p: { fontSize: font.md, color: colors.ink, lineHeight: 22 },
  reply: {
    backgroundColor: colors.paper,
    borderLeftWidth: 3,
    borderLeftColor: colors.gold,
    borderRadius: radius.sm,
    padding: space.md,
    gap: 4,
  },
  replyTitle: { fontSize: font.sm, fontWeight: '700', color: colors.ink2 },
  edit: { fontSize: font.xs + 1, color: colors.ink2, alignSelf: 'flex-end' },
});
