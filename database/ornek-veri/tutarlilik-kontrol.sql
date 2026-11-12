\pset footer off
SELECT 'il basina sube (min/max)' AS kontrol, MIN(c)::text || ' / ' || MAX(c)::text AS sonuc FROM (SELECT city_id, COUNT(*) c FROM branches GROUP BY city_id) x
UNION ALL SELECT 'musteri', COUNT(*)::text FROM customers
UNION ALL SELECT 'bakiye != islem toplami', COUNT(*)::text FROM wallets w WHERE w.balance <> (SELECT COALESCE(SUM(amount),0) FROM wallet_transactions t WHERE t.wallet_id = w.id)
UNION ALL SELECT 'kazanilan toplam hatali', COUNT(*)::text FROM wallets w WHERE w.total_earned <> (SELECT COALESCE(SUM(amount),0) FROM wallet_transactions t WHERE t.wallet_id = w.id AND type='earn')
UNION ALL SELECT 'son balance_after != bakiye', COUNT(*)::text FROM wallets w WHERE w.balance <> (SELECT balance_after FROM wallet_transactions t WHERE t.wallet_id = w.id ORDER BY created_at DESC, id DESC LIMIT 1)
UNION ALL SELECT 'sayac != tekil musteri', COUNT(*)::text FROM monthly_branch_counters m WHERE m.unique_customers <> (SELECT COUNT(*) FROM monthly_branch_customers x WHERE x.branch_id=m.branch_id AND x.period=m.period)
UNION ALL SELECT 'en yuksek aylik sayac', MAX(unique_customers)::text FROM monthly_branch_counters
UNION ALL SELECT '10+ yorumlu sube', COUNT(*)::text FROM branches WHERE rating_count >= 10
UNION ALL SELECT 'ort. puan araligi', MIN(rating_avg)::text || ' - ' || MAX(rating_avg)::text FROM branches WHERE rating_count > 0
UNION ALL SELECT 'kazanmadan yorum', COUNT(*)::text FROM reviews r WHERE NOT EXISTS (SELECT 1 FROM wallet_transactions t WHERE t.branch_id=r.branch_id AND t.customer_id=r.customer_id AND t.type='earn')
UNION ALL SELECT 'aktif kampanya', COUNT(*)::text FROM campaigns WHERE status='active' AND starts_at <= now() AND ends_at > now()
UNION ALL SELECT 'dogum gunu kampanyasi', COUNT(*)::text FROM campaigns WHERE birthday_only
UNION ALL SELECT 'bekleyen onay', COUNT(*)::text FROM approval_requests WHERE status='pending'
UNION ALL SELECT 'musteri no sayaci', last_value::text FROM customer_no_counter
UNION ALL SELECT 'gelecek tarihli islem', COUNT(*)::text FROM wallet_transactions WHERE created_at > TIMESTAMPTZ '2026-11-11 12:00:00+03';
