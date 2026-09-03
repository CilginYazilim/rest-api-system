<?php
/**
 * =====================================================================
 *  ÖN DENETLEYİCİ (Front Controller) – Uygulamanın TEK giriş noktası
 *  cilginyazilim.com – REST API Sistemi
 * ---------------------------------------------------------------------
 *  Tarayıcıdan gelen HER istek bu dosyadan geçer. Neden?
 *
 *    - Güvenlik kontrolleri (oturum, başlıklar, hata yakalama) TEK
 *      yerde yapılır; bir sayfada unutma riski kalmaz
 *    - Uygulama kodu web kökünün dışında/korumalı klasörlerde durur
 *    - Adres yapısı koddan bağımsızlaşır
 *
 *  AÇILIŞ SIRASI:
 *    1. Sabitler        → CY_BASE (proje kök klasörü)
 *    2. Autoloader      → sınıflar otomatik yüklensin
 *    3. .env + ayarlar  → veritabanı bilgileri, hata modu
 *    4. Hata yönetimi   → beklenmeyen durumda düzgün sayfa göster
 *    5. Oturum          → sertleştirilmiş session
 *    6. Güvenlik başlıkları
 *    7. Rotalar         → isteği doğru denetleyiciye ilet
 * =====================================================================
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
 *  1) SABİTLER
 * ------------------------------------------------------------------ */
// Proje kök klasörünün TAM yolu. Göreli yol ("../app") yerine bunu
// kullanmak, dosyanın nereden çağrıldığından bağımsız olarak çalışır.
define('CY_BASE', __DIR__);
define('CY_START', microtime(true));

/* ---------------------------------------------------------------------
 *  2) AUTOLOADER
 * ------------------------------------------------------------------ */
require CY_BASE . '/app/Core/Env.php';
require CY_BASE . '/app/Core/Autoloader.php';

App\Core\Autoloader::register('App', CY_BASE . '/app');

use App\Core\Auth;
use App\Core\Config;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/* ---------------------------------------------------------------------
 *  3) ORTAM DEĞİŞKENLERİ VE AYARLAR
 * ------------------------------------------------------------------ */
Env::load(CY_BASE . '/.env');
Config::load(CY_BASE . '/config/config.php');

date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Istanbul'));

$debug = (bool) Config::get('app.debug', false);

/* Hata gösterimi:
 *   debug açık  → ekranda tüm ayrıntı (geliştirme)
 *   debug kapalı→ hiçbir şey gösterme, sadece log'a yaz (canlı)
 * Canlıda hata detayı göstermek, tablo/sütun adlarınızı ve dosya
 * yollarınızı saldırgana bildirir. */
error_reporting($debug ? E_ALL : 0);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

/* ---------------------------------------------------------------------
 *  4) MERKEZİ HATA YÖNETİMİ
 * ---------------------------------------------------------------------
 *  Nerede olursa olsun yakalanmamış bir hata buraya düşer. Kullanıcı
 *  çirkin bir PHP hata yığını yerine düzgün bir sayfa görür; ayrıntı
 *  log dosyasına yazılır.
 * ------------------------------------------------------------------ */
set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log('[CY] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    // Yarım kalan çıktıyı temizle ki hata sayfası bozuk görünmesin.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $message = $debug
        ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.';

    /* API İSTEĞİNE ASLA HTML DÖNDÜRMEYİN.
     * Beklenmeyen bir hatada hata SAYFASI basarsak, istemcinin JSON
     * ayrıştırıcısı "Unexpected token <" diye patlar ve geliştirici
     * asıl sorunu göremez. Yolun "api/" ile başlayıp başlamadığına
     * bakıp API zarfıyla yanıtlıyoruz. */
    if (str_starts_with(App\Core\Router::currentPath(), 'api/')) {
        App\Core\ApiResponse::error('server_error', $message, 500);
    }

    if ($isAjax) {
        Response::error($message, 500);
    }

    http_response_code(500);

    try {
        View::render('errors/500', ['title' => 'Sunucu Hatası', 'message' => $message], 'layouts/plain');
    } catch (Throwable) {
        // Görünüm de patlarsa en azından düz metin gösterelim.
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
});

/* PHP uyarılarını (warning/notice) da istisnaya çeviriyoruz.
 * Sessizce yanlış çalışan kod yerine hatayı hemen görmek daha iyidir. */
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    // @ ile bastırılmış hataları yok say (bilerek bastırılmışlardır).
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

/* ---------------------------------------------------------------------
 *  5) OTURUM + 6) GÜVENLİK BAŞLIKLARI
 * ------------------------------------------------------------------ */
Session::start();
Response::securityHeaders();

/* ---------------------------------------------------------------------
 *  7) YARDIMCI FONKSİYONLAR VE ROTALAR
 * ------------------------------------------------------------------ */
require CY_BASE . '/app/Support/helpers.php';

$request = new Request();

// Tüm görünümlerde erişilebilecek ortak veriler.
View::share([
    'appName'     => Config::get('app.name'),
    'appBrand'    => Config::get('app.brand'),
    'currentUser' => Auth::user(),
]);

/** @var App\Core\Router $router */
$router = require CY_BASE . '/routes/web.php';

$router->dispatch($request);
