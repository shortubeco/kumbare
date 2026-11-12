// Şifre değiştir. Başarılı olunca diğer cihazlardaki oturumlar kapanır (bu cihaz açık kalır).
import { goBack } from '@/lib/nav';
import { useState } from 'react';
import { Text } from 'react-native';
import { Button, Field, Notice, Screen } from '@/components/ui';
import { api } from '@/lib/api';
import { errorMessage, fieldErrors } from '@/lib/form-errors';
import { colors, font } from '@/theme';

export default function ChangePasswordScreen() {
  const [f, setF] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const set = (k: keyof typeof f) => (v: string) => setF((s) => ({ ...s, [k]: v }));

  async function save() {
    setError(null);
    if (f.password !== f.password_confirmation) {
      setErrors({ password_confirmation: 'Şifreler aynı değil.' });
      return;
    }
    setErrors({});
    setBusy(true);
    try {
      const res = await api<{ message: string }>('/me/password', { method: 'PUT', body: f });
      setDone(res.message);
    } catch (e) {
      setErrors(fieldErrors(e));
      setError(errorMessage(e, 'Şifre değiştirilemedi.'));
    } finally {
      setBusy(false);
    }
  }

  if (done) {
    return (
      <Screen>
        <Notice text={`${done} Diğer cihazlardaki oturumlarınız kapatıldı.`} tone="success" />
        <Button title="Profile dön" onPress={() => goBack('/profil')} />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={{ color: colors.gray, fontSize: font.md, lineHeight: 22 }}>
        Yeni şifreniz en az 8 karakter olmalı. Şifre değişince diğer cihazlardaki oturumlarınız kapanır.
      </Text>
      <Notice text={error} />
      <Field
        label="Mevcut şifre"
        value={f.current_password}
        onChangeText={set('current_password')}
        secureTextEntry
        autoComplete="current-password"
        error={errors.current_password}
      />
      <Field
        label="Yeni şifre"
        value={f.password}
        onChangeText={set('password')}
        secureTextEntry
        autoComplete="new-password"
        error={errors.password}
      />
      <Field
        label="Yeni şifre (tekrar)"
        value={f.password_confirmation}
        onChangeText={set('password_confirmation')}
        secureTextEntry
        autoComplete="new-password"
        error={errors.password_confirmation}
        onSubmitEditing={save}
      />
      <Button title="Şifreyi değiştir" onPress={save} loading={busy} />
    </Screen>
  );
}
