// Türkçe görüntüleme biçimleri. Tutarlar sunucudan metin olarak gelir ("1234.50"); hesap yapılmaz.

const MONTHS = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

/** "1234.5" → "1.234,50 TL" */
export function money(value: string | number | null | undefined, withCurrency = true): string {
  const n = Number(value ?? 0);
  const [int, dec] = Math.abs(n).toFixed(2).split('.');
  const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `${n < 0 ? '−' : ''}${grouped},${dec}${withCurrency ? ' TL' : ''}`;
}

/** "10.00" → "%10", "12.50" → "%12,5" */
export function rate(value: string | number | null | undefined): string {
  const n = Number(value ?? 0);
  return '%' + String(Number(n.toFixed(2))).replace('.', ',');
}

/** "2026-11-12" veya ISO → "12 Kasım 2026" */
export function date(value: string | null | undefined): string {
  if (!value) return '';
  const d = new Date(value.length === 10 ? value + 'T12:00:00' : value);
  if (isNaN(d.getTime())) return '';
  return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

export function dateTime(value: string | null | undefined): string {
  if (!value) return '';
  const d = new Date(value);
  if (isNaN(d.getTime())) return '';
  return `${date(value)} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

export const monthNames = MONTHS;

/** Puan iş planındaki gibi noktayla: "8.7" */
export function score(value: string | number | null | undefined): string {
  return value === null || value === undefined ? '–' : Number(value).toFixed(1);
}
