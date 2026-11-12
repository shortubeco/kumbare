// Bilgilerimi düzenle. Müşteri numarası ve doğum tarihi değiştirilemez.
import { useQueryClient } from '@tanstack/react-query';
import { goBack } from '@/lib/nav';
import { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { type Option, SelectField } from '@/components/select-field';
import { Button, Card, Field, Notice, Screen } from '@/components/ui';
import { api } from '@/lib/api';
import { errorMessage, fieldErrors } from '@/lib/form-errors';
import { date } from '@/lib/format';
import { useCities, useDistricts } from '@/lib/queries';
import { useMe } from '@/lib/use-me';
import { type Customer, useAuth } from '@/providers/auth';
import { colors, font, radius, space } from '@/theme';

function initialsName(first: string, last: string) {
  const i = last.trim().slice(0, 1);
  return `${first.trim()}${i ? ` ${i.toLocaleUpperCase('tr-TR')}.` : ''}`;
}

export default function EditProfileScreen() {
  const me = useMe().data;
  const { setCustomer } = useAuth();
  const qc = useQueryClient();

  const [f, setF] = useState({
    first_name: me?.first_name ?? '',
    last_name: me?.last_name ?? '',
    phone: me?.phone ?? '',
    email: me?.email ?? '',
  });
  const [cityId, setCityId] = useState<number | null>(me?.city?.id ?? null);
  const [districtId, setDistrictId] = useState<number | null>(me?.district?.id ?? null);
  const [mode, setMode] = useState<'initials' | 'full'>(me?.display_name_mode ?? 'initials');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));

  const cities = useCities();
  const districts = useDistricts(cityId);
  const cityOptions: Option[] = useMemo(() => (cities.data ?? []).map((c) => ({ value: c.id, label: c.name })), [cities.data]);
  const districtOptions: Option[] = useMemo(() => (districts.data ?? []).map((d) => ({ value: d.id, label: d.name })), [districts.data]);

  async function save() {
    setError(null);
    if (!districtId) {
      setErrors({ district_id: 'İlçe seçin.' });
      setError('Lütfen işaretli alanları kontrol edin.');
      return;
    }
    setErrors({});
    setBusy(true);
    try {
      const res = await api<{ data: Customer }>('/me', {
        method: 'PUT',
        body: {
          first_name: f.first_name.trim(),
          last_name: f.last_name.trim(),
          phone: f.phone.trim(),
          email: f.email.trim(),
          city_id: cityId,
          district_id: districtId,
          display_name_mode: mode,
        },
      });
      setCustomer(res.data);
      qc.setQueryData(['me'], res.data);
      // İlçe değiştiyse ana sayfa blokları ve kampanyalar yeni ilçeye göre gelmeli.
      void qc.invalidateQueries({ queryKey: ['home'] });
      void qc.invalidateQueries({ queryKey: ['campaigns'] });
      goBack('/profil');
    } catch (e) {
      setErrors(fieldErrors(e));
      setError(errorMessage(e, 'Kaydedilemedi.'));
      setBusy(false);
    }
  }

  return (
    <Screen>
      <Notice text={error} />

      <View style={styles.row}>
        <View style={{ flex: 1 }}>
          <Field label="Ad" value={f.first_name} onChangeText={set('first_name')} error={errors.first_name} autoComplete="given-name" />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Soyad" value={f.last_name} onChangeText={set('last_name')} error={errors.last_name} autoComplete="family-name" />
        </View>
      </View>
      <Field
        label="Cep telefonu"
        value={f.phone}
        onChangeText={set('phone')}
        keyboardType="phone-pad"
        autoComplete="tel"
        error={errors.phone}
      />
      <Field
        label="E-posta"
        value={f.email}
        onChangeText={set('email')}
        keyboardType="email-address"
        autoCapitalize="none"
        autoComplete="email"
        error={errors.email}
        hint="Giriş yaparken bu adresi kullanırsınız."
      />
      <SelectField
        label="İl"
        options={cityOptions}
        value={cityId}
        onChange={(v) => {
          setCityId(Number(v));
          setDistrictId(null);
        }}
        error={errors.city_id}
      />
      <SelectField
        label="İlçe"
        options={districtOptions}
        value={districtId}
        onChange={(v) => setDistrictId(Number(v))}
        placeholder={districts.isLoading ? 'Yükleniyor…' : 'İlçe seçin'}
        error={errors.district_id}
      />
      <Text style={styles.hint}>Ana sayfadaki restoranlar ve Kampanyalar bu ilçeye göre gösterilir.</Text>

      <View style={{ gap: space.sm }}>
        <Text style={styles.label}>Yorumlarda adınız nasıl görünsün?</Text>
        {(
          [
            { v: 'initials', t: initialsName(f.first_name, f.last_name), d: 'Adınız ve soyadınızın baş harfi' },
            { v: 'full', t: `${f.first_name.trim()} ${f.last_name.trim()}`, d: 'Adınız ve soyadınız' },
          ] as const
        ).map((o) => (
          <Pressable
            key={o.v}
            onPress={() => setMode(o.v)}
            style={[styles.radio, mode === o.v && styles.radioOn]}
            accessibilityRole="radio"
            accessibilityState={{ selected: mode === o.v }}
          >
            <View style={[styles.dot, mode === o.v && styles.dotOn]} />
            <View style={{ flex: 1 }}>
              <Text style={styles.radioTitle}>{o.t}</Text>
              <Text style={styles.radioDesc}>{o.d}</Text>
            </View>
          </Pressable>
        ))}
      </View>

      <Card style={{ backgroundColor: colors.line2 }}>
        <Text style={styles.fixed}>Müşteri numarası: {me?.customer_no}</Text>
        <Text style={styles.fixed}>Doğum tarihi: {date(me?.birth_date)}</Text>
        <Text style={styles.hint}>Bu bilgiler değiştirilemez. Doğum tarihiniz hatalıysa Kumbare destek ile iletişime geçin.</Text>
      </Card>

      <Button title="Kaydet" onPress={save} loading={busy} />
      <View style={{ height: space.lg }} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: space.sm },
  hint: { color: colors.gray, fontSize: font.xs + 1, lineHeight: 18 },
  label: { fontSize: font.sm, fontWeight: '600', color: colors.ink },
  radio: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.white,
    padding: space.md,
  },
  radioOn: { borderColor: colors.ink, borderWidth: 2 },
  dot: { width: 20, height: 20, borderRadius: 10, borderWidth: 2, borderColor: colors.ink3 },
  dotOn: { borderWidth: 6, borderColor: colors.ink },
  radioTitle: { fontSize: font.md, fontWeight: '700', color: colors.ink },
  radioDesc: { fontSize: font.sm, color: colors.gray },
  fixed: { fontSize: font.sm, color: colors.ink, fontWeight: '600' },
});
