import Ionicons from '@expo/vector-icons/Ionicons';
import { Link, Tabs } from 'expo-router';
import { type ComponentProps } from 'react';
import { type ColorValue, Pressable, StyleSheet, Text, View } from 'react-native';
import { useApprovals } from '@/lib/queries';
import { colors } from '@/theme';

type IconName = ComponentProps<typeof Ionicons>['name'];

function icon(name: IconName, active: IconName) {
  function TabIcon({ color, focused, size }: { color: ColorValue; focused: boolean; size: number }) {
    return <Ionicons name={focused ? active : name} size={size} color={color as string} />;
  }
  return TabIcon;
}

// Üst çubuktaki Kampanyalar ve Bildirimler düğmeleri (her sekmede).
function HeaderActions() {
  const approvals = useApprovals();
  const pending = approvals.data?.length ?? 0;
  return (
    <View style={{ flexDirection: 'row', gap: 18, marginRight: 16 }}>
      <Link href="/kampanyalar" asChild>
        <Pressable accessibilityRole="button" accessibilityLabel="Kampanyalar" hitSlop={8}>
          <Ionicons name="pricetags-outline" size={23} color={colors.white} />
        </Pressable>
      </Link>
      <Link href="/bildirimler" asChild>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={pending ? `Bildirimler, onay bekleyen istek sayısı ${pending}` : 'Bildirimler'}
          hitSlop={8}
        >
          <Ionicons name={pending ? 'notifications' : 'notifications-outline'} size={23} color={pending ? colors.gold : colors.white} />
          {pending ? (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{pending > 9 ? '9+' : pending}</Text>
            </View>
          ) : null}
        </Pressable>
      </Link>
    </View>
  );
}

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        headerStyle: { backgroundColor: colors.ink },
        headerTintColor: colors.white,
        headerTitleStyle: { fontWeight: '700' },
        headerRight: () => <HeaderActions />,
        tabBarActiveTintColor: colors.ink,
        tabBarInactiveTintColor: '#7b8784',
        tabBarStyle: { backgroundColor: colors.white, borderTopColor: colors.line },
        tabBarLabelStyle: { fontSize: 12, fontWeight: '600' },
        sceneStyle: { backgroundColor: colors.paper },
      }}
    >
      <Tabs.Screen name="ana-sayfa" options={{ title: 'Ana sayfa', tabBarIcon: icon('home-outline', 'home') }} />
      <Tabs.Screen name="kesfet" options={{ title: 'Keşfet', tabBarIcon: icon('compass-outline', 'compass') }} />
      <Tabs.Screen name="kumbaram" options={{ title: 'Kumbaram', tabBarIcon: icon('wallet-outline', 'wallet') }} />
      <Tabs.Screen name="profil" options={{ title: 'Profil', tabBarIcon: icon('person-outline', 'person') }} />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  badge: {
    position: 'absolute',
    top: -6,
    right: -9,
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    paddingHorizontal: 4,
    backgroundColor: colors.gold,
    borderWidth: 2,
    borderColor: colors.ink,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: { color: colors.ink, fontSize: 10, fontWeight: '900' },
});
