// Şifre sıfırlama isteği. Bağlantı e-postayla gelir; yeni şifre web sitesinde belirlenir.
import { goBack } from '@/lib/nav';
import { useState } from 'react';
import { Text } from 'react-native';
import { Button, Field, Notice, Screen } from '@/components/ui';
import { api, ApiError } from '@/lib/api';
import { colors, font } from '@/theme';

export default function ForgotPasswordScreen() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit() {
    setError(null);
    if (!email.trim()) {
      setError('E-posta adresinizi yazın.');
      return;
    }
    setBusy(true);
    try {
      const res = await api<{ message: string }>('/auth/forgot-password', { method: 'POST', auth: false, body: { email: email.trim() } });
      setDone(res.message);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'İstek gönderilemedi.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <Screen>
      <Text style={{ color: colors.gray, fontSize: font.md, lineHeight: 22 }}>
        Üye olurken yazdığınız e-posta adresini girin. Şifrenizi yenilemeniz için bir bağlantı göndereceğiz.
      </Text>
      <Notice text={error} />
      <Notice text={done} tone="success" />
      {done ? (
        <Button title="Giriş ekranına dön" onPress={() => goBack('/giris')} />
      ) : (
        <>
          <Field
            label="E-posta"
            value={email}
            onChangeText={setEmail}
            autoCapitalize="none"
            autoComplete="email"
            keyboardType="email-address"
            onSubmitEditing={submit}
          />
          <Button title="Bağlantı gönder" onPress={submit} loading={busy} />
        </>
      )}
    </Screen>
  );
}
