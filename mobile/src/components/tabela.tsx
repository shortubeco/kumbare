// Fotoğrafı olmayan restoranlar için "tabela": mutfağa göre renklenen, baş harfleri taşıyan yüzey (sitedeki gibi).
import { Image } from 'expo-image';
import { type ImageStyle, StyleSheet, Text, View, type ViewStyle } from 'react-native';
import { radius } from '@/theme';

const TONES = [
  { bg: '#0e3b3a', fg: '#f2e7c9' }, // mürekkep
  { bg: '#f2b705', fg: '#0e3b3a' }, // kumbara
  { bg: '#1f4e8c', fg: '#f6f7f4' }, // çini
  { bg: '#8e2c3a', fg: '#f8e9e2' }, // nar
  { bg: '#55632a', fg: '#f3f0d8' }, // zeytin
  { bg: '#e9dfc8', fg: '#0e3b3a' }, // kum
];

const upper = (t: string) => t.replace(/i/g, 'İ').replace(/ı/g, 'I').toLocaleUpperCase('tr-TR');

export function initials(name: string): string {
  const words = name.trim().split(/\s+/);
  return upper(words.length >= 2 ? words[0].slice(0, 1) + words[1].slice(0, 1) : (words[0] ?? '').slice(0, 2));
}

export function tone(categoryId: number | null | undefined) {
  return TONES[(categoryId ?? 0) % TONES.length];
}

type Props = { name: string; categoryId?: number | null; size?: number; photo?: string | null; style?: ViewStyle; label?: string };

export function Tabela({ name, categoryId, size = 56, photo, style, label }: Props) {
  const t = tone(categoryId);
  if (photo) {
    return (
      <Image
        source={{ uri: photo }}
        style={[{ width: size, height: size, borderRadius: radius.md }, style as ImageStyle]}
        contentFit="cover"
      />
    );
  }
  return (
    <View style={[styles.box, { width: size, height: size, backgroundColor: t.bg }, style]} accessibilityElementsHidden>
      <View style={[styles.inner, { borderColor: t.fg + '55' }]}>
        <Text style={[styles.text, { color: t.fg, fontSize: label ? 22 : Math.min(size * 0.3, 40) }]} numberOfLines={label ? 2 : 1}>
          {label ?? initials(name)}
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  box: { borderRadius: radius.md, padding: 4 },
  inner: { flex: 1, borderWidth: 1, borderRadius: radius.sm, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 2 },
  text: { fontWeight: '900', letterSpacing: 1, textAlign: 'center' },
});
