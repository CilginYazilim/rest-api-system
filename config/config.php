<?php
/**
 * =====================================================================
 *  UYGULAMA AYARLARI  (tek merkez)
 *  cilginyazilim.com – REST API Sistemi
 * ---------------------------------------------------------------------
 *  Eski sürümde ayarlar "define()" ile dağınık haldeydi. Artık hepsi
 *  tek bir DİZİ olarak burada duruyor ve `Config` sınıfı üzerinden
 *  nokta notasyonuyla okunuyor:
 *
 *      Config::get('db.host')
 *      Config::get('session.idle_timeout')
 *
 *  ŞİFRELERİ BURAYA YAZMAYIN. Kök dizindeki ".env" dosyasını
 *  (".env.example" dosyasını kopyalayarak) kullanın; env() fonksiyonu
 *  önce .env dosyasına, sonra sunucu ortam değişkenlerine, en son
 *  buradaki varsayılana bakar.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Env;

return [

    /* -----------------------------------------------------------------
     *  UYGULAMA
     * -------------------------------------------------------------- */
    'app' => [
        'name'     => 'CY REST API',
        'brand'    => 'Çılgın Yazılım',
        'url'      => Env::get('APP_URL', ''),

        /**
         * debug = true  → hatalar ekranda görünür (GELİŞTİRME)
         * debug = false → hatalar sadece log'a yazılır (CANLI)
         *
         * VARSAYILAN ORTAMDAN TÜRETİLİR: .env dosyası yoksa (klonlanan
         * depoda yoktur, .gitignore içindedir) yerelde açık, gerçek bir
         * alan adında kapalı olur. Yani birisi .env oluşturmadan canlıya
         * atarsa hata yığını ziyaretçiye görünmez. Kararı elle vermek
         * isterseniz .env içine APP_DEBUG=true|false yazın.
         */
        'debug'    => Env::bool('APP_DEBUG', Env::isLocalHost()),

        'timezone' => 'Europe/Istanbul',

        /**
         * pretty_urls = true  → "/rest-api-system/users"        (varsayılan)
         *               false → "/rest-api-system/index.php?r=users"
         *
         * Temiz adres için kökteki .htaccess ve Apache'de mod_rewrite
         * gerekir; ikisi de bu depoyla birlikte hazır gelir.
         *
         * mod_rewrite kapalı bir sunucuda çalışıyorsanız .env içinde
         * APP_PRETTY_URLS=false yapmanız yeterlidir; uygulama hiçbir
         * kod değişikliği olmadan eski adres biçimine döner.
         */
        'pretty_urls' => Env::bool('APP_PRETTY_URLS', true),
    ],

    /* -----------------------------------------------------------------
     *  VERİTABANI
     * -------------------------------------------------------------- */
    'db' => [
        'host'    => Env::get('DB_HOST', '127.0.0.1'),
        'port'    => (int) Env::get('DB_PORT', '3306'),
        'name'    => Env::get('DB_NAME', 'cy_rest_api'),
        'user'    => Env::get('DB_USER', 'root'),
        'pass'    => Env::get('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    /* -----------------------------------------------------------------
     *  OTURUM (SESSION) GÜVENLİĞİ
     * -------------------------------------------------------------- */
    'session' => [
        'name'      => 'CYRESTSESS',

        // Hareketsizlik süresi (saniye). 30 dakika boyunca istek
        // gelmezse oturum düşürülür.
        'idle_timeout' => 1800,

        // Oturum kimliği bu sürede bir yenilenir (session fixation
        // saldırılarına karşı). 15 dakika.
        'regenerate_every' => 900,

        // "Beni hatırla" çerezinin ömrü (saniye) – 30 gün.
        'remember_lifetime' => 60 * 60 * 24 * 30,
    ],

    /* -----------------------------------------------------------------
     *  GÜVENLİK
     * -------------------------------------------------------------- */
    'security' => [
        // Aynı e-posta/IP için art arda kaç hatalı girişe izin verilir.
        'login_max_attempts' => 5,

        // Limit aşılınca kaç saniye kilitlenir (15 dakika).
        'login_lockout'      => 900,

        // Hatalı giriş sayacının sıfırlanma penceresi (15 dakika).
        'login_window'       => 900,

        // Parola kuralları
        'password_min'       => 8,

        // Content-Security-Policy başlığı gönderilsin mi?
        'csp_enabled'        => true,
    ],

    /* -----------------------------------------------------------------
     *  DOĞRULAMA SINIRLARI (veritabanı VARCHAR uzunluklarıyla uyumlu)
     * -------------------------------------------------------------- */
    'validation' => [
        'name_min' => 2,
        'name_max' => 150,
    ],

    /* -----------------------------------------------------------------
     *  SOL MENÜ
     * -----------------------------------------------------------------
     *  Menü tanımı ayrı bir dosyada durur: ayarlarla menü farklı
     *  hızlarda değişir. Yeni sayfa eklerken yalnızca menu.php'ye
     *  dokunursunuz; veritabanı ayarlarına hiç yaklaşmazsınız.
     * -------------------------------------------------------------- */
    'menu' => require __DIR__ . '/menu.php',
];
