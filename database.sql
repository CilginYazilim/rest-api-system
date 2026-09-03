-- =====================================================================
--  REST API Sistemi – VERİTABANI KURULUM DOSYASI
--  cilginyazilim.com
-- ---------------------------------------------------------------------
--  KURULUM
--    1. phpMyAdmin → "İçe Aktar" → bu dosyayı seçin
--    2. Ya da komut satırından:
--         mysql -u root -p < database.sql
--
--  Dosya kendi veritabanını OLUŞTURUR; önce elle veritabanı açmanıza
--  gerek yoktur.
--
--  DİKKAT: DROP TABLE komutları vardır. Aynı isimli bir veritabanınız
--  varsa tabloları SİLER. Var olan bir sisteme uygularken bu bölümü
--  atlayın.
-- =====================================================================

-- BAĞLANTI KARAKTER KÜMESİ
-- ---------------------------------------------------------------
--  Bu satır olmadan "mysql -u root < database.sql" komutu, dosyayı
--  sunucunun VARSAYILAN karakter kümesiyle (çoğu Windows kurulumunda
--  latin1) okur. Sonuç sessiz bir bozulmadır: "GÜL" veritabanına
--  "GÃœL" olarak yazılır ve hata da vermez.
--
--  phpMyAdmin bunu kendisi ayarladığı için sorun oradan içe
--  aktarırken görülmez — bu da hatayı bulmayı zorlaştırır.
SET NAMES utf8mb4;

-- utf8mb4: Türkçe karakterlerin yanı sıra emoji de saklanabilir.
-- utf8mb4_unicode_ci: Büyük/küçük harf ve aksan duyarsız karşılaştırma.
CREATE DATABASE IF NOT EXISTS `cy_rest_api`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `cy_rest_api`;

