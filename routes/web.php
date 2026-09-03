<?php
/**
 * =====================================================================
 *  ROTA TABLOSU
 * ---------------------------------------------------------------------
 *  İKİ AYRI DÜNYA, İKİ AYRI GÜVENLİK MODELİ:
 *
 *    PANEL (HTML)          →  'auth' + 'csrf'
 *        Çerez tabanlı oturum. Tarayıcı çerezi otomatik eklediği için
 *        CSRF riski vardır; veri değiştiren her istek anahtar ister.
 *
 *    API (JSON)            →  'api' + 'scope:read|write'
 *        Durumsuz. Kimlik her istekte Authorization başlığıyla AÇIKÇA
 *        gönderilir; tarayıcı bunu kendiliğinden ekleyemediği için
 *        CSRF yüzeyi oluşmaz ve anahtar ARANMAZ.
 *
 *  ROTA SIRASI ÖNEMLİDİR: Sabit yollar ("api/v1/users") desenli
 *  yollardan ("api/v1/users/{id}") ÖNCE tanımlanmalıdır.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\ApiResponse;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Http\Controllers\Api\PreferenceApiController;
use App\Http\Controllers\Api\V1\UserApiController;
use App\Http\Controllers\ApiDocController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UserController;

$router = new Router();

/* =====================================================================
 *  PANEL
 * ================================================================== */

$router->get('',       AuthController::class, 'showLogin', ['guest']);
$router->get('home',   AuthController::class, 'showLogin', ['guest']);
$router->get('login',  AuthController::class, 'showLogin', ['guest']);
$router->post('login', AuthController::class, 'login',     ['guest', 'csrf']);
$router->post('logout', AuthController::class, 'logout',   ['auth', 'csrf']);

$router->get('dashboard', DashboardController::class, 'index', ['auth']);

// SAYFALAMA ÖRNEĞİ (HTML): filtreler ve sayfa adres çubuğunda taşınır.
$router->get('users', UserController::class, 'index', ['auth']);

/* API belgeleri – panelde, oturum arkasında.
 *
 * ADRES NEDEN "docs" DEĞİL?
 * Projede diskte GERÇEK bir "docs/" klasörü var (README'nin ekran
 * görüntüleri orada). .htaccess'teki temiz adres kuralı, istenen yol
 * diskte var olan bir KLASÖRE denk geliyorsa isteği index.php'ye
 * DEVRETMEZ (RewriteCond %{REQUEST_FILENAME} !-d). Yani "/docs"
 * adresi hiçbir zaman bu rotaya ulaşmıyor, doğrudan o klasöre
 * gidiyordu. Rotaya klasörle çakışmayan bir ad vermek, .htaccess'i
 * özel durumlarla doldurmaktan daha sağlam bir çözüm. */
$router->get('api-belgeleri', ApiDocController::class, 'index', ['auth']);

/* Jeton yönetimi PANELDEDİR, API'de değil.
 * Jeton üretmek için API jetonu gerekseydi "tavuk-yumurta" sorunu
 * doğardı: ilk jetonu nasıl alacaktınız? */
$router->get('tokens',         TokenController::class, 'index',  ['auth']);

/* Örnek kullanım dosyası (cURL / PHP / JS / Python).
 * GET'tir çünkü hiçbir şeyi DEĞİŞTİRMEZ: yalnızca metin üretip indirtir.
 * Dosyaya gerçek jeton yazılmaz; bkz. TokenController::ornek(). */
$router->get('tokens/ornek',   TokenController::class, 'ornek',  ['auth']);
$router->post('tokens',        TokenController::class, 'store',  ['auth', 'csrf']);
$router->post('tokens/revoke', TokenController::class, 'revoke', ['auth', 'csrf']);

$router->post('api/preferences/theme', PreferenceApiController::class, 'theme', ['auth', 'csrf']);

/* =====================================================================
 *  REST API – SÜRÜM 1
 * ---------------------------------------------------------------------
 *  Sürümü adreste taşımak, yayınlanmış bir API'yi bozmadan
 *  geliştirebilmenin en anlaşılır yoludur: v2 çıktığında v1 yerinde
 *  kalır ve mevcut istemciler çalışmaya devam eder.
 * ================================================================== */

$router->get('api/v1/users',  UserApiController::class, 'index', ['api', 'scope:read']);
$router->post('api/v1/users', UserApiController::class, 'store', ['api', 'scope:write']);

/* Desenli rotalar SONRA gelir. Router önce tam eşleşmeye baktığı için
 * sıra aslında güvenlidir; yine de okuyanın kafası karışmasın diye
 * sabitten değişkene doğru yazıyoruz. */
$router->get('api/v1/users/{id}',    UserApiController::class, 'show',    ['api', 'scope:read']);
$router->patch('api/v1/users/{id}',  UserApiController::class, 'update',  ['api', 'scope:write']);
$router->delete('api/v1/users/{id}', UserApiController::class, 'destroy', ['api', 'scope:write']);

/* =====================================================================
 *  BULUNAMAYAN ADRESLER
 * ---------------------------------------------------------------------
 *  API isteğine HTML hata sayfası döndürmek, istemcinin JSON
 *  ayrıştırıcısını patlatır ve gerçek sorunu gizler. Bu yüzden yolun
 *  "api/" ile başlayıp başlamadığına bakıp doğru biçimde yanıtlıyoruz.
 * ================================================================== */
$router->fallback(static function (Request $request, string $path): void {
    if ($request->isApi()) {
        ApiResponse::error('not_found', 'İstenen uç nokta bulunamadı: ' . $path, 404);
    }

    if ($request->isAjax()) {
        App\Core\Response::error('İstenen uç nokta bulunamadı.', 404);
    }

    http_response_code(404);

    View::render('errors/404', [
        'title' => 'Sayfa Bulunamadı',
        'path'  => $path,
    ], App\Core\Auth::check() ? 'layouts/admin' : 'layouts/plain');
});

return $router;
