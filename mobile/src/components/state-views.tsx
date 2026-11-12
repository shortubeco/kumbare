// Liste ekranlarının boş / hata durumları.
import { StyleSheet, Text, View } from 'react-native';
import { Button } from '@/components/ui';
import { colors, font, space } from '@/theme';

export function EmptyState({ title, text, action }: { title: string; text?: string; action?: { title: string; onPress: () => void } }) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.title}>{title}</Text>
      {text ? <Text style={styles.text}>{text}</Text> : null}
      {action ? <Button title={action.title} variant="secondary" onPress={action.onPress} style={{ alignSelf: 'stretch' }} /> : null}
    </View>
  );
}

export function ErrorState({ message, onRetry }: { message?: string; onRetry: () => void }) {
  return (
    <EmptyState
      title="Yüklenemedi"
      text={message ?? 'Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edin.'}
      action={{ title: 'Tekrar dene', onPress: onRetry }}
    />
  );
}

const styles = StyleSheet.create({
  wrap: { alignItems: 'center', padding: space.xl, gap: space.md },
  title: { fontSize: font.lg, fontWeight: '700', color: colors.ink, textAlign: 'center' },
  text: { fontSize: font.md, color: colors.gray, textAlign: 'center', lineHeight: 22 },
});
