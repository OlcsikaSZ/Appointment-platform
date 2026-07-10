-- Weboldal-sablon és bizalomépítő tartalmak bővítése
-- Futtatás előtt készíts adatbázis-mentést.

ALTER TABLE businesses
    ADD COLUMN hero_title VARCHAR(220) NULL AFTER tagline,
    ADD COLUMN hero_text TEXT NULL AFTER hero_title,
    ADD COLUMN about_title VARCHAR(160) NULL AFTER hero_text,
    ADD COLUMN about_text TEXT NULL AFTER about_title,
    ADD COLUMN phone VARCHAR(80) NULL AFTER about_text,
    ADD COLUMN email VARCHAR(160) NULL AFTER phone,
    ADD COLUMN address VARCHAR(255) NULL AFTER email,
    ADD COLUMN opening_hours TEXT NULL AFTER address,
    ADD COLUMN google_maps_url TEXT NULL AFTER opening_hours,
    ADD COLUMN logo_path VARCHAR(255) NULL AFTER google_maps_url;

ALTER TABLE services
    ADD COLUMN image_url TEXT NULL AFTER description;

CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    author VARCHAR(120) NOT NULL,
    text TEXT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT reviews_business_id_foreign FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX reviews_business_active_sort_index (business_id, active, sort_order)
);

CREATE TABLE faqs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT faqs_business_id_foreign FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX faqs_business_active_sort_index (business_id, active, sort_order)
);

UPDATE businesses SET slug = 'default' WHERE slug = 'demo';

UPDATE businesses
SET
    name = 'Aranyvonal Stúdió',
    tagline = 'Személyre szabott szolgáltatások, egyszerű online foglalással.',
    hero_title = 'Egyszerű foglalás. Megbízható szolgáltatás.',
    hero_text = 'Válaszd ki a neked megfelelő szolgáltatást és időpontot néhány kattintással. Gyors, átlátható és kényelmes.',
    about_title = 'Rólunk',
    about_text = 'Fontos számunkra, hogy már az első kattintástól egyszerű és átlátható legyen az ügyintézés. Válassz szolgáltatást, foglalj szabad időpontot, és mi gondoskodunk a többiről.',
    phone = '+36 30 123 4567',
    email = 'hello@aranyvonal.hu',
    address = '3525 Miskolc, Széchenyi utca 12.',
    opening_hours = 'Hétfő–Péntek: 09:00–17:00\nSzombat: 09:00–13:00\nVasárnap: zárva',
    google_maps_url = 'https://www.google.com/maps/search/?api=1&query=Miskolc',
    logo_text = 'AS'
WHERE slug = 'default' AND name IN ('Demo Vallalkozas', 'Demo Vállalkozás');

INSERT INTO reviews (business_id, author, text, rating, active, sort_order, created_at, updated_at)
SELECT id, 'Minta vélemény', 'Ez egy helykitöltő vendégvélemény. Az admin felületen saját, valódi értékelésre cserélhető.', 5, 1, 1, NOW(), NOW()
FROM businesses WHERE slug = 'default';

INSERT INTO reviews (business_id, author, text, rating, active, sort_order, created_at, updated_at)
SELECT id, 'Minta vendég', 'Gyors, átlátható foglalás és kellemes ügyfélélmény – ezt a szöveget is szabadon módosíthatod.', 5, 1, 2, NOW(), NOW()
FROM businesses WHERE slug = 'default';

INSERT INTO faqs (business_id, question, answer, active, sort_order, created_at, updated_at)
SELECT id, 'Hogyan tudok időpontot foglalni?', 'Válassz szolgáltatást, dátumot és szabad időpontot, majd add meg az elérhetőségeidet.', 1, 1, NOW(), NOW()
FROM businesses WHERE slug = 'default';

INSERT INTO faqs (business_id, question, answer, active, sort_order, created_at, updated_at)
SELECT id, 'Módosíthatom vagy lemondhatom a foglalásomat?', 'Igen. A foglalás után kapott egyedi kezelőlinken módosíthatod vagy lemondhatod az időpontot.', 1, 2, NOW(), NOW()
FROM businesses WHERE slug = 'default';

INSERT INTO faqs (business_id, question, answer, active, sort_order, created_at, updated_at)
SELECT id, 'Hol találom a pontos elérhetőségeket?', 'A kapcsolat szekcióban megtalálod a telefonszámot, e-mail címet, címet és nyitvatartást.', 1, 3, NOW(), NOW()
FROM businesses WHERE slug = 'default';
