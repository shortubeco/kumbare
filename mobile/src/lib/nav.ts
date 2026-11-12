// Geri dön; geçmiş yoksa (web'de sayfa doğrudan açıldıysa) verilen sayfaya geç.
import { type Href, router } from 'expo-router';

export function goBack(fallback: Href = '/ana-sayfa') {
  if (router.canGoBack()) router.back();
  else router.replace(fallback);
}
