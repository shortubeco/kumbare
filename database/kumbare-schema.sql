-- =====================================================================
-- KUMBARE - PostgreSQL Veritabanı Şeması
-- Kaynak: teknik-tasarim-db.md
-- Gereksinim: PostgreSQL 10+
--
-- Kullanım:
--   Boş bir veritabanında tek seferde çalıştırın (phpPgAdmin / pgAdmin
--   SQL penceresi veya: psql -U kullanici -d veritabani -f kumbare-schema.sql)
--   Tüm komutlar tek bir transaction içindedir: bir hata olursa hiçbir
--   tablo oluşturulmaz.
--
-- Not: İl/ilçe verileri ve ilk platform yöneticisi hesabı bu dosyada
--      yoktur (ayrı seed dosyasıyla yüklenecek).
-- =====================================================================

BEGIN;

SET client_encoding = 'UTF8';

-- =====================================================================
-- 1. COĞRAFYA VE KATEGORİ
-- =====================================================================

CREATE TABLE cities (
    id          BIGSERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    plate_code  SMALLINT UNIQUE CHECK (plate_code BETWEEN 1 AND 81),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE districts (
    id          BIGSERIAL PRIMARY KEY,
    city_id     BIGINT NOT NULL REFERENCES cities(id),
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (city_id, slug)
);

CREATE TABLE categories (
    id          BIGSERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- =====================================================================
-- 2. AYARLAR VE PAKETLER
-- =====================================================================

CREATE TABLE settings (
    key         VARCHAR(100) PRIMARY KEY,
    value       TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE plans (
    id             BIGSERIAL PRIMARY KEY,
    code           VARCHAR(20) NOT NULL UNIQUE CHECK (code IN ('free', 'pro')),
    name           VARCHAR(100) NOT NULL,
    description    TEXT,
    monthly_price  NUMERIC(10,2) CHECK (monthly_price >= 0),
    yearly_price   NUMERIC(10,2) CHECK (yearly_price >= 0),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- =====================================================================
-- 3. MÜŞTERİLER
-- =====================================================================

-- Boşluksuz müşteri numarası üretimi için tek satırlı sayaç.
-- Kayıt sırasında FOR UPDATE ile kilitlenir (bkz. teknik-tasarim-db.md 4.1).
CREATE TABLE customer_no_counter (
    id          SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    last_value  BIGINT NOT NULL DEFAULT 0 CHECK (last_value >= 0)
);

CREATE TABLE customers (
    id                 BIGSERIAL PRIMARY KEY,
    customer_no        BIGINT NOT NULL UNIQUE CHECK (customer_no >= 1),
    first_name         VARCHAR(100) NOT NULL,
    last_name          VARCHAR(100) NOT NULL,
    phone              VARCHAR(20)  NOT NULL UNIQUE,
    email              VARCHAR(255) NOT NULL,
    password           VARCHAR(255) NOT NULL,
    city_id            BIGINT NOT NULL REFERENCES cities(id),
    district_id        BIGINT NOT NULL REFERENCES districts(id),
    birth_date         DATE NOT NULL CHECK (birth_date >= DATE '1900-01-01'),
    display_name_mode  VARCHAR(10) NOT NULL DEFAULT 'initials'
                       CHECK (display_name_mode IN ('initials', 'full')),
    kvkk_consent_at    TIMESTAMPTZ NOT NULL,
    status             VARCHAR(10) NOT NULL DEFAULT 'active'
                       CHECK (status IN ('active', 'passive')),
    deleted_at         TIMESTAMPTZ,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- E-posta büyük/küçük harf duyarsız benzersiz
CREATE UNIQUE INDEX customers_email_lower_unique ON customers (lower(email));

-- =====================================================================
-- 4. İŞLETME, ŞUBE, PANEL KULLANICILARI
-- =====================================================================

CREATE TABLE businesses (
    id                BIGSERIAL PRIMARY KEY,
    name              VARCHAR(150) NOT NULL,
    slug              VARCHAR(170) NOT NULL UNIQUE,
    logo_path         VARCHAR(255),
    description       TEXT,
    category_id       BIGINT REFERENCES categories(id),
    status            VARCHAR(10) NOT NULL DEFAULT 'pending'
                      CHECK (status IN ('pending', 'approved', 'rejected', 'suspended', 'closed')),
    legal_name        VARCHAR(255),
    tax_office        VARCHAR(100),
    tax_number        VARCHAR(20),
    rejection_reason  TEXT,
    approved_at       TIMESTAMPTZ,
    approved_by       BIGINT,          -- FK panel_users tablosundan sonra eklenir
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE branches (
    id                BIGSERIAL PRIMARY KEY,
    business_id       BIGINT NOT NULL REFERENCES businesses(id),
    name              VARCHAR(150) NOT NULL,
    slug              VARCHAR(170) NOT NULL,
    city_id           BIGINT NOT NULL REFERENCES cities(id),
    district_id       BIGINT NOT NULL REFERENCES districts(id),
    address           TEXT NOT NULL,
    latitude          NUMERIC(10,7) CHECK (latitude  BETWEEN -90  AND 90),
    longitude         NUMERIC(10,7) CHECK (longitude BETWEEN -180 AND 180),
    phone             VARCHAR(20),
    email             VARCHAR(255),
    description       TEXT,
    reward_rate       NUMERIC(5,2) NOT NULL DEFAULT 0
                      CHECK (reward_rate BETWEEN 0 AND 100),
    reward_terms      TEXT,
    status            VARCHAR(10) NOT NULL DEFAULT 'active'
                      CHECK (status IN ('active', 'passive', 'deleted')),
    rating_avg        NUMERIC(3,1) CHECK (rating_avg BETWEEN 1 AND 10),
    rating_count      INTEGER NOT NULL DEFAULT 0 CHECK (rating_count >= 0),
    popularity_score  INTEGER NOT NULL DEFAULT 0 CHECK (popularity_score >= 0),
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (business_id, slug)
);

CREATE TABLE panel_users (
    id              BIGSERIAL PRIMARY KEY,
    role            VARCHAR(20) NOT NULL
                    CHECK (role IN ('platform_admin', 'business_owner', 'branch_manager', 'staff')),
    business_id     BIGINT REFERENCES businesses(id),
    branch_id       BIGINT REFERENCES branches(id),
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    phone           VARCHAR(20),
    password        VARCHAR(255) NOT NULL,
    remember_token  VARCHAR(100),
    status          VARCHAR(10) NOT NULL DEFAULT 'active'
                    CHECK (status IN ('active', 'passive')),
    last_login_at   TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    -- Rol ile işletme/şube bağlantısı tutarlı olmalı
    CONSTRAINT panel_users_role_scope CHECK (
        (role = 'platform_admin' AND business_id IS NULL     AND branch_id IS NULL)
     OR (role = 'business_owner' AND business_id IS NOT NULL AND branch_id IS NULL)
     OR (role IN ('branch_manager', 'staff') AND business_id IS NOT NULL AND branch_id IS NOT NULL)
    )
);

CREATE UNIQUE INDEX panel_users_email_lower_unique ON panel_users (lower(email));

-- Her işletmede tek işletme yöneticisi
CREATE UNIQUE INDEX panel_users_one_owner_per_business
    ON panel_users (business_id) WHERE role = 'business_owner';

-- Her şubede tek aktif şube yöneticisi
CREATE UNIQUE INDEX panel_users_one_manager_per_branch
    ON panel_users (branch_id) WHERE role = 'branch_manager' AND status = 'active';

ALTER TABLE businesses
    ADD CONSTRAINT businesses_approved_by_fk
    FOREIGN KEY (approved_by) REFERENCES panel_users(id);

CREATE TABLE staff_permissions (
    id             BIGSERIAL PRIMARY KEY,
    panel_user_id  BIGINT NOT NULL REFERENCES panel_users(id) ON DELETE CASCADE,
    permission     VARCHAR(30) NOT NULL
                   CHECK (permission IN ('lookup_customer', 'earn', 'redeem', 'view_customers', 'view_reports')),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (panel_user_id, permission)
);

CREATE TABLE branch_hours (
    id          BIGSERIAL PRIMARY KEY,
    branch_id   BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    weekday     SMALLINT NOT NULL CHECK (weekday BETWEEN 1 AND 7),  -- 1 = Pazartesi
    opens_at    TIME,
    closes_at   TIME,
    is_closed   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (branch_id, weekday),
    CHECK (is_closed OR (opens_at IS NOT NULL AND closes_at IS NOT NULL))
);

CREATE TABLE branch_photos (
    id          BIGSERIAL PRIMARY KEY,
    branch_id   BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    path        VARCHAR(255) NOT NULL,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE reward_rate_history (
    id          BIGSERIAL PRIMARY KEY,
    branch_id   BIGINT NOT NULL REFERENCES branches(id),
    old_rate    NUMERIC(5,2) NOT NULL CHECK (old_rate BETWEEN 0 AND 100),
    new_rate    NUMERIC(5,2) NOT NULL CHECK (new_rate BETWEEN 0 AND 100),
    changed_by  BIGINT NOT NULL REFERENCES panel_users(id),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE favorites (
    id           BIGSERIAL PRIMARY KEY,
    customer_id  BIGINT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    branch_id    BIGINT NOT NULL REFERENCES branches(id) ON DELETE CASCADE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (customer_id, branch_id)
);

-- =====================================================================
-- 5. MENÜ (İŞLETMEYE AİT)
-- =====================================================================

CREATE TABLE menu_categories (
    id           BIGSERIAL PRIMARY KEY,
    business_id  BIGINT NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
    name         VARCHAR(100) NOT NULL,
    sort_order   INTEGER NOT NULL DEFAULT 0,
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE menu_items (
    id                BIGSERIAL PRIMARY KEY,
    business_id       BIGINT NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
    menu_category_id  BIGINT NOT NULL REFERENCES menu_categories(id),
    name              VARCHAR(150) NOT NULL,
    description       TEXT,
    price             NUMERIC(10,2) NOT NULL CHECK (price >= 0),
    image_path        VARCHAR(255),
    is_available      BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order        INTEGER NOT NULL DEFAULT 0,
    deleted_at        TIMESTAMPTZ,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- =====================================================================
-- 6. KAMPANYALAR
-- =====================================================================

CREATE TABLE campaigns (
    id                  BIGSERIAL PRIMARY KEY,
    branch_id           BIGINT NOT NULL REFERENCES branches(id),
    title               VARCHAR(150) NOT NULL,
    description         TEXT,
    image_path          VARCHAR(255),
    terms               TEXT,
    type                VARCHAR(20) NOT NULL
                        CHECK (type IN ('announcement', 'rate_override')),
    reward_rate         NUMERIC(5,2) CHECK (reward_rate BETWEEN 0 AND 100),
    weekdays            SMALLINT[] CHECK (weekdays <@ ARRAY[1,2,3,4,5,6,7]::SMALLINT[]),
    new_customers_only  BOOLEAN NOT NULL DEFAULT FALSE,
    birthday_only       BOOLEAN NOT NULL DEFAULT FALSE,
    starts_at           TIMESTAMPTZ NOT NULL,
    ends_at             TIMESTAMPTZ NOT NULL,
    status              VARCHAR(10) NOT NULL DEFAULT 'draft'
                        CHECK (status IN ('draft', 'active', 'ended')),
    created_by          BIGINT NOT NULL REFERENCES panel_users(id),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (ends_at > starts_at),
    -- Oran kampanyasında oran zorunlu, duyuruda oran olmaz
    CHECK ((type = 'rate_override') = (reward_rate IS NOT NULL))
);

-- =====================================================================
-- 7. KUMBARA VE İŞLEMLER (ÇEKİRDEK)
-- =====================================================================

CREATE TABLE wallets (
    id              BIGSERIAL PRIMARY KEY,
    customer_id     BIGINT NOT NULL REFERENCES customers(id),
    branch_id       BIGINT NOT NULL REFERENCES branches(id),
    balance         NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (balance >= 0),
    total_earned    NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (total_earned >= 0),
    total_redeemed  NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (total_redeemed >= 0),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (customer_id, branch_id)
);

CREATE TABLE approval_requests (
    id                      BIGSERIAL PRIMARY KEY,
    type                    VARCHAR(20) NOT NULL CHECK (type IN ('redeem', 'correction')),
    wallet_id               BIGINT NOT NULL REFERENCES wallets(id),
    branch_id               BIGINT NOT NULL REFERENCES branches(id),
    customer_id             BIGINT NOT NULL REFERENCES customers(id),
    amount                  NUMERIC(12,2) NOT NULL,
    related_transaction_id  BIGINT,     -- FK wallet_transactions tablosundan sonra eklenir
    reason                  TEXT,
    status                  VARCHAR(10) NOT NULL DEFAULT 'pending'
                            CHECK (status IN ('pending', 'approved', 'rejected', 'withdrawn')),
    requested_by            BIGINT NOT NULL REFERENCES panel_users(id),
    idempotency_key         UUID UNIQUE,
    responded_at            TIMESTAMPTZ,
    failure_reason          VARCHAR(255),
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    -- Kullanım tutarı pozitif; düzeltme sıfırdan farklı ve bir işleme bağlı olmalı
    CHECK (
        (type = 'redeem'     AND amount > 0  AND related_transaction_id IS NULL)
     OR (type = 'correction' AND amount <> 0 AND related_transaction_id IS NOT NULL)
    )
);

-- Değiştirilemez işlem defteri: yalnızca INSERT (bkz. trigger'lar)
CREATE TABLE wallet_transactions (
    id                      BIGSERIAL PRIMARY KEY,
    wallet_id               BIGINT NOT NULL REFERENCES wallets(id),
    branch_id               BIGINT NOT NULL REFERENCES branches(id),
    customer_id             BIGINT NOT NULL REFERENCES customers(id),
    type                    VARCHAR(20) NOT NULL CHECK (type IN ('earn', 'redeem', 'correction')),
    amount                  NUMERIC(12,2) NOT NULL,
    spend_amount            NUMERIC(12,2) CHECK (spend_amount > 0),
    reward_rate             NUMERIC(5,2) CHECK (reward_rate BETWEEN 0 AND 100),
    campaign_id             BIGINT REFERENCES campaigns(id),
    balance_after           NUMERIC(12,2) NOT NULL CHECK (balance_after >= 0),
    related_transaction_id  BIGINT REFERENCES wallet_transactions(id),
    approval_request_id     BIGINT REFERENCES approval_requests(id),
    idempotency_key         UUID UNIQUE,
    performed_by            BIGINT NOT NULL REFERENCES panel_users(id),
    note                    TEXT,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (
        (type = 'earn'       AND amount > 0  AND spend_amount IS NOT NULL AND reward_rate IS NOT NULL
                             AND related_transaction_id IS NULL AND approval_request_id IS NULL)
     OR (type = 'redeem'     AND amount < 0  AND approval_request_id IS NOT NULL
                             AND spend_amount IS NULL AND campaign_id IS NULL)
     OR (type = 'correction' AND amount <> 0 AND approval_request_id IS NOT NULL
                             AND related_transaction_id IS NOT NULL
                             AND spend_amount IS NULL AND campaign_id IS NULL)
    )
);

ALTER TABLE approval_requests
    ADD CONSTRAINT approval_requests_related_tx_fk
    FOREIGN KEY (related_transaction_id) REFERENCES wallet_transactions(id);

-- =====================================================================
-- 8. AYLIK MÜŞTERİ SAYACI
-- =====================================================================

CREATE TABLE monthly_branch_customers (
    id                    BIGSERIAL PRIMARY KEY,
    branch_id             BIGINT NOT NULL REFERENCES branches(id),
    period                DATE NOT NULL CHECK (EXTRACT(DAY FROM period) = 1),  -- ayın 1'i
    customer_id           BIGINT NOT NULL REFERENCES customers(id),
    first_transaction_id  BIGINT NOT NULL REFERENCES wallet_transactions(id),
    created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (branch_id, period, customer_id)
);

CREATE TABLE monthly_branch_counters (
    id                     BIGSERIAL PRIMARY KEY,
    branch_id              BIGINT NOT NULL REFERENCES branches(id),
    period                 DATE NOT NULL CHECK (EXTRACT(DAY FROM period) = 1),
    unique_customers       INTEGER NOT NULL DEFAULT 0 CHECK (unique_customers >= 0),
    warned_threshold_1_at  TIMESTAMPTZ,
    warned_threshold_2_at  TIMESTAMPTZ,
    limit_reached_at       TIMESTAMPTZ,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (branch_id, period)
);

-- =====================================================================
-- 9. ABONELİK, ÖDEME, SPONSORLUK
-- =====================================================================

CREATE TABLE payment_notifications (
    id                 BIGSERIAL PRIMARY KEY,
    branch_id          BIGINT NOT NULL REFERENCES branches(id),
    submitted_by       BIGINT NOT NULL REFERENCES panel_users(id),
    purpose            VARCHAR(20) NOT NULL CHECK (purpose IN ('subscription', 'sponsorship')),
    billing_cycle      VARCHAR(10) CHECK (billing_cycle IN ('monthly', 'yearly')),
    sponsorship_month  DATE CHECK (EXTRACT(DAY FROM sponsorship_month) = 1),
    amount             NUMERIC(10,2) NOT NULL CHECK (amount > 0),
    sender_name        VARCHAR(150) NOT NULL,
    transfer_date      DATE NOT NULL,
    description        TEXT,
    receipt_path       VARCHAR(255),
    status             VARCHAR(10) NOT NULL DEFAULT 'pending'
                       CHECK (status IN ('pending', 'approved', 'rejected')),
    reviewed_by        BIGINT REFERENCES panel_users(id),
    reviewed_at        TIMESTAMPTZ,
    review_note        TEXT,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (
        (purpose = 'subscription' AND billing_cycle IS NOT NULL AND sponsorship_month IS NULL)
     OR (purpose = 'sponsorship'  AND sponsorship_month IS NOT NULL AND billing_cycle IS NULL)
    )
);

-- Yalnızca Profesyonel dönemler tutulur; kayıt yoksa şube Ücretsiz pakettedir.
CREATE TABLE subscriptions (
    id                       BIGSERIAL PRIMARY KEY,
    branch_id                BIGINT NOT NULL REFERENCES branches(id),
    billing_cycle            VARCHAR(10) NOT NULL CHECK (billing_cycle IN ('monthly', 'yearly')),
    starts_at                TIMESTAMPTZ NOT NULL,
    ends_at                  TIMESTAMPTZ NOT NULL,
    price                    NUMERIC(10,2) NOT NULL CHECK (price >= 0),
    status                   VARCHAR(10) NOT NULL DEFAULT 'active'
                             CHECK (status IN ('active', 'cancelled', 'expired')),
    payment_notification_id  BIGINT REFERENCES payment_notifications(id),
    activated_by             BIGINT NOT NULL REFERENCES panel_users(id),
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (ends_at > starts_at)
);

CREATE TABLE sponsorships (
    id                       BIGSERIAL PRIMARY KEY,
    branch_id                BIGINT NOT NULL REFERENCES branches(id),
    month                    DATE NOT NULL CHECK (EXTRACT(DAY FROM month) = 1),
    status                   VARCHAR(10) NOT NULL DEFAULT 'pending'
                             CHECK (status IN ('pending', 'active', 'ended', 'cancelled')),
    payment_notification_id  BIGINT REFERENCES payment_notifications(id),
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (branch_id, month)
);

-- =====================================================================
-- 10. PUANLAMA VE YORUM
-- =====================================================================

CREATE TABLE reviews (
    id              BIGSERIAL PRIMARY KEY,
    branch_id       BIGINT NOT NULL REFERENCES branches(id),
    customer_id     BIGINT NOT NULL REFERENCES customers(id),
    score           SMALLINT NOT NULL CHECK (score BETWEEN 1 AND 10),
    comment         TEXT,
    status          VARCHAR(10) NOT NULL DEFAULT 'published'
                    CHECK (status IN ('published', 'removed')),
    removed_by      BIGINT REFERENCES panel_users(id),
    removed_reason  TEXT,
    removed_at      TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (branch_id, customer_id)
);

CREATE TABLE review_replies (
    id             BIGSERIAL PRIMARY KEY,
    review_id      BIGINT NOT NULL UNIQUE REFERENCES reviews(id) ON DELETE CASCADE,
    panel_user_id  BIGINT NOT NULL REFERENCES panel_users(id),
    body           TEXT NOT NULL,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE review_reports (
    id             BIGSERIAL PRIMARY KEY,
    review_id      BIGINT NOT NULL REFERENCES reviews(id) ON DELETE CASCADE,
    reporter_type  VARCHAR(20) NOT NULL CHECK (reporter_type IN ('customer', 'panel_user')),
    reporter_id    BIGINT NOT NULL,
    reason         TEXT NOT NULL,
    status         VARCHAR(10) NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'resolved')),
    resolved_by    BIGINT REFERENCES panel_users(id),
    resolved_at    TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- =====================================================================
-- 11. PANEL BİLDİRİMLERİ VE DENETİM
-- =====================================================================

CREATE TABLE panel_notifications (
    id             BIGSERIAL PRIMARY KEY,
    panel_user_id  BIGINT NOT NULL REFERENCES panel_users(id) ON DELETE CASCADE,
    type           VARCHAR(50) NOT NULL,
    data           JSONB NOT NULL DEFAULT '{}'::JSONB,
    read_at        TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE audit_logs (
    id            BIGSERIAL PRIMARY KEY,
    actor_type    VARCHAR(30) NOT NULL,   -- customer, panel_user, system
    actor_id      BIGINT,
    action        VARCHAR(100) NOT NULL,
    subject_type  VARCHAR(50),
    subject_id    BIGINT,
    payload       JSONB,
    ip            INET,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- =====================================================================
-- 12. LARAVEL SİSTEM TABLOLARI
-- (oturum, önbellek, kuyruk, Sanctum token'ları, şifre sıfırlama)
-- =====================================================================

CREATE TABLE migrations (
    id         SERIAL PRIMARY KEY,
    migration  VARCHAR(255) NOT NULL,
    batch      INTEGER NOT NULL
);

-- Panel kullanıcıları için
CREATE TABLE password_reset_tokens (
    email       VARCHAR(255) PRIMARY KEY,
    token       VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP(0)
);

-- Müşteriler için
CREATE TABLE customer_password_reset_tokens (
    email       VARCHAR(255) PRIMARY KEY,
    token       VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP(0)
);

CREATE TABLE sessions (
    id             VARCHAR(255) PRIMARY KEY,
    user_id        BIGINT,
    ip_address     VARCHAR(45),
    user_agent     TEXT,
    payload        TEXT NOT NULL,
    last_activity  INTEGER NOT NULL
);
CREATE INDEX sessions_user_id_index ON sessions (user_id);
CREATE INDEX sessions_last_activity_index ON sessions (last_activity);

CREATE TABLE cache (
    key         VARCHAR(255) PRIMARY KEY,
    value       TEXT NOT NULL,
    expiration  INTEGER NOT NULL
);

CREATE TABLE cache_locks (
    key         VARCHAR(255) PRIMARY KEY,
    owner       VARCHAR(255) NOT NULL,
    expiration  INTEGER NOT NULL
);

CREATE TABLE jobs (
    id            BIGSERIAL PRIMARY KEY,
    queue         VARCHAR(255) NOT NULL,
    payload       TEXT NOT NULL,
    attempts      SMALLINT NOT NULL,
    reserved_at   INTEGER,
    available_at  INTEGER NOT NULL,
    created_at    INTEGER NOT NULL
);
CREATE INDEX jobs_queue_index ON jobs (queue);

CREATE TABLE job_batches (
    id              VARCHAR(255) PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    total_jobs      INTEGER NOT NULL,
    pending_jobs    INTEGER NOT NULL,
    failed_jobs     INTEGER NOT NULL,
    failed_job_ids  TEXT NOT NULL,
    options         TEXT,
    cancelled_at    INTEGER,
    created_at      INTEGER NOT NULL,
    finished_at     INTEGER
);

CREATE TABLE failed_jobs (
    id          BIGSERIAL PRIMARY KEY,
    uuid        VARCHAR(255) NOT NULL UNIQUE,
    connection  TEXT NOT NULL,
    queue       TEXT NOT NULL,
    payload     TEXT NOT NULL,
    exception   TEXT NOT NULL,
    failed_at   TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Mobil API token'ları (Laravel Sanctum)
CREATE TABLE personal_access_tokens (
    id              BIGSERIAL PRIMARY KEY,
    tokenable_type  VARCHAR(255) NOT NULL,
    tokenable_id    BIGINT NOT NULL,
    name            TEXT NOT NULL,
    token           VARCHAR(64) NOT NULL UNIQUE,
    abilities       TEXT,
    last_used_at    TIMESTAMP(0),
    expires_at      TIMESTAMP(0),
    created_at      TIMESTAMP(0),
    updated_at      TIMESTAMP(0)
);
CREATE INDEX personal_access_tokens_tokenable_index
    ON personal_access_tokens (tokenable_type, tokenable_id);

-- =====================================================================
-- 13. TRIGGER'LAR (veri bütünlüğü)
-- =====================================================================

-- İşlem defteri değiştirilemez / silinemez
CREATE FUNCTION kumbare_prevent_ledger_change() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION '% tablosundaki kayitlar degistirilemez veya silinemez', TG_TABLE_NAME;
END;
$$;

CREATE TRIGGER wallet_transactions_no_update
    BEFORE UPDATE OR DELETE ON wallet_transactions
    FOR EACH ROW EXECUTE PROCEDURE kumbare_prevent_ledger_change();

-- Müşteri numarası değiştirilemez
CREATE FUNCTION kumbare_prevent_customer_no_change() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.customer_no IS DISTINCT FROM OLD.customer_no THEN
        RAISE EXCEPTION 'Musteri numarasi degistirilemez';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER customers_customer_no_immutable
    BEFORE UPDATE ON customers
    FOR EACH ROW EXECUTE PROCEDURE kumbare_prevent_customer_no_change();

-- Müşteriler fiziksel olarak silinemez (numara tekrar kullanılmasın, geçmiş korunsun)
CREATE FUNCTION kumbare_prevent_customer_delete() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Musteriler silinemez; soft delete (deleted_at) kullanin';
END;
$$;

CREATE TRIGGER customers_no_delete
    BEFORE DELETE ON customers
    FOR EACH ROW EXECUTE PROCEDURE kumbare_prevent_customer_delete();

-- Müşteri numarası sayacı geri alınamaz
CREATE FUNCTION kumbare_prevent_counter_decrease() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.last_value < OLD.last_value THEN
        RAISE EXCEPTION 'Musteri numarasi sayaci azaltilamaz';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER customer_no_counter_no_decrease
    BEFORE UPDATE ON customer_no_counter
    FOR EACH ROW EXECUTE PROCEDURE kumbare_prevent_counter_decrease();

CREATE TRIGGER customer_no_counter_no_delete
    BEFORE DELETE ON customer_no_counter
    FOR EACH ROW EXECUTE PROCEDURE kumbare_prevent_ledger_change();

-- =====================================================================
-- 14. INDEX'LER
-- =====================================================================

CREATE INDEX customers_district_idx           ON customers (district_id);

CREATE INDEX branches_business_idx            ON branches (business_id);
CREATE INDEX branches_district_status_idx     ON branches (district_id, status);
CREATE INDEX branches_city_status_idx         ON branches (city_id, status);
CREATE INDEX branches_reward_rate_idx         ON branches (reward_rate);
CREATE INDEX branches_rating_idx              ON branches (rating_count, rating_avg);
CREATE INDEX branches_popularity_idx          ON branches (popularity_score);
CREATE INDEX branches_location_idx            ON branches (latitude, longitude);
CREATE INDEX branches_created_idx             ON branches (created_at);

CREATE INDEX panel_users_business_idx         ON panel_users (business_id);
CREATE INDEX panel_users_branch_idx           ON panel_users (branch_id);

CREATE INDEX menu_categories_business_idx     ON menu_categories (business_id, sort_order);
CREATE INDEX menu_items_category_idx          ON menu_items (menu_category_id, sort_order);
CREATE INDEX menu_items_business_idx          ON menu_items (business_id);

CREATE INDEX campaigns_branch_status_idx      ON campaigns (branch_id, status, starts_at, ends_at);

CREATE INDEX wallets_branch_idx               ON wallets (branch_id);

CREATE INDEX wallet_tx_wallet_created_idx     ON wallet_transactions (wallet_id, created_at DESC);
CREATE INDEX wallet_tx_branch_created_idx     ON wallet_transactions (branch_id, created_at);
CREATE INDEX wallet_tx_customer_created_idx   ON wallet_transactions (customer_id, created_at);
CREATE INDEX wallet_tx_campaign_idx           ON wallet_transactions (campaign_id) WHERE campaign_id IS NOT NULL;
CREATE INDEX wallet_tx_related_idx            ON wallet_transactions (related_transaction_id) WHERE related_transaction_id IS NOT NULL;

CREATE INDEX approval_requests_customer_idx   ON approval_requests (customer_id, status);
CREATE INDEX approval_requests_branch_idx     ON approval_requests (branch_id, status);

CREATE INDEX monthly_branch_customers_customer_idx ON monthly_branch_customers (customer_id);

CREATE INDEX payment_notifications_status_idx ON payment_notifications (status, created_at);
CREATE INDEX subscriptions_branch_idx         ON subscriptions (branch_id, status, ends_at);
CREATE INDEX sponsorships_month_idx           ON sponsorships (month, status);

CREATE INDEX reviews_branch_status_idx        ON reviews (branch_id, status, created_at);
CREATE INDEX reviews_customer_idx             ON reviews (customer_id);
CREATE INDEX review_reports_status_idx        ON review_reports (status);

CREATE INDEX panel_notifications_user_idx     ON panel_notifications (panel_user_id, read_at);

CREATE INDEX audit_logs_actor_idx             ON audit_logs (actor_type, actor_id, created_at);
CREATE INDEX audit_logs_action_idx            ON audit_logs (action, created_at);
CREATE INDEX audit_logs_subject_idx           ON audit_logs (subject_type, subject_id);

-- =====================================================================
-- 15. BAŞLANGIÇ VERİLERİ
-- =====================================================================

INSERT INTO customer_no_counter (id, last_value) VALUES (1, 0);

INSERT INTO plans (code, name, description, monthly_price, yearly_price) VALUES
    ('free', 'Ücretsiz',
     'Bütün özellikler kullanılabilir. Aylık tekil müşteri sınırı vardır.',
     0, 0),
    ('pro',  'Profesyonel',
     'Bütün özellikler kullanılabilir. Aylık müşteri sınırı yoktur.',
     NULL, NULL);   -- Fiyatlar platform yöneticisi tarafından belirlenecek

INSERT INTO settings (key, value) VALUES
    ('free_monthly_customer_limit',      '100'),
    ('warning_threshold_1',              '80'),
    ('warning_threshold_2',              '90'),
    ('subscription_expiry_warning_days', '7'),
    ('bank_name',                        ''),
    ('iban',                             ''),
    ('account_holder',                   ''),
    ('sponsorship_monthly_price',        '');

COMMIT;
