// Keşfet: restoran arama ve filtreleme (il/ilçe, mutfak, sıralama, kampanyalı, ödül veren).
import { router } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, TextInput, View } from 'react-native';
import { BranchRow } from '@/components/branch-card';
import { type Option, SelectField } from '@/components/select-field';
import { EmptyState, ErrorState } from '@/components/state-views';
import { type BranchFilters, useBranches, useCategories, useCities, useDistricts } from '@/lib/queries';
import { useMe } from '@/lib/use-me';
import { colors, font, radius, space } from '@/theme';

const SORTS: Option[] = [
  { value: 'popular', label: 'Önerilen' },
  { value: 'rate_desc', label: 'En yüksek ödül' },
  { value: 'score', label: 'En yüksek puan' },
  { value: 'most_reviewed', label: 'En çok değerlendirilen' },
  { value: 'newest', label: 'En yeni' },
];

function Chip({ label, active, onPress }: { label: string; active?: boolean; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      style={[styles.chip, active && styles.chipOn]}
      accessibilityRole="button"
      accessibilityState={{ selected: !!active }}
    >
      <Text style={[styles.chipText, active && styles.chipTextOn]} numberOfLines={1}>
        {label}
      </Text>
    </Pressable>
  );
}

export default function KesfetScreen() {
  const me = useMe().data;
  const [text, setText] = useState('');
  const [q, setQ] = useState('');
  const [pickedCity, setCity] = useState<number>(0);
  const [district, setDistrict] = useState<number>(0);
  const [category, setCategory] = useState<number>(0);
  const [sort, setSort] = useState('popular');
  const [campaignOnly, setCampaignOnly] = useState(false);
  const [rewardOnly, setRewardOnly] = useState(false);
  const [cityTouched, setCityTouched] = useState(false);

  // Başlangıçta müşterinin kayıtlı ili seçili gelir.
  const city = cityTouched ? pickedCity : (me?.city?.id ?? 0);

  useEffect(() => {
    const t = setTimeout(() => setQ(text.trim()), 400);
    return () => clearTimeout(t);
  }, [text]);

  const cities = useCities();
  const districts = useDistricts(city || null);
  const categories = useCategories();

  const cityOptions: Option[] = useMemo(
    () => [{ value: 0, label: 'Tüm Türkiye' }, ...(cities.data ?? []).map((c) => ({ value: c.id, label: c.name }))],
    [cities.data],
  );
  const districtOptions: Option[] = useMemo(
    () => [{ value: 0, label: 'Tüm ilçeler' }, ...(districts.data ?? []).map((d) => ({ value: d.id, label: d.name }))],
    [districts.data],
  );
  const categoryOptions: Option[] = useMemo(
    () => [{ value: 0, label: 'Tüm mutfaklar' }, ...(categories.data ?? []).map((c) => ({ value: c.id, label: c.name }))],
    [categories.data],
  );

  const filters: BranchFilters = {
    q: q.length >= 2 ? q : undefined,
    city: district ? null : city || null,
    district: district || null,
    category: category || null,
    sort,
    has_campaign: campaignOnly,
    min_rate: rewardOnly ? 0.01 : undefined,
  };
  const list = useBranches(filters);
  const items = list.data?.pages.flatMap((p) => p.data) ?? [];
  const total = list.data?.pages[0]?.meta.total;

  const label = (opts: Option[], v: number | string, fallback: string) => opts.find((o) => o.value === v)?.label ?? fallback;

  const header = (
    <View style={{ gap: space.md, marginBottom: space.xs }}>
      <TextInput
        value={text}
        onChangeText={setText}
        placeholder="Restoran veya mutfak ara"
        placeholderTextColor="#8a9592"
        style={styles.search}
        returnKeyType="search"
        autoCorrect={false}
        accessibilityLabel="Restoran ara"
      />
      <View style={styles.chips}>
        <SelectField
          label="İl"
          options={cityOptions}
          value={city}
          onChange={(v) => {
            setCityTouched(true);
            setCity(Number(v));
            setDistrict(0);
          }}
          trigger={(open) => <Chip label={label(cityOptions, city, 'İl')} active={city !== 0} onPress={open} />}
        />
        {city ? (
          <SelectField
            label="İlçe"
            options={districtOptions}
            value={district}
            onChange={(v) => setDistrict(Number(v))}
            trigger={(open) => <Chip label={label(districtOptions, district, 'Tüm ilçeler')} active={district !== 0} onPress={open} />}
          />
        ) : null}
        <SelectField
          label="Mutfak"
          options={categoryOptions}
          value={category}
          onChange={(v) => setCategory(Number(v))}
          trigger={(open) => <Chip label={label(categoryOptions, category, 'Mutfak')} active={category !== 0} onPress={open} />}
        />
        <SelectField
          label="Sıralama"
          options={SORTS}
          value={sort}
          onChange={(v) => setSort(String(v))}
          trigger={(open) => <Chip label={`Sırala: ${label(SORTS, sort, '')}`} active={sort !== 'popular'} onPress={open} />}
        />
        <Chip label="Kampanyalı" active={campaignOnly} onPress={() => setCampaignOnly((x) => !x)} />
        <Chip label="Ödül veren" active={rewardOnly} onPress={() => setRewardOnly((x) => !x)} />
        <Chip label="♥ Favorilerim" onPress={() => router.push('/favoriler')} />
      </View>
      {sort === 'score' ? <Text style={styles.note}>Puan sıralamasında en az 10 değerlendirmesi olan restoranlar gösterilir.</Text> : null}
      {total !== undefined ? <Text style={styles.count}>Bulunan restoran: {total}</Text> : null}
    </View>
  );

  return (
    <FlatList
      style={{ backgroundColor: colors.paper }}
      contentContainerStyle={styles.wrap}
      data={items}
      keyExtractor={(b) => String(b.id)}
      renderItem={({ item }) => <BranchRow b={item} />}
      keyboardShouldPersistTaps="handled"
      ListHeaderComponent={header}
      onEndReached={() => list.hasNextPage && !list.isFetchingNextPage && void list.fetchNextPage()}
      onEndReachedThreshold={0.5}
      refreshControl={<RefreshControl refreshing={list.isRefetching && !list.isFetchingNextPage} onRefresh={() => void list.refetch()} />}
      ListEmptyComponent={
        list.isLoading ? (
          <ActivityIndicator color={colors.ink} style={{ margin: space.xl }} />
        ) : list.isError ? (
          <ErrorState message={list.error.message} onRetry={() => void list.refetch()} />
        ) : (
          <EmptyState title="Sonuç bulunamadı" text="Filtreleri değiştirerek ya da başka bir ilde aramayı deneyin." />
        )
      }
      ListFooterComponent={list.isFetchingNextPage ? <ActivityIndicator color={colors.ink} style={{ margin: space.lg }} /> : undefined}
    />
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.md, width: '100%', maxWidth: 640, alignSelf: 'center', flexGrow: 1 },
  search: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.pill,
    backgroundColor: colors.white,
    paddingHorizontal: space.lg,
    fontSize: font.md,
    color: colors.ink,
  },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
  chip: {
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.white,
    borderRadius: radius.pill,
    paddingHorizontal: 14,
    paddingVertical: 8,
    maxWidth: 220,
  },
  chipOn: { backgroundColor: colors.ink, borderColor: colors.ink },
  chipText: { fontSize: font.sm, color: colors.ink, fontWeight: '600' },
  chipTextOn: { color: colors.white },
  note: { fontSize: font.xs + 1, color: colors.gray },
  count: { fontSize: font.sm, color: colors.gray },
});
