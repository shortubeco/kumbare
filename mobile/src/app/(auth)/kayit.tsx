// Üye olma ekranı. Başarılı kayıtta müşteri numarası "Hoş geldiniz" ekranında gösterilir.
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useMemo, useState } from 'react';
import { Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { type Option, SelectField } from '@/components/select-field';
import { Button, Field, Notice, Screen } from '@/components/ui';
import { api, ApiError } from '@/lib/api';
import { monthNames } from '@/lib/format';
import { useCities, useDistricts } from '@/lib/queries';
import { KVKK_URL } from '@/lib/site';
import { type Customer, useAuth } from '@/providers/auth';
import { colors, font, radius, space } from '@/theme';

const pad = (n: number) => String(n).padStart(2, '0');
const daysIn = (y: number | null, m: number | null) => (m ? new Date(y ?? 2000, m, 0).getDate() : 31);

export default function RegisterScreen() {
  const { signIn } = useAuth();
  const [f, setF] = useState({ first_name: '', last_name: '', phone: '', email: '', password: '', password_confirmation: '' });
  const [cityId, setCityId] = useState<number | null>(null);
  const [districtId, setDistrictId] = useState<number | null>(null);
  const [day, setDay] = useState<number | null>(null);
  const [month, setMonth] = useState<number | null>(null);
  const [year, setYear] = useState<number | null>(null);
  const [consent, setConsent] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));

  const cities = useCities();
  const districts = useDistricts(cityId);

  const cityOptions: Option[] = useMemo(() => (cities.data ?? []).map((c) => ({ value: c.id, label: c.name })), [cities.data]);
  const districtOptions: Option[] = useMemo(() => (districts.data ?? []).map((d) => ({ value: d.id, label: d.name })), [districts.data]);

  const thisYear = new Date().getFullYear();
  const yearOptions: Option[] = useMemo(
    () => Array.from({ length: 100 }, (_, i) => thisYear - i).map((y) => ({ value: y, label: String(y) })),
    [thisYear],
  );
  const monthOptions: Option[] = monthNames.map((n, i) => ({ value: i + 1, label: n }));
  const dayOptions: Option[] = Array.from({ length: daysIn(year, month) }, (_, i) => ({ value: i + 1, label: String(i + 1) }));

  async function submit() {
    setError(null);
    const local: Record<string, string> = {};
    if (!day || !month || !year) local.birth_date = 'Doğum tarihinizi seçin.';
    if (!cityId) local.city_id = 'İl seçin.';
    else if (!districtId) local.district_id = 'İlçe seçin.';
    if (!consent) local.kvkk_consent = 'Devam etmek için aydınlatma metnini onaylayın.';
    if (f.password && f.password !== f.password_confirmation) local.password_confirmation = 'Şifreler aynı değil.';
    if (Object.keys(local).length) {
      setErrors(local);
      setError('Lütfen işaretli alanları kontrol edin.');
      return;
    }

    setErrors({});
    setBusy(true);
    try {
      const res = await api<{ token: string; customer: Customer }>('/auth/register', {
        method: 'POST',
        auth: false,
        body: {
          ...f,
          email: f.email.trim(),
          first_name: f.first_name.trim(),
          last_name: f.last_name.trim(),
          city_id: cityId,
          district_id: districtId,
          birth_date: `${year}-${pad(month!)}-${pad(day!)}`,
          kvkk_consent: true,
        },
      });
      await signIn(res.token, res.customer);
      router.replace('/hosgeldin');
    } catch (e) {
      if (e instanceof ApiError) {
        const fieldErrors: Record<string, string> = {};
        Object.entries(e.errors ?? {}).forEach(([k, v]) => (fieldErrors[k] = v[0]));
        setErrors(fieldErrors);
        setError(e.errors ? 'Lütfen işaretli alanları kontrol edin.' : e.message);
      } else {
        setError('Kayıt tamamlanamadı.');
      }
      setBusy(false);
    }
  }

  return (
    <Screen>
      <Text style={styles.lead}>Üye olunca size bir müşteri numarası verilir. Ödemede bu numarayı söylemeniz yeterli.</Text>

      <Notice text={error} />

      <View style={styles.row}>
        <View style={{ flex: 1 }}>
          <Field label="Ad" value={f.first_name} onChangeText={set('first_name')} autoComplete="given-name" error={errors.first_name} />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Soyad" value={f.last_name} onChangeText={set('last_name')} autoComplete="family-name" error={errors.last_name} />
        </View>
      </View>

      <Field
        label="Cep telefonu"
        value={f.phone}
        onChangeText={set('phone')}
        keyboardType="phone-pad"
        autoComplete="tel"
        placeholder="05XX XXX XX XX"
        error={errors.phone}
      />

      <SelectField
        label="İl"
        options={cityOptions}
        value={cityId}
        onChange={(v) => {
          setCityId(Number(v));
          setDistrictId(null);
        }}
        placeholder={cities.isLoading ? 'Yükleniyor…' : 'İl seçin'}
        error={errors.city_id ?? (cities.isError ? 'İller yüklenemedi. İnternet bağlantınızı kontrol edin.' : undefined)}
      />
      <SelectField
        label="İlçe"
        options={districtOptions}
        value={districtId}
        onChange={(v) => setDistrictId(Number(v))}
        placeholder={!cityId ? 'Önce il seçin' : districts.isLoading ? 'Yükleniyor…' : 'İlçe seçin'}
        disabled={!cityId}
        error={errors.district_id}
      />
      <Text style={styles.hintSmall}>Kampanyalar bu ilçeye göre gösterilir.</Text>

      <View style={{ gap: 6 }}>
        <View style={styles.row}>
          <SelectField compact label="Doğum günü" options={dayOptions} value={day} onChange={(v) => setDay(Number(v))} placeholder="Gün" />
          <SelectField
            compact
            label="Ay"
            options={monthOptions}
            value={month}
            onChange={(v) => {
              const m = Number(v);
              setMonth(m);
              if (day && day > daysIn(year, m)) setDay(null);
            }}
            placeholder="Ay"
          />
          <SelectField
            compact
            label="Yıl"
            options={yearOptions}
            value={year}
            onChange={(v) => {
              const y = Number(v);
              setYear(y);
              if (day && day > daysIn(y, month)) setDay(null);
            }}
            placeholder="Yıl"
          />
        </View>
        {errors.birth_date ? (
          <Text style={styles.err}>{errors.birth_date}</Text>
        ) : (
          <Text style={styles.hintSmall}>Doğum günü kampanyalarından yararlanmak için. Sonradan değiştirilemez.</Text>
        )}
      </View>

      <Field
        label="E-posta"
        value={f.email}
        onChangeText={set('email')}
        autoCapitalize="none"
        keyboardType="email-address"
        autoComplete="email"
        error={errors.email}
      />
      <Field
        label="Şifre"
        value={f.password}
        onChangeText={set('password')}
        secureTextEntry
        autoComplete="new-password"
        textContentType="newPassword"
        hint="En az 8 karakter."
        error={errors.password}
      />
      <Field
        label="Şifre (tekrar)"
        value={f.password_confirmation}
        onChangeText={set('password_confirmation')}
        secureTextEntry
        autoComplete="new-password"
        textContentType="newPassword"
        error={errors.password_confirmation}
      />

      <Pressable
        onPress={() => setConsent((c) => !c)}
        style={styles.consent}
        accessibilityRole="checkbox"
        accessibilityState={{ checked: consent }}
      >
        <View style={[styles.box, consent && styles.boxOn, !!errors.kvkk_consent && { borderColor: colors.error }]}>
          {consent ? <Text style={styles.tick}>✓</Text> : null}
        </View>
        <Text style={styles.consentText}>
          <Text
            style={styles.link}
            onPress={() => (Platform.OS === 'web' ? window.open(KVKK_URL, '_blank') : WebBrowser.openBrowserAsync(KVKK_URL))}
          >
            KVKK aydınlatma metnini
          </Text>{' '}
          okudum, kişisel verilerimin işlenmesini kabul ediyorum.
        </Text>
      </Pressable>
      {errors.kvkk_consent ? <Text style={styles.err}>{errors.kvkk_consent}</Text> : null}

      <Button title="Üye ol" onPress={submit} loading={busy} />
      <View style={{ height: space.lg }} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  lead: { color: colors.gray, fontSize: font.md, lineHeight: 22 },
  row: { flexDirection: 'row', gap: space.sm },
  hintSmall: { color: colors.gray, fontSize: font.xs + 1, marginTop: -space.sm },
  err: { color: colors.error, fontSize: font.xs + 1 },
  consent: { flexDirection: 'row', gap: space.md, alignItems: 'flex-start' },
  box: {
    width: 26,
    height: 26,
    borderRadius: radius.sm,
    borderWidth: 2,
    borderColor: colors.ink3,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  boxOn: { backgroundColor: colors.ink, borderColor: colors.ink },
  tick: { color: colors.gold, fontWeight: '900', fontSize: 16 },
  consentText: { flex: 1, color: colors.ink, fontSize: font.sm, lineHeight: 20 },
  link: { color: colors.ink2, fontWeight: '700', textDecorationLine: 'underline' },
});