-- Yabancı anahtar bağımlılığı olduğu için tabloları silmeden önce
-- yabancı anahtar denetimini kapatıyoruz. Aksi halde "users" tablosunu
-- silmeye çalışırken ona bağlı bir tablo yüzünden hata alırdık ve
-- dosyayı ikinci kez içe aktarmak imkânsız olurdu.
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `remember_tokens`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------------
--  1) users – Uygulama kullanıcıları
-- ---------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,

  `name`          VARCHAR(150) NOT NULL,
  `surname`       VARCHAR(150) NOT NULL,

  -- 190 karakter: utf8mb4 + InnoDB'de bir sütunun UNIQUE indeks
  -- alabileceği güvenli üst sınırdır (eski MySQL sürümlerinde 767 bayt).
  `email`         VARCHAR(190) NOT NULL,

  -- password_hash() çıktısı. Bugün 60 karakter (bcrypt) ama ileride
  -- daha uzun algoritmalara geçilebilsin diye 255 bıraktık.
  -- ASLA düz parola saklanmaz.
  `password`      VARCHAR(255) NOT NULL,

  -- 0 = hesap pasif; giriş yapamaz.
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,

  -- Arayüz teması HESABA bağlıdır: kullanıcı hangi bilgisayardan
  -- girerse girsin kendi tercihini görür.
  `theme`         ENUM('light','dark') NOT NULL DEFAULT 'light',

  `last_login_at` DATETIME     NULL DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NULL DEFAULT NULL,

  PRIMARY KEY (`id`),

  -- BENZERSİZ E-POSTA: Aynı adresle iki hesap açılmasını veritabanı
  -- seviyesinde engeller. Uygulama da kontrol eder ama son söz
  -- burasıdır; iki istek aynı anda gelse bile çakışma olmaz.
  UNIQUE KEY `uk_users_email` (`email`),

  -- İNDEKSLER: SAYFALAMA için kritiktir.
  -- "ORDER BY id DESC LIMIT ... OFFSET ..." birincil anahtarı kullanır;
  -- ad/soyad aramaları ve durum filtresi de indekslenmiştir.
  KEY `idx_users_name`    (`name`),
  KEY `idx_users_surname` (`surname`),
  KEY `idx_users_active`  (`is_active`),
  KEY `idx_users_created` (`created_at`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
--  2) remember_tokens – "Beni hatırla" anahtarları
-- ---------------------------------------------------------------
--  ÇEREZDE NE VAR?   "selector:validator"
--  TABLODA NE VAR?   selector düz, validator'ın SHA-256 ÖZETİ
--
--  Validator'ı düz saklamıyoruz: veritabanı sızsa bile o özetlerden
--  çalışan bir çereze geri dönülemez. Parolayı neden özetliyorsak
--  aynı gerekçe geçerlidir.
CREATE TABLE `remember_tokens` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `selector`       CHAR(18)     NOT NULL,
  `validator_hash` CHAR(64)     NOT NULL,
  `expires_at`     DATETIME     NOT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_remember_selector` (`selector`),
  KEY `idx_remember_expires` (`expires_at`),

  -- ON DELETE CASCADE: Kullanıcı silinince anahtarları da silinir.
  CONSTRAINT `fk_remember_user`
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
--  3) login_attempts – Kaba kuvvet (brute force) koruması
-- ---------------------------------------------------------------
--  Hatalı giriş denemeleri burada sayılır. Sayaç OTURUMDA değil
--  VERİTABANINDA tutulur; aksi halde saldırgan çerezini silerek
--  sayacı sıfırlayabilirdi.
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- E-posta + IP birleşiminin SHA-256 özeti. Düz e-posta yazsaydık,
  -- veritabanı sızdığında "hangi hesaplara saldırıldı" bilgisi de sızardı.
  `identifier`   CHAR(64)     NOT NULL,

  -- 45 karakter: IPv6 adresinin en uzun metin biçimine yeter.
  `ip`           VARCHAR(45)  NOT NULL DEFAULT '',
  `attempted_at` DATETIME     NOT NULL,

  PRIMARY KEY (`id`),
  -- Sorgu her zaman "şu anahtar için son X saniyedeki denemeler"
  -- biçiminde olduğu için bileşik indeks ideal.
  KEY `idx_attempts_lookup` (`identifier`, `attempted_at`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
--  4) activity_log – İşlem günlüğü (audit log)
-- ---------------------------------------------------------------
CREATE TABLE `activity_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- NULL olabilir: hatalı giriş denemesinde kullanıcı bilinmez.
  `user_id`     INT UNSIGNED NULL DEFAULT NULL,

  `action`      VARCHAR(40)  NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `ip`          VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`  DATETIME     NOT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_log_user`    (`user_id`),
  KEY `idx_log_created` (`created_at`),

  -- ON DELETE SET NULL: Kullanıcı silinse bile günlük satırı KALIR,
  -- yalnızca kime ait olduğu bilgisi boşalır. Günlük silinmemelidir.
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- ===============================================================
--  DEMO VERİLER
-- ---------------------------------------------------------------
--  GİRİŞ BİLGİLERİ
--    Yönetici  : admin@cilginyazilim.com / Admin1234
--    Kullanıcı : demo@cilginyazilim.com  / Demo1234
--    Diğer örnek kullanıcıların tamamı   : Demo1234
--
--  !!! CANLI SUNUCUYA ALIRKEN BU PAROLALARI MUTLAKA DEĞİŞTİRİN !!!
--
--  Parolalar bcrypt ile özetlenmiştir (password_hash / PASSWORD_DEFAULT).
--  Özet geri çevrilemez; veritabanı sızsa bile parolalar okunamaz.
--
--  NEDEN 51 KULLANICI?
--  Sayfalamayı gerçekten görebilmek için. 20'şer kayıtla 3 sayfa
--  oluşur; sayfa boyutunu 10'a düşürürseniz 6 sayfa çıkar ve
--  sayfa numaralarındaki "…" kısaltmasını da görebilirsiniz.
-- ===============================================================

SET @demo_pass  = '$2y$10$Pum/vll0wIHFZF4scJXJTubeqAJSc/lk6rmT8ysG3NEpWXqsdSdua'; -- Demo1234
SET @admin_pass = '$2y$10$3/.dKkIFNGVfVblONMvs.uJhs/7hp4Ivymrl0XxNrG5RnpF8I3nL6'; -- Admin1234

INSERT INTO `users` (`id`, `name`, `surname`, `email`, `password`, `is_active`, `created_at`)
VALUES
(1, 'Sistem', 'Yöneticisi', 'admin@cilginyazilim.com', @admin_pass, 1, '2025-01-01 09:00:00'),
(2, 'Demo', 'Kullanıcı', 'demo@cilginyazilim.com', @demo_pass, 1, '2025-01-01 09:05:00');

