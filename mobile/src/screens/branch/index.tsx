/**
 * Şube (restoran) ayrıntısı: kapak, puan ve ödül, kumbaram, kampanyalar ve
 * Bilgiler / Menü / Değerlendirmeler bölümleri. Favori düğmesi üst çubukta.
 */
import Ionicons from '@expo/vector-icons/Ionicons';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { router, Stack } from 'expo-router';
import { useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { CampaignCard } from '@/components/campaign-card';
import { ErrorState } from '@/components/state-views';
import { Tabela } from '@/components/tabela';
import { Loading } from '@/components/ui';
import { api } from '@/lib/api';
import { money } from '@/lib/format';
import { useBranch } from '@/lib/queries';
import type { BranchDetail } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';
import { BranchInfo } from './info';
import { BranchMenu } from './menu';
import { BranchReviews } from './reviews';

type Section = 'info' | 'menu' | 'reviews';

function FavoriteButton({ b }: { b: BranchDetail }) {
  const qc = useQueryClient();
  const fav = !!b.viewer?.is_favorite;
  const toggle = useMutation({
    mutationFn: () => api(`/favorites/${b.id}`, { method: fav ? 'DELETE' : 'POST' }),
    onMutate: () => {
      qc.setQueryData<BranchDetail>(['branch', b.id], (old) =>
        old?.viewer ? { ...old, viewer: { ...old.viewer, is_favorite: !fav } } : old,
      );
    },
    onSettled: () => {
      void qc.invalidateQueries({ queryKey: ['branch', b.id] });
      void qc.invalidateQueries({ queryKey: ['favorites'] });
    },
  });
  return (
    <Pressable
      onPress={() => toggle.mutate()}
      hitSlop={10}
      style={{ marginRight: 16 }}
      accessibilityRole="button"
      accessibilityLabel={fav ? 'Favorilerden çıkar' : 'Favorilere ekle'}
      accessibilityState={{ selected: fav }}
    >
      <Ionicons name={fav ? 'heart' : 'heart-outline'} size={24} color={fav ? colors.gold : colors.white} />
    </Pressable>
  );
}

export function BranchScreen({ id }: { id: number }) {
  const q = useBranch(id);
  const [section, setSection] = useState<Section>('info');

  if (q.isLoading) return <Loading />;
  if (!q.data) return <ErrorState message={q.error?.message} onRetry={() => void q.refetch()} />;

  const b = q.data;
  const cover = b.photos[0];
  const wallet = b.viewer?.wallet;

  const tabs: { key: Section; label: string }[] = [
    { key: 'info', label: 'Bilgiler' },
    { key: 'menu', label: 'Menü' },
    { key: 'reviews', label: `Değerlendirmeler${b.rating.count ? ` (${b.rating.count})` : ''}` },
  ];

  return (
    <>
      <Stack.Screen options={{ title: b.business.name, headerRight: () => <FavoriteButton b={b} /> }} />
      <ScrollView
        style={{ backgroundColor: colors.paper }}
        contentContainerStyle={styles.wrap}
        refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => void q.refetch()} />}
      >
        {cover ? (
          <View>
            <Image source={{ uri: cover }} style={styles.cover} contentFit="cover" />
          </View>
        ) : (
          <Tabela name={b.business.name} categoryId={b.category?.id} label={b.business.name} size={120} style={styles.coverTabela} />
        )}

        <View style={{ gap: 4 }}>
          <Text style={styles.name}>{b.display_name}</Text>
          <Text style={styles.meta}>{[b.category?.name, b.location.district, b.location.city].filter(Boolean).join(' · ')}</Text>
          <Text style={styles.rating}>{b.rating.label}</Text>
        </View>

        <View style={styles.badges}>
          <View style={[styles.badge, Number(b.reward_rate) > 0 ? styles.badgeGold : styles.badgeMuted]}>
            <Text style={styles.badgeText}>{b.reward_label}</Text>
          </View>
          {b.is_sponsored ? (
            <View style={[styles.badge, styles.badgeLine]}>
              <Text style={styles.badgeText}>Öne çıkan</Text>
            </View>
          ) : null}
        </View>

        {wallet ? (
          <Pressable
            onPress={() => router.push({ pathname: '/kumbara/[id]', params: { id: String(wallet.id) } })}
            style={({ pressed }) => [styles.wallet, pressed && { opacity: 0.88 }]}
            accessibilityRole="button"
          >
            <View style={{ flex: 1 }}>
              <Text style={styles.walletLabel}>BU RESTORANDAKİ KUMBARAM</Text>
              <Text style={styles.walletValue}>{money(wallet.balance)}</Text>
            </View>
            <Text style={styles.walletLink}>Geçmiş ›</Text>
          </Pressable>
        ) : (
          <View style={styles.noWallet}>
            <Text style={styles.noWalletText}>
              Bu restoranda henüz kumbaranız yok. Ödemede müşteri numaranızı söyleyin; ödülünüz burada birikmeye başlar.
            </Text>
          </View>
        )}

        {b.campaigns.length ? (
          <View style={{ gap: space.sm }}>
            <Text style={styles.section}>Kampanyalar</Text>
            {b.campaigns.map((c) => (
              <CampaignCard key={c.id} c={c} showBranch={false} />
            ))}
          </View>
        ) : null}

        <View style={styles.tabs} accessibilityRole="tablist">
          {tabs.map((t) => (
            <Pressable
              key={t.key}
              onPress={() => setSection(t.key)}
              style={[styles.tab, section === t.key && styles.tabOn]}
              accessibilityRole="tab"
              accessibilityState={{ selected: section === t.key }}
            >
              <Text style={[styles.tabText, section === t.key && styles.tabTextOn]} numberOfLines={1}>
                {t.label}
              </Text>
            </Pressable>
          ))}
        </View>

        {section === 'info' ? <BranchInfo b={b} /> : section === 'menu' ? <BranchMenu b={b} /> : <BranchReviews b={b} />}
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.md, width: '100%', maxWidth: 640, alignSelf: 'center', paddingBottom: space.xxl },
  cover: { width: '100%', aspectRatio: 16 / 9, borderRadius: radius.lg, backgroundColor: colors.line2 },
  coverTabela: { width: '100%', height: 130, borderRadius: radius.lg },
  name: { fontSize: font.xl, fontWeight: '800', color: colors.ink },
  meta: { fontSize: font.sm, color: colors.gray },
  rating: { fontSize: font.md, fontWeight: '700', color: colors.ink },
  badges: { flexDirection: 'row', gap: space.sm, flexWrap: 'wrap' },
  badge: { borderRadius: radius.pill, paddingHorizontal: 12, paddingVertical: 5 },
  badgeGold: { backgroundColor: colors.gold },
  badgeMuted: { backgroundColor: colors.line2 },
  badgeLine: { borderWidth: 1, borderColor: colors.line, backgroundColor: colors.white },
  badgeText: { fontSize: font.sm, fontWeight: '800', color: colors.ink },
  wallet: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.ink,
    borderRadius: radius.lg,
    padding: space.lg,
    gap: space.md,
  },
  walletLabel: { color: colors.gold, fontSize: font.xs, fontWeight: '800', letterSpacing: 1.2 },
  walletValue: { color: colors.white, fontSize: font.xl, fontWeight: '800', fontVariant: ['tabular-nums'] },
  walletLink: { color: colors.gold, fontWeight: '700' },
  noWallet: { backgroundColor: colors.goldSoft, borderRadius: radius.md, padding: space.md },
  noWalletText: { fontSize: font.sm, color: colors.ink, lineHeight: 20 },
  section: { fontSize: font.lg, fontWeight: '700', color: colors.ink },
  tabs: { flexDirection: 'row', backgroundColor: colors.line2, borderRadius: radius.md, padding: 4, gap: 4, marginTop: space.sm },
  tab: { flex: 1, paddingVertical: 10, alignItems: 'center', borderRadius: radius.sm },
  tabOn: { backgroundColor: colors.white },
  tabText: { fontSize: font.sm, fontWeight: '600', color: colors.gray },
  tabTextOn: { color: colors.ink, fontWeight: '800' },
});
