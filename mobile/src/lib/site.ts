// Herkese açık web sitesi adresleri (KVKK metni, şube sayfaları).
export const SITE_URL = (process.env.EXPO_PUBLIC_SITE_URL ?? 'https://kumbare.trthabercanli.com').replace(/\/$/, '');

export const KVKK_URL = `${SITE_URL}/kvkk-aydinlatma-metni`;
