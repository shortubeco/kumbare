// Kayıttan hemen sonra: müşteri numarası büyük gösterilir.
import { router } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Brand } from '@/components/brand';
import { CustomerNumber } from '@/components/customer-number';
import { Button } from '@/components/ui';
import { useAuth } from '@/providers/auth';
import { colors, font, space } from '@/theme';

export default function WelcomeScreen() {
  const { customer } = useAuth();
  return (
    <SafeAreaView style={styles.screen}>
      <View style={styles.inner}>
        <Brand size={28} />
        <Text style={styles.title}>Hoş geldiniz{customer ? `, ${customer.first_name}` : ''}!</Text>
        <CustomerNumber number={customer?.customer_no} />
        <Text style={styles.text}>
          Kumbare&apos;ye katılan bir restoranda ödeme yaparken numaranızı söyleyin. Harcamanızın restoranın belirlediği oranı kadar ödül o
          restoranın kumbarasında birikir; bir sonraki gelişinizde kullanabilirsiniz.
        </Text>
        <Text style={styles.text}>Numaranızı istediğiniz zaman Profil sayfasında görebilirsiniz.</Text>
        <Button title="Başla" onPress={() => router.replace('/ana-sayfa')} />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.paper },
  inner: { flex: 1, padding: space.lg, gap: space.lg, justifyContent: 'center', width: '100%', maxWidth: 560, alignSelf: 'center' },
  title: { fontSize: font.xl, fontWeight: '800', color: colors.ink },
  text: { fontSize: font.md, color: colors.gray, lineHeight: 23 },
});