-- Sayfalamayı denemek için örnek kullanıcılar.
-- Birkaçı bilerek PASİF bırakılmıştır; durum filtresini deneyebilirsiniz.
INSERT INTO `users` (`id`, `name`, `surname`, `email`, `password`, `is_active`, `created_at`)
VALUES
(3, 'Evren', 'ÇILGIN', 'evren.cilgin@ornek.com', @demo_pass, 1, '2025-01-06 19:34:27'),
(4, 'Taha', 'BAYAR', 'taha.bayar@ornek.com', @demo_pass, 1, '2025-01-07 08:42:28'),
(5, 'Zeynep', 'TURAN', 'zeynep.turan@ornek.com', @demo_pass, 1, '2025-01-08 10:59:56'),
(6, 'Mustafa', 'YILMAZ', 'mustafa.yilmaz@ornek.com', @demo_pass, 1, '2025-01-08 18:44:32'),
(7, 'Elif', 'KAYA', 'elif.kaya@ornek.com', @demo_pass, 1, '2025-01-09 17:47:28'),
(8, 'Ahmet', 'DEMİR', 'ahmet.demir@ornek.com', @demo_pass, 1, '2025-01-11 11:49:05'),
(9, 'Ayşe', 'ŞAHİN', 'ayse.sahin@ornek.com', @demo_pass, 1, '2025-01-12 21:17:02'),
(10, 'Mehmet', 'ÇELİK', 'mehmet.celik@ornek.com', @demo_pass, 1, '2025-01-14 04:24:09'),
(11, 'Fatma', 'YILDIZ', 'fatma.yildiz@ornek.com', @demo_pass, 0, '2025-01-15 23:58:29'),
(12, 'Emre', 'YILDIRIM', 'emre.yildirim@ornek.com', @demo_pass, 1, '2025-01-16 16:30:24'),
(13, 'Selin', 'ÖZTÜRK', 'selin.ozturk@ornek.com', @demo_pass, 1, '2025-01-17 17:06:42'),
(14, 'Burak', 'AYDIN', 'burak.aydin@ornek.com', @demo_pass, 1, '2025-01-17 18:08:06'),
(15, 'Merve', 'ÖZDEMİR', 'merve.ozdemir@ornek.com', @demo_pass, 1, '2025-01-19 01:30:32'),
(16, 'Onur', 'ARSLAN', 'onur.arslan@ornek.com', @demo_pass, 1, '2025-01-19 20:00:51'),
(17, 'Ceren', 'DOĞAN', 'ceren.dogan@ornek.com', @demo_pass, 1, '2025-01-20 14:09:31'),
(18, 'Kaan', 'KILIÇ', 'kaan.kilic@ornek.com', @demo_pass, 1, '2025-01-20 22:05:15'),
(19, 'Büşra', 'ASLAN', 'busra.aslan@ornek.com', @demo_pass, 1, '2025-01-21 08:36:59'),
(20, 'Serkan', 'ÇETİN', 'serkan.cetin@ornek.com', @demo_pass, 1, '2025-01-22 16:06:12'),
(21, 'Gizem', 'KARA', 'gizem.kara@ornek.com', @demo_pass, 1, '2025-01-24 10:30:31'),
(22, 'Barış', 'KOÇ', 'baris.koc@ornek.com', @demo_pass, 1, '2025-01-25 07:19:52'),
(23, 'Deniz', 'KURT', 'deniz.kurt@ornek.com', @demo_pass, 1, '2025-01-26 01:28:52'),
(24, 'Hakan', 'ÖZKAN', 'hakan.ozkan@ornek.com', @demo_pass, 1, '2025-01-27 19:52:10'),
(25, 'İrem', 'ŞİMŞEK', 'irem.simsek@ornek.com', @demo_pass, 1, '2025-01-29 12:43:32'),
(26, 'Yusuf', 'POLAT', 'yusuf.polat@ornek.com', @demo_pass, 1, '2025-01-29 20:10:46'),
(27, 'Melis', 'ÖZER', 'melis.ozer@ornek.com', @demo_pass, 1, '2025-01-30 22:06:37'),
(28, 'Cem', 'KORKMAZ', 'cem.korkmaz@ornek.com', @demo_pass, 1, '2025-01-31 03:44:01'),
(29, 'Esra', 'ÇAKIR', 'esra.cakir@ornek.com', @demo_pass, 0, '2025-01-31 18:25:27'),
(30, 'Volkan', 'ERDOĞAN', 'volkan.erdogan@ornek.com', @demo_pass, 1, '2025-02-01 08:14:52'),
(31, 'Şeyma', 'GÜNEŞ', 'seyma.gunes@ornek.com', @demo_pass, 1, '2025-02-01 14:27:09'),
(32, 'Uğur', 'AKSOY', 'ugur.aksoy@ornek.com', @demo_pass, 1, '2025-02-03 03:12:55'),
(33, 'Pınar', 'BULUT', 'pinar.bulut@ornek.com', @demo_pass, 1, '2025-02-04 20:02:24'),
(34, 'Tolga', 'TAŞ', 'tolga.tas@ornek.com', @demo_pass, 1, '2025-02-04 21:02:35'),
(35, 'Nazlı', 'KAPLAN', 'nazli.kaplan@ornek.com', @demo_pass, 1, '2025-02-06 16:07:07'),
(36, 'Görkem', 'SOYLU', 'gorkem.soylu@ornek.com', @demo_pass, 1, '2025-02-08 01:23:35'),
(37, 'Damla', 'ATEŞ', 'damla.ates@ornek.com', @demo_pass, 1, '2025-02-09 07:56:33'),
(38, 'Berk', 'GÜLER', 'berk.guler@ornek.com', @demo_pass, 1, '2025-02-10 02:16:27'),
(39, 'Sude', 'BOZKURT', 'sude.bozkurt@ornek.com', @demo_pass, 1, '2025-02-10 18:54:39'),
(40, 'Alper', 'TEKİN', 'alper.tekin@ornek.com', @demo_pass, 1, '2025-02-11 10:55:00'),
(41, 'Ebru', 'ACAR', 'ebru.acar@ornek.com', @demo_pass, 1, '2025-02-13 09:17:40'),
(42, 'Sinan', 'BARAN', 'sinan.baran@ornek.com', @demo_pass, 1, '2025-02-15 08:26:15'),
(43, 'Aslı', 'SEZER', 'asli.sezer@ornek.com', @demo_pass, 1, '2025-02-16 06:25:42'),
(44, 'Furkan', 'KOCA', 'furkan.koca@ornek.com', @demo_pass, 0, '2025-02-17 21:37:35'),
(45, 'Nesrin', 'UZUN', 'nesrin.uzun@ornek.com', @demo_pass, 1, '2025-02-18 17:36:38'),
(46, 'Okan', 'AVCI', 'okan.avci@ornek.com', @demo_pass, 1, '2025-02-19 06:17:27'),
(47, 'Tuğçe', 'KESKİN', 'tugce.keskin@ornek.com', @demo_pass, 1, '2025-02-20 05:21:28'),
(48, 'Murat', 'ÜNAL', 'murat.unal@ornek.com', @demo_pass, 1, '2025-02-21 08:10:22'),
(49, 'Yasemin', 'GÜL', 'yasemin.gul@ornek.com', @demo_pass, 1, '2025-02-22 02:55:23'),
(50, 'Halil', 'DURMAZ', 'halil.durmaz@ornek.com', @demo_pass, 1, '2025-02-22 18:23:50'),
(51, 'Beyza', 'SARI', 'beyza.sari@ornek.com', @demo_pass, 1, '2025-02-23 10:36:41');


