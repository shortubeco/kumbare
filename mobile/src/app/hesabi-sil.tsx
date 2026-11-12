/**
 * Hesabımı sil. Kişisel bilgiler silinir (anonimleştirilir), tüm kumbaralardaki ödüller kullanılamaz hale gelir,
 * müşteri numarası başkasına verilmez. Şifre ve onay kutusu zorunlu.
 */
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Button, Card, Field, Notice, Screen } from '@/components/ui';
import { api } from '@/lib/api';
import { confirm } from '@/lib/confirm';
import { errorMessage, fieldErrors } from '@/lib/form-errors';
import { money } from '@/lib/format';
import { useWallets } from '@/lib/queries';
import { useAuth } from '@/providers/auth';
import { colors, font, radius, space } from '@/theme';

export default function DeleteAccountScreen() {
  const { signOut } = useAuth();
  const wallets = useWallets();
  const [password, setPassword] = useState('');
  const [agree, setAgree] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const totalCents = (wallets.data ?? []).reduce((s, w) => s + Math.round(Number(w.balance) * 100), 0);

  async function remove() {
    setError(null);
    if (!password) {
      setErrors({ password: 'Şifrenizi yazın.' });
      return;
    }
    if (!agree) {
      setError('Devam etmek için onay kutusunu işaretleyin.');
      return;
    }
    const ok = await confirm('Hesabınız silinecek', 'Bu işlem geri alınamaz. Devam etmek istiyor musunuz?', 'Hesabımı sil', true);
    if (!ok) return;
    setErrors({});
    setBusy(true);
    try {
      await api('/me', { method: 'DELETE', body: { password } });
      await signOut();
    } catch (e) {
      setErrors(fieldErrors(e));
      setError(errorMessage(e, 'Hesap silinemedi.'));
      setBusy(false);
    }
  }

  return (
    <Screen>
      <Card style={{ borderColor: colors.error }}>
        <Text style={styles.h}>Hesabınızı silerseniz</Text>
        <Text style={styles.li}>• Adınız, telefonunuz, e-postanız ve doğum tarihiniz silinir.</Text>
        <Text style={styles.li}>• Tüm kumbaralarınızdaki ödüller kullanılamaz hale gelir ve geri verilmez.</Text>
        <Text style={styles.li}>• Müşteri numaranız başka birine verilmez; aynı numarayla geri dönemezsiniz.</Text>
        <Text style={styles.li}>• Geçmiş işlem kayıtları silinmez; adınız olmadan saklanır.</Text>
        <Text style={styles.li}>• Bu işlem geri alınamaz.</Text>
      </Card>

      {totalCents > 0 ? (
        <Notice
          text={`Kumbaralarınızda toplam ${money(totalCents / 100)} ödül var. Silmeden önce restoranlarda kullanmanızı öneririz.`}
          tone="info"
        />
      ) : null}

      <Notice text={error} />

      <Field
        label="Şifreniz"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
        autoComplete="current-password"
        error={errors.password}
      />

      <Pressable
        onPress={() => setAgree((a) => !a)}
        style={styles.consent}
        accessibilityRole="checkbox"
        accessibilityState={{ checked: agree }}
      >
        <View style={[styles.box, agree && styles.boxOn]}>{agree ? <Text style={styles.tick}>✓</Text> : null}</View>
        <Text style={styles.consentText}>Yukarıdakileri okudum; hesabımın ve ödüllerimin kalıcı olarak silineceğini anlıyorum.</Text>
      </Pressable>

      <Button title="Hesabımı kalıcı olarak sil" variant="danger" onPress={remove} loading={busy} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  h: { fontSize: font.md, fontWeight: '700', color: colors.error },
  li: { fontSize: font.sm, color: colors.ink, lineHeight: 21 },
  consent: { flexDirection: 'row', gap: space.md, alignItems: 'flex-start' },
  box: {
    width: 26,
    height: 26,
    borderRadius: radius.sm,
    borderWidth: 2,
    borderColor: colors.error,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  boxOn: { backgroundColor: colors.error },
  tick: { color: colors.white, fontWeight: '900', fontSize: 16 },
  consentText: { flex: 1, color: colors.ink, fontSize: font.sm, lineHeight: 20 },
});
