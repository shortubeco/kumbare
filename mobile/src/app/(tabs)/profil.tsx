// Profil: müşteri numarası, bilgilerim, hesap işlemleri menüsü, çıkış ve uygulama sürümü.
import Ionicons from '@expo/vector-icons/Ionicons';
import Constants from 'expo-constants';
import { type Href, router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { type ComponentProps, useState } from 'react';
import { Platform, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { CustomerNumber } from '@/components/customer-number';
import { Button, Card } from '@/components/ui';
import { UpdateHint } from '@/components/update-hint';
import { confirm } from '@/lib/confirm';
import { date } from '@/lib/format';
import { KVKK_URL } from '@/lib/site';
import { useMe } from '@/lib/use-me';
import { useAuth } from '@/providers/auth';
import { colors, font, radius, space } from '@/theme';

function Row({ label, value }: { label: string; value?: string | null }) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={styles.rowValue}>{value || '—'}</Text>
    </View>
  );
}

type IconName = ComponentProps<typeof Ionicons>['name'];

function MenuItem({ icon, label, onPress, danger }: { icon: IconName; label: string; onPress: () => void; danger?: boolean }) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.item, pressed && { backgroundColor: colors.line2 }]}
      accessibilityRole="button"
    >
      <Ionicons name={icon} size={21} color={danger ? colors.error : colors.ink2} />
      <Text style={[styles.itemText, danger && { color: colors.error }]}>{label}</Text>
      <Text style={styles.chev}>›</Text>
    </Pressable>
  );
}

const go = (href: Href) => () => router.push(href);

function openKvkk() {
  if (Platform.OS === 'web') window.open(KVKK_URL, '_blank');
  else void WebBrowser.openBrowserAsync(KVKK_URL);
}

export default function ProfileScreen() {
  const me = useMe();
  const { signOut } = useAuth();
  const [busy, setBusy] = useState(false);
  const c = me.data;

  async function confirmSignOut() {
    if (await confirm('Çıkış yap', 'Çıkış yapmak istiyor musunuz?', 'Çıkış yap', true)) {
      setBusy(true);
      await signOut();
    }
  }

  return (
    <ScrollView
      style={{ backgroundColor: colors.paper }}
      contentContainerStyle={styles.wrap}
      refreshControl={<RefreshControl refreshing={me.isRefetching} onRefresh={() => void me.refetch()} />}
    >
      <CustomerNumber number={c?.customer_no} compact />

      <Card>
        <View style={styles.cardHead}>
          <Text style={styles.title}>Bilgilerim</Text>
          <Pressable onPress={go('/profil-duzenle')} hitSlop={8} accessibilityRole="button">
            <Text style={styles.edit}>Düzenle</Text>
          </Pressable>
        </View>
        <Row label="Ad soyad" value={c ? `${c.first_name} ${c.last_name}` : null} />
        <Row label="Telefon" value={c?.phone} />
        <Row label="E-posta" value={c?.email} />
        <Row label="İl / ilçe" value={c?.city ? `${c.city.name} / ${c.district?.name ?? ''}` : null} />
        <Row label="Doğum tarihi" value={date(c?.birth_date)} />
        <Row label="Yorumlarda adım" value={c?.public_name} />
      </Card>

      <View style={styles.menu}>
        <MenuItem icon="heart-outline" label="Favori restoranlarım" onPress={go('/favoriler')} />
        <MenuItem icon="chatbubble-ellipses-outline" label="Değerlendirmelerim" onPress={go('/degerlendirmelerim')} />
        <MenuItem icon="key-outline" label="Şifre değiştir" onPress={go('/sifre-degistir')} />
        <MenuItem icon="document-text-outline" label="KVKK aydınlatma metni" onPress={openKvkk} />
        <MenuItem icon="trash-outline" label="Hesabımı sil" onPress={go('/hesabi-sil')} danger />
      </View>

      <UpdateHint />
      <Button title="Çıkış yap" variant="danger" onPress={confirmSignOut} loading={busy} />
      <Text style={styles.version}>Kumbare sürüm {Constants.expoConfig?.version ?? '1.0.0'}</Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  wrap: { padding: space.lg, gap: space.lg, width: '100%', maxWidth: 560, alignSelf: 'center' },
  cardHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: space.xs },
  title: { fontSize: font.lg, fontWeight: '700', color: colors.ink },
  edit: { fontSize: font.sm, fontWeight: '700', color: colors.ink2, textDecorationLine: 'underline' },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: space.md,
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: colors.line2,
  },
  rowLabel: { color: colors.gray, fontSize: font.sm },
  rowValue: { color: colors.ink, fontSize: font.sm, fontWeight: '600', flexShrink: 1, textAlign: 'right' },
  menu: { backgroundColor: colors.white, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, overflow: 'hidden' },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: 15,
    paddingHorizontal: space.lg,
    borderTopWidth: 1,
    borderTopColor: colors.line2,
  },
  itemText: { flex: 1, fontSize: font.md, color: colors.ink, fontWeight: '600' },
  chev: { fontSize: font.lg, color: colors.gray },
  version: { textAlign: 'center', color: colors.gray, fontSize: font.xs + 1 },
});
