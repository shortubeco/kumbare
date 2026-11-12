// Giriş ekranı (oturum yokken uygulamanın açılış sayfası).
import { Link } from 'expo-router';
import { useState } from 'react';
import { Platform, StyleSheet, Text, View } from 'react-native';
import { Brand } from '@/components/brand';
import { InstallHint } from '@/components/install-hint';
import { Button, Field, Notice, Screen } from '@/components/ui';
import { api, ApiError } from '@/lib/api';
import { type Customer, useAuth } from '@/providers/auth';
import { colors, font, space } from '@/theme';

export default function LoginScreen() {
  const { signIn } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit() {
    setError(null);
    if (!email.trim() || !password) {
      setError('E-posta adresinizi ve şifrenizi yazın.');
      return;
    }
    setBusy(true);
    try {
      const res = await api<{ token: string; customer: Customer }>('/auth/login', {
        method: 'POST',
        auth: false,
        body: { email: email.trim(), password, device_name: Platform.OS === 'web' ? 'web' : Platform.OS },
      });
      await signIn(res.token, res.customer);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Giriş yapılamadı.');
      setBusy(false);
    }
  }

  return (
    <Screen>
      <View style={styles.hero}>
        <Brand />
        <Text style={styles.tagline}>Harcadıkça restoranın kumbarasında ödül biriktirin.</Text>
      </View>

      <InstallHint />

      <Notice text={error} />

      <Field
        label="E-posta"
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        autoComplete="email"
        keyboardType="email-address"
        textContentType="emailAddress"
        returnKeyType="next"
      />
      <Field
        label="Şifre"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
        autoComplete="current-password"
        textContentType="password"
        returnKeyType="go"
        onSubmitEditing={submit}
      />

      <Button title="Giriş yap" onPress={submit} loading={busy} />

      <Link href="/sifre-sifirla" style={styles.link}>
        Şifremi unuttum
      </Link>

      <View style={styles.sep} />

      <Text style={styles.muted}>Hesabınız yok mu?</Text>
      <Link href="/kayit" asChild>
        <Button title="Üye ol" variant="secondary" />
      </Link>
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: { alignItems: 'center', gap: space.md, paddingTop: space.xxl * 1.5, paddingBottom: space.lg },
  tagline: { fontSize: font.md, color: colors.gray, textAlign: 'center' },
  link: { color: colors.ink2, fontSize: font.sm, fontWeight: '600', textAlign: 'center', textDecorationLine: 'underline' },
  sep: { height: 1, backgroundColor: colors.line, marginVertical: space.sm },
  muted: { textAlign: 'center', color: colors.gray, fontSize: font.sm },
});
