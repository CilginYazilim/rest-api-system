<?php
/**
 * =====================================================================
 *  Router – İsteği doğru denetleyiciye (controller) yönlendirir
 * ---------------------------------------------------------------------
 *  TEK GİRİŞ NOKTASI kökteki index.php'dir; tüm istekler oradan geçer
 *  ve güvenlik kontrolleri tek yerde yapılır.
 *
 *  BU SÜRÜM REST İÇİN GENİŞLETİLMİŞTİR
 *  ---------------------------------------------------------------
 *  1. YOL PARAMETRELERİ:   users/{id}
 *     REST'te kaynak kimliği ADRESTE taşınır:
 *         GET /api/v1/users/42        ✔ kaynağın kendisi
 *         GET /api/v1/users?id=42     ✘ "kullanıcı listesinin bir filtresi"
 *     Bu ayrım önemsiz görünür ama önbellek, günlük ve yetkilendirme
 *     kurallarının tamamı adrese bakarak çalışır.
 *
 *  2. TÜM HTTP METOTLARI:  GET · POST · PUT · PATCH · DELETE
 *     Aynı adres, metoda göre farklı iş yapar:
 *         GET    /api/v1/users/42  → oku
 *         PATCH  /api/v1/users/42  → kısmi güncelle
 *         DELETE /api/v1/users/42  → sil
 *
 *  EŞLEŞTİRME SIRASI: Önce TAM eşleşme aranır (sabit yollar hızlıdır),
 *  bulunamazsa desenli rotalar sırayla denenir. Böylece "users/new"
 *  gibi sabit bir yol, "users/{id}" tarafından yutulmaz — yeter ki
 *  sabit rota önce tanımlansın.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * Sabit (parametresiz) rotalar. Anahtar: "GET users"
     *
     * @var array<string,array{0:string,1:string,2:array<int,string>}>
     */
    private array $static = [];

    /**
     * Desenli rotalar. Sıra korunur; ilk eşleşen kazanır.
     *
     * @var array<int,array{verb:string,segments:array<int,string>,controller:string,method:string,middleware:array<int,string>}>
     */
    private array $dynamic = [];

    private ?\Closure $notFound = null;

    /* =================================================================
     *  TANIMLAMA
     * ============================================================== */

    /** @param array<int,string> $middleware */
    public function get(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('GET', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function post(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('POST', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function put(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('PUT', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function patch(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('PATCH', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    public function delete(string $path, string $controller, string $method, array $middleware = []): self
    {
        return $this->add('DELETE', $path, $controller, $method, $middleware);
    }

    /** Hem GET hem POST kabul eden rota. @param array<int,string> $middleware */
    public function any(string $path, string $controller, string $method, array $middleware = []): self
    {
        $this->add('GET', $path, $controller, $method, $middleware);

        return $this->add('POST', $path, $controller, $method, $middleware);
    }

    /** @param array<int,string> $middleware */
    private function add(string $verb, string $path, string $controller, string $method, array $middleware): self
    {
        $path = trim($path, '/');

        // Süslü parantez yoksa bu sabit bir yoldur; sözlüğe koyup
        // O(1) hızında buluruz.
        if (!str_contains($path, '{')) {
            $this->static[$verb . ' ' . $path] = [$controller, $method, $middleware];

            return $this;
        }

        $this->dynamic[] = [
            'verb'       => $verb,
            'segments'   => explode('/', $path),
            'controller' => $controller,
            'method'     => $method,
            'middleware' => $middleware,
        ];

        return $this;
    }

    /** Rota bulunamazsa çalışacak fonksiyon. */
    public function fallback(\Closure $handler): self
    {
        $this->notFound = $handler;

        return $this;
    }

    /* =================================================================
     *  ADRES ÇÖZÜMLEME
     * ============================================================== */

    /**
     * Adres çubuğundaki rotayı okur ve temizler.
     *
     * GÜVENLİK: Yalnızca harf, rakam, tire, alt çizgi ve eğik çizgiye
     * izin veriyoruz. Böylece "../" gibi yol kaçışları en baştan elenir.
     */
    public static function currentPath(): string
    {
        $raw = (string) ($_GET['r'] ?? '');

        // Temiz adres (pretty URL) kullanılıyorsa .htaccess bunu doldurur.
        if ($raw === '' && isset($_SERVER['PATH_INFO'])) {
            $raw = (string) $_SERVER['PATH_INFO'];
        }

        $clean = preg_replace('#[^a-zA-Z0-9/_-]#', '', $raw) ?? '';
        $clean = trim(preg_replace('#/+#', '/', $clean) ?? '', '/');

        return $clean === '' ? 'home' : $clean;
    }

    /**
     * Uygulamanın tarayıcıdaki KÖK YOLU.
     *
     *   http://localhost/rest-api-system/index.php  →  "/rest-api-system"
     *   http://example.com/index.php                →  ""
     *
     * Bağlantıları MUTLAK üretmek şarttır: temiz adres modunda
     * "api/v1/users/42" gibi çok seviyeli bir adreste göreli bağlantılar
     * (CSS/JS dahil) kırılır.
     */
    public static function basePath(): string
    {
        static $base = null;

        if ($base !== null) {
            return $base;
        }

        $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));

        return $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }

    /* =================================================================
     *  ÇALIŞTIRMA
     * ============================================================== */

    public function dispatch(Request $request): void
    {
        $path = self::currentPath();

        /* HEAD, "gövdesiz GET" demektir; tarayıcılar ve izleme araçları
         * bunu kullanır. Ayrı rota tanımlamak yerine GET'e eşliyoruz. */
        $verb = $request->method() === 'HEAD' ? 'GET' : $request->method();

        /* --- 1) Sabit rota --------------------------------------- */
        if (isset($this->static[$verb . ' ' . $path])) {
            [$controller, $method, $middleware] = $this->static[$verb . ' ' . $path];

            $this->run($controller, $method, $middleware, $request, []);

            return;
        }

        /* --- 2) Desenli rota -------------------------------------- */
        $segments = explode('/', $path);

        foreach ($this->dynamic as $route) {
            if ($route['verb'] !== $verb || count($route['segments']) !== count($segments)) {
                continue;
            }

            $params = self::matchSegments($route['segments'], $segments);

            if ($params === null) {
                continue;
            }

            $this->run($route['controller'], $route['method'], $route['middleware'], $request, $params);

            return;
        }

        /* --- 3) Eşleşme yok --------------------------------------- */
        $this->miss($request, $path, $verb, $segments);
    }

    /**
     * Desen ile gerçek yolu karşılaştırır.
     *
     * @param array<int,string> $pattern ['api','v1','users','{id}']
     * @param array<int,string> $actual  ['api','v1','users','42']
     *
     * @return array<string,string>|null Eşleşmezse null
     */
    private static function matchSegments(array $pattern, array $actual): ?array
    {
        $params = [];

        foreach ($pattern as $index => $segment) {
            // {id} → değişken; adresteki karşılığını yakala.
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                $params[trim($segment, '{}')] = $actual[$index];

                continue;
            }

            // Sabit parça birebir eşleşmeli.
            if ($segment !== $actual[$index]) {
                return null;
            }
        }

        return $params;
    }

    /**
     * Ara katmanları çalıştırır, sonra denetleyiciyi çağırır.
     *
     * @param array<int,string>   $middleware
     * @param array<string,string> $params
     */
    private function run(string $controllerClass, string $method, array $middleware, Request $request, array $params): void
    {
        foreach ($middleware as $rule) {
            Middleware::handle($rule, $request);
        }

        $controller = new $controllerClass();

        /* Yol parametreleri İKİNCİ argüman olarak geçilir:
         *     public function show(Request $request, array $params)
         * Parametre almayan denetleyiciler etkilenmez; PHP, kullanıcı
         * tanımlı bir metoda fazladan argüman geçilmesine izin verir. */
        $controller->{$method}($request, $params);
    }

    /**
     * Eşleşme bulunamadığında.
     *
     * @param array<int,string> $segments
     */
    private function miss(Request $request, string $path, string $verb, array $segments): void
    {
        /* ADRES VAR AMA METOT YANLIŞ mı?
         * Bunu 404 yerine 405 ile ayırt etmek, API kullanan geliştiricinin
         * hatayı saniyeler içinde bulmasını sağlar: "adres yanlış" ile
         * "PUT yerine PATCH göndermelisin" çok farklı sorunlardır. */
        $allowed = [];

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $candidate) {
            if ($candidate === $verb) {
                continue;
            }

            if (isset($this->static[$candidate . ' ' . $path])) {
                $allowed[] = $candidate;
                continue;
            }

            foreach ($this->dynamic as $route) {
                if ($route['verb'] === $candidate
                    && count($route['segments']) === count($segments)
                    && self::matchSegments($route['segments'], $segments) !== null) {
                    $allowed[] = $candidate;
                    break;
                }
            }
        }

        if ($allowed !== []) {
            /* Allow başlığı zorunludur (RFC 9110): 405 döndüren bir
             * yanıt, hangi metotların kabul edildiğini SÖYLEMELİDİR. */
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', array_unique($allowed)));
            }

            if ($request->isApi()) {
                ApiResponse::error(
                    'method_not_allowed',
                    'Bu adres için geçersiz istek yöntemi.',
                    405,
                    ['allowed' => array_values(array_unique($allowed))]
                );
            }

            http_response_code(405);
        }

        if ($this->notFound !== null) {
            ($this->notFound)($request, $path);

            return;
        }

        http_response_code(404);
        echo 'Sayfa bulunamadı.';
    }
}
