<?php
/**
 * =====================================================================
 *  ApiDocController – API belgeleri
 * ---------------------------------------------------------------------
 *  BELGESİZ API, OLMAYAN API'DİR.
 *  Uç nokta listesini elle yazılmış bir HTML sayfasına gömmek yerine
 *  BİR DİZİDEN üretiyoruz. Böylece yeni bir uç nokta eklendiğinde
 *  belge de aynı yerden güncellenir; "kod değişti, belge unutuldu"
 *  durumu zorlaşır.
 *
 *  Gerçek projelerde bu diziyi OpenAPI (Swagger) belgesine
 *  dönüştürmek de mümkündür; buradaki amaç yapıyı göstermektir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Http\Controller;
use App\Support\ApiExamples;

final class ApiDocController extends Controller
{
    public function index(Request $request): void
    {
        // Belgede gösterilecek TAM adres. Kullanıcı kopyalayıp
        // doğrudan çalıştırabilsin diye alan adını da ekliyoruz.
        $base = (Session::isHttps() ? 'https://' : 'http://')
              . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . Router::basePath()
              . '/api/v1';

        $this->view('api/docs', [
            'title'     => 'API Belgeleri',
            'subtitle'  => 'Sürüm v1',
            'baseUrl'   => $base,
            'endpoints' => self::endpoints(),

            // Örnek kullanım kodları jeton sayfasıyla ortak parçadan gelir.
            'ornekler'  => ApiExamples::tumu(ApiExamples::tabanUrl(), ApiExamples::ORNEK_JETON),
            'scripts'   => ['tokens.js'],
        ]);
    }

    /**
     * Uç nokta tanımları.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function endpoints(): array
    {
        return [
            [
                'method'  => 'GET',
                'path'    => '/users',
                'scope'   => 'read',
                'summary' => 'Kullanıcıları sayfa sayfa listeler.',
                'params'  => [
                    'q'      => 'Ad, soyad veya e-postada arama yapar.',
                    'status' => '"active" veya "passive". Boş bırakılırsa hepsi.',
                    'page'   => 'Sayfa numarası (varsayılan 1).',
                    'per'    => 'Sayfa başına kayıt: 10, 20, 50 veya 100 (varsayılan 20).',
                ],
                'response' => <<<'JSON'
{
  "data": [
    { "id": 51, "name": "Beyza", "surname": "SARI", "email": "…", "is_active": true }
  ],
  "meta": {
    "total": 51, "per_page": 20, "current_page": 1,
    "last_page": 3, "from": 1, "to": 20, "has_more": true
  },
  "links": { "self": "…", "next": "…", "prev": null }
}
JSON,
            ],

            [
                'method'  => 'GET',
                'path'    => '/users/{id}',
                'scope'   => 'read',
                'summary' => 'Tek bir kullanıcıyı döndürür.',
                'params'  => ['id' => 'Kullanıcı kimliği (adreste taşınır).'],
                'response' => <<<'JSON'
{ "data": { "id": 5, "name": "Elif", "surname": "KAYA", "email": "…" } }
JSON,
            ],

            [
                'method'  => 'POST',
                'path'    => '/users',
                'scope'   => 'write',
                'summary' => 'Yeni kullanıcı oluşturur. Başarılıysa 201 döner ve Location başlığında kaydın adresini verir.',
                'params'  => [
                    'name'      => 'Ad (zorunlu).',
                    'surname'   => 'Soyad (zorunlu).',
                    'email'     => 'Benzersiz e-posta (zorunlu).',
                    'password'  => 'En az 8 karakter (zorunlu).',
                    'is_active' => 'true / false (varsayılan true).',
                ],
                'request' => <<<'JSON'
{
  "name": "Yeni",
  "surname": "Kullanıcı",
  "email": "yeni@ornek.com",
  "password": "GucluParola1"
}
JSON,
                'response' => <<<'JSON'
201 Created
Location: /rest-api-system/api/v1/users/52

{ "data": { "id": 52, "name": "Yeni", "…": "…" } }
JSON,
            ],

            [
                'method'  => 'PATCH',
                'path'    => '/users/{id}',
                'scope'   => 'write',
                'summary' => 'Yalnızca gönderilen alanları günceller. Göndermediğiniz alanlara dokunulmaz.',
                'params'  => [
                    'name'      => 'İsteğe bağlı.',
                    'surname'   => 'İsteğe bağlı.',
                    'email'     => 'İsteğe bağlı; başka hesapta kayıtlıysa 409 döner.',
                    'is_active' => 'İsteğe bağlı.',
                ],
                'request' => <<<'JSON'
{ "is_active": false }
JSON,
                'response' => <<<'JSON'
{ "data": { "id": 52, "is_active": false, "…": "…" } }
JSON,
            ],

            [
                'method'  => 'DELETE',
                'path'    => '/users/{id}',
                'scope'   => 'write',
                'summary' => 'Kullanıcıyı siler. Başarılıysa gövdesiz 204 döner.',
                'params'  => ['id' => 'Silinecek kullanıcının kimliği.'],
                'response' => <<<'TEXT'
204 No Content
TEXT,
            ],
        ];
    }
}
