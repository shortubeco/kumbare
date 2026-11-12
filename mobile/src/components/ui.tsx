// Ortak arayüz parçaları: düğme, metin alanı, hata/bilgi kutusu, kart, sayfa kabı.
import { type ReactNode, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  type TextInputProps,
  View,
  type ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, font, radius, space } from '@/theme';

export function Screen({ children, scroll = true, padded = true }: { children: ReactNode; scroll?: boolean; padded?: boolean }) {
  const inner = <View style={[styles.inner, padded && styles.padded]}>{children}</View>;
  return (
    <SafeAreaView style={styles.screen} edges={['bottom', 'left', 'right']}>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        {scroll ? (
          <ScrollView contentContainerStyle={{ flexGrow: 1 }} keyboardShouldPersistTaps="handled">
            {inner}
          </ScrollView>
        ) : (
          inner
        )}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

type ButtonProps = {
  title: string;
  onPress?: () => void;
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  loading?: boolean;
  disabled?: boolean;
  style?: ViewStyle;
};

export function Button({ title, onPress, variant = 'primary', loading, disabled, style }: ButtonProps) {
  const off = disabled || loading;
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: !!off, busy: !!loading }}
      onPress={off ? undefined : onPress}
      style={({ pressed }) => [styles.btn, styles[`btn_${variant}`], off && styles.btnOff, pressed && !off && styles.btnPressed, style]}
    >
      {loading ? (
        <ActivityIndicator color={variant === 'primary' ? colors.ink : colors.ink} />
      ) : (
        <Text style={[styles.btnText, styles[`btnText_${variant}`]]}>{title}</Text>
      )}
    </Pressable>
  );
}

type FieldProps = TextInputProps & { label: string; error?: string; hint?: string };

export function Field({ label, error, hint, secureTextEntry, ...rest }: FieldProps) {
  const [hidden, setHidden] = useState(!!secureTextEntry);
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <View>
        <TextInput
          placeholderTextColor="#8a9592"
          style={[styles.input, !!error && styles.inputError, secureTextEntry && { paddingRight: 64 }]}
          secureTextEntry={hidden}
          {...rest}
        />
        {secureTextEntry ? (
          <Pressable onPress={() => setHidden((h) => !h)} style={styles.eye} accessibilityRole="button" hitSlop={8}>
            <Text style={styles.eyeText}>{hidden ? 'Göster' : 'Gizle'}</Text>
          </Pressable>
        ) : null}
      </View>
      {error ? <Text style={styles.error}>{error}</Text> : hint ? <Text style={styles.hint}>{hint}</Text> : null}
    </View>
  );
}

export function Notice({ text, tone = 'error' }: { text?: string | null; tone?: 'error' | 'success' | 'info' }) {
  if (!text) return null;
  const bg = tone === 'error' ? colors.errorSoft : tone === 'success' ? colors.successSoft : colors.infoSoft;
  const fg = tone === 'error' ? colors.error : tone === 'success' ? colors.success : colors.info;
  return (
    <View style={[styles.notice, { backgroundColor: bg, borderColor: fg + '33' }]} accessibilityRole="alert">
      <Text style={{ color: fg, fontSize: font.sm, lineHeight: 20 }}>{text}</Text>
    </View>
  );
}

export function Card({ children, style }: { children: ReactNode; style?: ViewStyle }) {
  return <View style={[styles.card, style]}>{children}</View>;
}

export function Loading() {
  return (
    <View style={styles.center}>
      <ActivityIndicator size="large" color={colors.ink} />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper },
  inner: { flex: 1, width: '100%', maxWidth: 560, alignSelf: 'center' },
  padded: { padding: space.lg, gap: space.lg },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.paper },
  btn: {
    minHeight: 50,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: space.lg,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  btn_primary: { backgroundColor: colors.gold, borderColor: colors.gold },
  btn_secondary: { backgroundColor: colors.white, borderColor: colors.ink },
  btn_ghost: { backgroundColor: 'transparent' },
  btn_danger: { backgroundColor: colors.white, borderColor: colors.error },
  btnOff: { opacity: 0.55 },
  btnPressed: { opacity: 0.8 },
  btnText: { fontSize: font.md, fontWeight: '700' },
  btnText_primary: { color: colors.ink },
  btnText_secondary: { color: colors.ink },
  btnText_ghost: { color: colors.ink2, textDecorationLine: 'underline' },
  btnText_danger: { color: colors.error },
  field: { gap: 6 },
  label: { fontSize: font.sm, fontWeight: '600', color: colors.ink },
  input: {
    minHeight: 50,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.white,
    paddingHorizontal: space.md,
    fontSize: font.md,
    color: colors.ink,
  },
  inputError: { borderColor: colors.error },
  eye: { position: 'absolute', right: 12, top: 0, bottom: 0, justifyContent: 'center' },
  eyeText: { color: colors.ink2, fontWeight: '600', fontSize: font.sm },
  error: { color: colors.error, fontSize: font.xs + 1 },
  hint: { color: colors.gray, fontSize: font.xs + 1 },
  notice: { borderWidth: 1, borderRadius: radius.md, padding: space.md },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.line,
    padding: space.lg,
    gap: space.sm,
  },
});
