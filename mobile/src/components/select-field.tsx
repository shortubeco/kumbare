// Seçim alanı: dokununca tam ekran liste açılır (uzun listelerde arama kutusu). Web'de de aynı çalışır.
import { type ReactNode, useMemo, useState } from 'react';
import { FlatList, Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, font, radius, space } from '@/theme';

export type Option = { value: number | string; label: string };

type Props = {
  label: string;
  placeholder?: string;
  options: Option[];
  value: Option['value'] | null;
  onChange: (value: Option['value']) => void;
  error?: string;
  disabled?: boolean;
  compact?: boolean;
  /** Verilirse kutu yerine bu gösterilir (ör. filtre çipi). */
  trigger?: (open: () => void, selected: Option | undefined) => ReactNode;
};

const lower = (s: string) => s.toLocaleLowerCase('tr-TR');

export function SelectField({ label, placeholder = 'Seçin', options, value, onChange, error, disabled, compact, trigger }: Props) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const selected = options.find((o) => o.value === value);
  const searchable = options.length > 40;

  const list = useMemo(() => {
    const term = lower(q.trim());
    return term ? options.filter((o) => lower(o.label).includes(term)) : options;
  }, [options, q]);

  return (
    <View style={trigger ? undefined : [styles.field, compact && { flex: 1 }]}>
      {trigger ? (
        trigger(() => !disabled && setOpen(true), selected)
      ) : (
        <>
          <Text style={styles.label}>{label}</Text>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={`${label}: ${selected?.label ?? placeholder}`}
            onPress={() => !disabled && setOpen(true)}
            style={[styles.box, !!error && { borderColor: colors.error }, disabled && { opacity: 0.5 }]}
          >
            <Text style={[styles.value, !selected && { color: '#8a9592' }]} numberOfLines={1}>
              {selected?.label ?? placeholder}
            </Text>
            <Text style={styles.caret}>▾</Text>
          </Pressable>
          {error ? <Text style={styles.error}>{error}</Text> : null}
        </>
      )}

      <Modal visible={open} animationType="slide" onRequestClose={() => setOpen(false)}>
        <SafeAreaView style={styles.modal}>
          <View style={styles.sheet}>
            <View style={styles.head}>
              <Text style={styles.title}>{label}</Text>
              <Pressable onPress={() => setOpen(false)} hitSlop={10} accessibilityRole="button">
                <Text style={styles.close}>Kapat</Text>
              </Pressable>
            </View>
            {searchable ? (
              <TextInput value={q} onChangeText={setQ} placeholder="Ara…" placeholderTextColor="#8a9592" style={styles.search} autoFocus />
            ) : null}
            <FlatList
              data={list}
              keyExtractor={(o) => String(o.value)}
              keyboardShouldPersistTaps="handled"
              initialNumToRender={30}
              renderItem={({ item }) => (
                <Pressable
                  onPress={() => {
                    onChange(item.value);
                    setOpen(false);
                    setQ('');
                  }}
                  style={({ pressed }) => [styles.row, item.value === value && styles.rowOn, pressed && { backgroundColor: colors.line2 }]}
                >
                  <Text style={[styles.rowText, item.value === value && { fontWeight: '700' }]}>{item.label}</Text>
                </Pressable>
              )}
              ListEmptyComponent={<Text style={styles.empty}>Sonuç yok.</Text>}
            />
          </View>
        </SafeAreaView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  field: { gap: 6 },
  label: { fontSize: font.sm, fontWeight: '600', color: colors.ink },
  box: {
    minHeight: 50,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.white,
    paddingHorizontal: space.md,
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.sm,
  },
  value: { flex: 1, fontSize: font.md, color: colors.ink },
  caret: { color: colors.gray, fontSize: font.md },
  error: { color: colors.error, fontSize: font.xs + 1 },
  modal: { flex: 1, backgroundColor: colors.paper },
  sheet: { flex: 1, width: '100%', maxWidth: 560, alignSelf: 'center' },
  head: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: space.lg,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
    backgroundColor: colors.white,
  },
  title: { fontSize: font.lg, fontWeight: '700', color: colors.ink },
  close: { fontSize: font.md, color: colors.ink2, fontWeight: '600' },
  search: {
    margin: space.lg,
    minHeight: 46,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.white,
    paddingHorizontal: space.md,
    fontSize: font.md,
    color: colors.ink,
  },
  row: {
    paddingVertical: 14,
    paddingHorizontal: space.lg,
    borderBottomWidth: 1,
    borderBottomColor: colors.line2,
    backgroundColor: colors.white,
  },
  rowOn: { backgroundColor: colors.goldSoft },
  rowText: { fontSize: font.md, color: colors.ink },
  empty: { padding: space.lg, color: colors.gray, textAlign: 'center' },
});