-- ---------------------------------------------------------------
--  Örnek işlem günlüğü
-- ---------------------------------------------------------------
INSERT INTO `activity_log` (`user_id`, `action`, `description`, `ip`, `created_at`) VALUES
(1, 'login',            'Panele giriş yapıldı.', '127.0.0.1', NOW() - INTERVAL 5 MINUTE),
(2, 'login',            'Panele giriş yapıldı.', '127.0.0.1', NOW() - INTERVAL 5 HOUR),
(1, 'password_changed', 'Parola değiştirildi.',  '127.0.0.1', NOW() - INTERVAL 3 DAY);



-- ===============================================================
--  PROJEYE ÖZEL TABLOLAR
-- ===============================================================

-- Dosyanın ikinci kez içe aktarılabilmesi için önce siliyoruz.
-- Sıra önemlidir: api_requests, api_tokens'a bağlıdır.
DROP TABLE IF EXISTS `api_requests`;
DROP TABLE IF EXISTS `api_tokens`;

-- ---------------------------------------------------------------
--  api_tokens – Bearer jetonları
-- ---------------------------------------------------------------
--  JETON DÜZ SAKLANMAZ, YALNIZCA SHA-256 ÖZETİ TUTULUR.
--  Veritabanı bir gün sızarsa, düz saklanan jetonlar anında
--  kullanılabilir hâle gelirdi.
--
--  Neden bcrypt değil? Jeton 32 BAYT RASTGELE veridir; sözlük
--  saldırısı anlamsızdır ve her istekte doğrulandığı için bcrypt
--  her API çağrısına ~100 ms eklerdi. Tuz da kullanmıyoruz: tuzlu
--  bir özette ARAMA yapamaz, tüm jetonları tek tek denemek
--  zorunda kalırdık.
-- ---------------------------------------------------------------
CREATE TABLE `api_tokens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,

  -- Kullanıcının hatırlaması için: "Mobil uygulama", "Rapor betiği"…
  `name`         VARCHAR(100) NOT NULL,

  -- SHA-256 çıktısı her zaman 64 onaltılık karakterdir.
  `token_hash`   CHAR(64)     NOT NULL,

  -- Virgülle ayrılmış yetkiler: "read" veya "read,write".
  -- Ayrı bir tablo daha "doğru" olurdu; iki sabit değer için
  -- JOIN maliyeti ve karmaşıklık buna değmiyor.
  `scopes`       VARCHAR(100) NOT NULL DEFAULT 'read',

  -- Kullanılmayan jetonlar, unutulup ortalıkta kalan yetkilerdir;
  -- görünür olmaları güvenlik açısından değerlidir.
  `last_used_at` DATETIME     NULL DEFAULT NULL,
  `last_used_ip` VARCHAR(45)  NOT NULL DEFAULT '',

  -- Jetonun ÖMÜR BOYU istek sayısı. api_requests tablosu bunun için
  -- KAYNAK DEĞİLDİR: o tablo yalnızca hız sınırının kayan penceresi
  -- içindir ve ApiRateLimiter::prune() satırları 1 saat sonra siler
  -- (bkz. o dosya). Kalıcı bir sayaç için ayrı bir alan gerekiyordu;
  -- her istekte "request_count = request_count + 1" ile artırılır.
  `request_count` INT UNSIGNED NOT NULL DEFAULT 0,

  -- İptal SİLME DEĞİLDİR: "ne zaman iptal edildi" bilgisi bir
  -- güvenlik incelemesinde paha biçilmezdir.
  `revoked_at`   DATETIME     NULL DEFAULT NULL,

  `created_at`   DATETIME     NOT NULL,

  PRIMARY KEY (`id`),

  -- Doğrulama sorgusu HER API isteğinde çalışır: özet üzerinde
  -- benzersiz indeks, aramayı O(1)'e indirir.
  UNIQUE KEY `uk_token_hash` (`token_hash`),

  KEY `idx_token_user` (`user_id`),

  CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`) ON DELETE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
