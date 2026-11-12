-- =====================================================================
-- KUMBARE - Örnek verileri silme
--
-- 01..08-ornek-veri.sql ile eklenen örnek işletmeleri, şubeleri, panel
-- kullanıcılarını, müşterileri ve tüm ilişkili kayıtları siler.
-- Gerçek kayıtlara dokunmaz:
--   * örnek işletmeler: yöneticisi isletme<N>@example.com olanlar
--   * örnek müşteriler: e-postası musteri<N>@example.com olanlar
-- Kategoriler silinmez (gerçek kullanımda da işe yarar).
--
-- Tabloların sahibi olan kullanıcıyla (phpPgAdmin'deki ana kullanıcı) çalıştırın.
-- =====================================================================

BEGIN;

CREATE TEMP TABLE _ornek_isletme ON COMMIT DROP AS
    SELECT DISTINCT business_id AS id FROM panel_users
    WHERE role = 'business_owner' AND email ~ '^isletme[0-9]+@example\.com$';

CREATE TEMP TABLE _ornek_sube ON COMMIT DROP AS
    SELECT id FROM branches WHERE business_id IN (SELECT id FROM _ornek_isletme);

CREATE TEMP TABLE _ornek_musteri ON COMMIT DROP AS
    SELECT id FROM customers WHERE email ~ '^musteri[0-9]+@example\.com$';

CREATE TEMP TABLE _ornek_panel ON COMMIT DROP AS
    SELECT id FROM panel_users
    WHERE business_id IN (SELECT id FROM _ornek_isletme) OR branch_id IN (SELECT id FROM _ornek_sube);

CREATE TEMP TABLE _ornek_kumbara ON COMMIT DROP AS
    SELECT id FROM wallets
    WHERE branch_id IN (SELECT id FROM _ornek_sube) OR customer_id IN (SELECT id FROM _ornek_musteri);

-- Değiştirilemezlik korumaları yalnızca bu işlem süresince kapatılır.
ALTER TABLE wallet_transactions DISABLE TRIGGER wallet_transactions_no_update;
ALTER TABLE customers DISABLE TRIGGER customers_no_delete;
ALTER TABLE approval_requests DROP CONSTRAINT approval_requests_related_tx_fk;

DELETE FROM review_reports WHERE review_id IN (
    SELECT id FROM reviews WHERE branch_id IN (SELECT id FROM _ornek_sube) OR customer_id IN (SELECT id FROM _ornek_musteri));
DELETE FROM review_replies WHERE review_id IN (
    SELECT id FROM reviews WHERE branch_id IN (SELECT id FROM _ornek_sube) OR customer_id IN (SELECT id FROM _ornek_musteri));
DELETE FROM reviews WHERE branch_id IN (SELECT id FROM _ornek_sube) OR customer_id IN (SELECT id FROM _ornek_musteri);
DELETE FROM favorites WHERE branch_id IN (SELECT id FROM _ornek_sube) OR customer_id IN (SELECT id FROM _ornek_musteri);

DELETE FROM monthly_branch_customers WHERE branch_id IN (SELECT id FROM _ornek_sube) OR customer_id IN (SELECT id FROM _ornek_musteri);
DELETE FROM monthly_branch_counters WHERE branch_id IN (SELECT id FROM _ornek_sube);

DELETE FROM wallet_transactions WHERE wallet_id IN (SELECT id FROM _ornek_kumbara);
DELETE FROM approval_requests WHERE wallet_id IN (SELECT id FROM _ornek_kumbara);
DELETE FROM wallets WHERE id IN (SELECT id FROM _ornek_kumbara);

DELETE FROM campaigns WHERE branch_id IN (SELECT id FROM _ornek_sube);
DELETE FROM sponsorships WHERE branch_id IN (SELECT id FROM _ornek_sube);
DELETE FROM subscriptions WHERE branch_id IN (SELECT id FROM _ornek_sube);
DELETE FROM payment_notifications WHERE branch_id IN (SELECT id FROM _ornek_sube);

DELETE FROM panel_notifications WHERE panel_user_id IN (SELECT id FROM _ornek_panel);
DELETE FROM staff_permissions WHERE panel_user_id IN (SELECT id FROM _ornek_panel);
DELETE FROM reward_rate_history WHERE branch_id IN (SELECT id FROM _ornek_sube);
DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\Models\PanelUser' AND tokenable_id IN (SELECT id FROM _ornek_panel);
DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\Models\Customer' AND tokenable_id IN (SELECT id FROM _ornek_musteri);
DELETE FROM panel_users WHERE id IN (SELECT id FROM _ornek_panel);

DELETE FROM branch_hours WHERE branch_id IN (SELECT id FROM _ornek_sube);
DELETE FROM branch_photos WHERE branch_id IN (SELECT id FROM _ornek_sube);
DELETE FROM branches WHERE id IN (SELECT id FROM _ornek_sube);
DELETE FROM menu_items WHERE business_id IN (SELECT id FROM _ornek_isletme);
DELETE FROM menu_categories WHERE business_id IN (SELECT id FROM _ornek_isletme);
DELETE FROM businesses WHERE id IN (SELECT id FROM _ornek_isletme);

DELETE FROM customer_password_reset_tokens WHERE email ~ '^musteri[0-9]+@example\.com$';
DELETE FROM customers WHERE id IN (SELECT id FROM _ornek_musteri);

ALTER TABLE approval_requests
    ADD CONSTRAINT approval_requests_related_tx_fk
    FOREIGN KEY (related_transaction_id) REFERENCES wallet_transactions(id);
ALTER TABLE customers ENABLE TRIGGER customers_no_delete;
ALTER TABLE wallet_transactions ENABLE TRIGGER wallet_transactions_no_update;

-- Not: Müşteri numaraları tekrar kullanılmaz kuralı gereği sayaç geri alınmaz;
-- ilk gerçek müşteri 2001 numarasını alır. Gerçek yayına geçmeden önce
-- numaraların 1'den başlamasını istiyorsanız ve başka müşteri YOKSA
-- aşağıdaki üç satırın başındaki "--" işaretlerini kaldırın:
-- ALTER TABLE customer_no_counter DISABLE TRIGGER customer_no_counter_no_decrease;
-- UPDATE customer_no_counter SET last_value = 0 WHERE id = 1 AND NOT EXISTS (SELECT 1 FROM customers);
-- ALTER TABLE customer_no_counter ENABLE TRIGGER customer_no_counter_no_decrease;

COMMIT;
