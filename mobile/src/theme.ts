/**
 * Kumbare renkleri — web sitesi ve panellerle aynı aile ("adisyon + kumbara").
 * Mürekkep yeşili ana renk, kumbara sarısı ödül/para vurgusu, kâğıt arka plan.
 * Uygulama yalnızca açık temada çalışır (marka renkleri koyu temada okunaksızlaşıyor).
 */
export const colors = {
  ink: '#0e3b3a',
  ink2: '#1d5654',
  ink3: '#2a6b68',
  gold: '#f2b705',
  goldSoft: '#fdf3d1',
  goldDark: '#8a6500',
  paper: '#f6f7f4',
  white: '#ffffff',
  line: '#dce3de',
  line2: '#eef2ef',
  gray: '#56625f',
  error: '#a1262f',
  errorSoft: '#fbeaec',
  success: '#1e6b3f',
  successSoft: '#e5f3ea',
  info: '#1f4e8c',
  infoSoft: '#e6eff8',
} as const;

export const space = { xs: 4, sm: 8, md: 12, lg: 16, xl: 24, xxl: 32 } as const;

export const radius = { sm: 6, md: 10, lg: 14, pill: 999 } as const;

export const font = {
  xs: 12,
  sm: 14,
  md: 16,
  lg: 19,
  xl: 24,
  xxl: 32,
} as const;