--  api_requests – Kayan pencere hız sınırı sayacı
-- ---------------------------------------------------------------
--  Her istek bir satır ekler; "son 60 saniyede kaç istek" sorusu
--  bu tablodan yanıtlanır.
--
--  SABİT PENCERE yerine KAYAN PENCERE kullanıyoruz: sabit pencerede
--  12:00:59'da 60, 12:01:00'da 60 istek geçebilir ve sınır kâğıt
--  üzerinde kalır.
--
--  ÜRETİM NOTU: Yüksek trafikte her istek için INSERT pahalıdır;
--  orada Redis gibi bellek içi bir sayaç tercih edilir. Mantık
--  aynıdır, yalnızca saklama yeri değişir.
-- ---------------------------------------------------------------
CREATE TABLE `api_requests` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_id`     INT UNSIGNED NOT NULL,
  `ip`           VARCHAR(45)  NOT NULL DEFAULT '',
  `requested_at` DATETIME     NOT NULL,

  PRIMARY KEY (`id`),

  -- Sorgu her zaman "şu jeton için son X saniye" biçimindedir;
  -- bileşik indeks tam bu erişim deseni içindir.
  KEY `idx_req_window` (`token_id`, `requested_at`),

  CONSTRAINT `fk_req_token` FOREIGN KEY (`token_id`)
      REFERENCES `api_tokens` (`id`) ON DELETE CASCADE
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ===============================================================
--  ÖRNEK JETONLAR
-- ---------------------------------------------------------------
--  JETONUN AÇIK METNİ DEPODA YOKTUR — ve olmamalıdır.
--  Tabloda yalnızca SHA-256 özeti durur; buradaki özetler rastgele
--  üretilmiş, hiçbir yerde yazılı olmayan dizelere aittir. Depoyu
--  indiren herkesin bildiği hazır bir jeton, herkese açık bir kapı
--  demek olurdu.
--
--  O hâlde bu satırlar niye var? Boş bir liste, jeton yönetiminin
--  neye benzediğini anlatamaz. Bu üç satır listenin BÜTÜN
--  durumlarını gösterir:
--      #1  read + write  · dakikalar önce kullanılmış (etkin)
--      #2  read          · dün kullanılmış, salt okunur
--      #3  read + write  · İPTAL EDİLMİŞ (revoked_at dolu)
--
--  Kendi jetonunuzu panelden üretin; açık metin yalnızca üretildiği
--  anda BİR KEZ gösterilir:
--      Giriş → API Jetonları → "Jeton üret"
--
--  parola özetleri gibi jeton özetleri de tuzsuzdur; jeton zaten
--  32 bayt rastgeledir, sözlük saldırısına konu olmaz.
-- ===============================================================

INSERT INTO `api_tokens`
    (`id`, `user_id`, `name`, `token_hash`, `scopes`, `request_count`,
     `last_used_at`, `last_used_ip`, `revoked_at`, `created_at`)
VALUES
(1, 1, 'Mobil uygulama',
 'bc18f268d28b6e5ab775e9813886f61b59b4f81e27ca54f5e1c817406297c07a',
 'read,write', 2418, NOW() - INTERVAL 12 MINUTE, '127.0.0.1', NULL, NOW() - INTERVAL 21 DAY),

(2, 1, 'Rapor betiği (salt okunur)',
 '97135353dfa44a29d83806ab8911f40dbcd513975ad4c79d1caae5873f35694c',
 'read', 96, NOW() - INTERVAL 1 DAY, '127.0.0.1', NULL, NOW() - INTERVAL 9 DAY),

(3, 2, 'Eski entegrasyon',
 '05be673a8d42f461c6644989c8948f05f23ee48af374e7534adc22e0ce92bdf5',
 'read,write', 1204, NOW() - INTERVAL 34 DAY, '127.0.0.1', NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 60 DAY);

ALTER TABLE `api_tokens` AUTO_INCREMENT = 11;


-- ---------------------------------------------------------------
--  ÖRNEK İSTEK KAYITLARI (hız sınırı penceresi)
-- ---------------------------------------------------------------
--  api_requests, hız sınırının saydığı tablodur ve bir saatten eski
--  satırlar kendiliğinden temizlenir. Bu yüzden burada yalnızca
--  SON BİR SAATE yayılmış birkaç istek var.
--
--  Bu satırlar SİZİN sınırınızı yemez: sayım token_id başına
--  yapılır, bunlar da yukarıdaki örnek jetonlara aittir. Panelde
--  üreteceğiniz yeni jeton sıfırdan 60 hakla başlar.
-- ---------------------------------------------------------------
INSERT INTO `api_requests` (`token_id`, `ip`, `requested_at`)
SELECT 1, '127.0.0.1', NOW() - INTERVAL (n * 97) SECOND
  FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
        UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
        UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
        UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
        UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
        UNION ALL SELECT 16 UNION ALL SELECT 17) AS seq;

INSERT INTO `api_requests` (`token_id`, `ip`, `requested_at`)
SELECT 2, '127.0.0.1', NOW() - INTERVAL (n * 311) SECOND
  FROM (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7
        UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10) AS seq;


-- ---------------------------------------------------------------
--  DENEMEK İÇİN
-- ---------------------------------------------------------------
--  Panelden bir jeton üretin, sonra:
--      curl -H "Authorization: Bearer <jetonunuz>" --           "http://localhost/rest-api-system/api/v1/users?per=10&page=2"
