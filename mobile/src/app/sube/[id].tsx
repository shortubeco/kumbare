import { useLocalSearchParams } from 'expo-router';
import { BranchScreen } from '@/screens/branch';

export default function BranchRoute() {
  const { id } = useLocalSearchParams<{ id: string }>();
  return <BranchScreen key={id} id={Number(id)} />;
}
