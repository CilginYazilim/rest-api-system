<?php
/**
 * =====================================================================
 *  Request – Gelen HTTP isteğini temsil eder
 * ---------------------------------------------------------------------
 *  $_POST / $_GET / $_FILES süper globallerine kodun her yerinden
 *  dokunmak yerine tek kapıdan geçiyoruz. Faydası:
 *
 *   - Girdi her zaman aynı biçimde temizlenir
 *   - Kodu test etmek kolaylaşır (sahte Request üretilebilir)
 *   - "Bu değer nereden geldi?" sorusunun cevabı tek yerdedir
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $body;

    /** @var array<string,mixed> */
    private array $files;

    public function __construct()
    {
        $this->query = $_GET;
        $this->files = $_FILES;

        /* GÖVDEYİ NEDEN KENDİMİZ OKUYORUZ?
         * PHP $_POST dizisini YALNIZCA iki durumda doldurur:
         *   - metot POST ise ve
         *   - içerik türü form-urlencoded veya multipart ise
         *
         * REST istemcileri ise genellikle JSON gönderir ve PUT/PATCH/
         * DELETE kullanır. Bu iki durumda $_POST BOŞ kalır; kod
         * "alan eksik" der ve saatlerce sebebi aranır.
         *
         * Ham gövdeyi okuyup JSON'sa çözerek bu tuzağı kapatıyoruz.
         * Böylece denetleyiciler, isteğin form mu JSON mu olduğunu
         * hiç bilmeden aynı input() metoduyla çalışır. */
        $this->body = $_POST !== [] ? $_POST : self::parseBody();
    }

    /**
     * Ham istek gövdesini diziye çevirir.
     *
     * @return array<string,mixed>
     */
    private static function parseBody(): array
    {
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $raw  = (string) file_get_contents('php://input');

        if ($raw === '') {
            return [];
        }

        if (str_contains($type, 'application/json')) {
            $decoded = json_decode($raw, true);

            /* Bozuk JSON'u SESSİZCE yutmuyoruz; boş dizi döndürmek
             * "alanları göndermemişsin" gibi yanıltıcı bir hataya yol
             * açardı. İstemciye ne olduğunu söylemek daha yararlıdır. */
            if (json_last_error() !== JSON_ERROR_NONE) {
                ApiResponse::error('invalid_json', 'İstek gövdesi geçerli bir JSON değil.', 400);
            }

            return is_array($decoded) ? $decoded : [];
        }

        // PUT/PATCH ile gönderilen klasik form verisi.
        parse_str($raw, $parsed);

        return $parsed;
    }

    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * İstek AJAX ile mi geldi?
     * jQuery her AJAX isteğine X-Requested-With başlığını ekler.
     */
    public function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /**
     * Bu bir API isteği mi?
     *
     * Ayrımı YOLA bakarak yapıyoruz, başlığa değil: hata durumunda
     * ne döndüreceğimizi (HTML sayfa mı, JSON mu) bu belirler.
     * "Accept: application/json" başlığına güvenmek kırılgandır —
     * curl ve tarayıcı adres çubuğu bu başlığı göndermez, ama
     * /api/v1/... adresini isteyen herkes JSON bekler.
     */
    public function isApi(): bool
    {
        return str_starts_with(Router::currentPath(), 'api/');
    }

    /**
     * Authorization başlığındaki Bearer jetonu.
     *
     * DİKKAT: Bazı Apache yapılandırmalarında Authorization başlığı
     * PHP'ye HİÇ ulaşmaz (CGI/FastCGI modunda güvenlik gerekçesiyle
     * ayıklanır). Bu yüzden apache_request_headers() yedeğine de
     * bakıyoruz — "jetonum çalışmıyor" şikâyetlerinin sessiz sebebi
     * çoğu zaman budur.
     */
    public function bearerToken(): string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '');

        if ($header === '' && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $header = (string) $value;
                    break;
                }
            }
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) !== 1) {
            return '';
        }

        return trim($matches[1]);
    }

    /** Gövdenin tamamı (toplu doğrulama için). @return array<string,mixed> */
    public function all(): array
    {
        return $this->body + $this->query;
    }

    /** Alan istekte GERÇEKTEN var mı? (PATCH'te "gönderilmedi" ile "boş" farklıdır) */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    /**
     * POST veya GET'ten metin okur; baştaki/sondaki boşlukları temizler.
     * Dizi gelirse (beklenmedik girdi) boş string döner.
     */
    public function input(string $key, string $default = ''): string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** Ham değeri döndürür (dizi olabilir; DataTables parametreleri gibi). */
    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Tam sayı okur. Geçersizse (örn. "5abc", "1 OR 1=1") null döner.
     * filter_var, elle (int) dönüşümünden çok daha katıdır:
     *   (int) "5abc"        → 5   (sessizce yanlış)
     *   filter_var("5abc")  → false (hata yakalanır)
     */
    public function int(string $key, ?int $min = null, ?int $max = null): ?int
    {
        $raw = $this->body[$key] ?? $this->query[$key] ?? null;

        if ($raw === null || is_array($raw)) {
            return null;
        }

        $options = [];

        if ($min !== null) {
            $options['min_range'] = $min;
        }
        if ($max !== null) {
            $options['max_range'] = $max;
        }

        $value = filter_var((string) $raw, FILTER_VALIDATE_INT, ['options' => $options]);

        return $value === false ? null : $value;
    }

    /** Onay kutusu okuma: "1", "on", "true" → true */
    public function bool(string $key): bool
    {
        $raw = strtolower($this->input($key));

        return in_array($raw, ['1', 'on', 'true', 'yes'], true);
    }

    /** @return array<string,mixed>|null Yüklenen dosya bilgisi */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /**
     * Dosya alanı gerçekten doldurulmuş mu?
     * UPLOAD_ERR_NO_FILE, "kullanıcı dosya seçmedi" demektir; bu bir
     * hata değildir, çoğu formda görsel zorunlu değildir.
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);

        return $file !== null
            && isset($file['error'])
            && !is_array($file['error'])
            && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * İstemcinin IP adresi.
     *
     * DİKKAT: X-Forwarded-For başlığı KOLAYCA SAHTE ÜRETİLİR. Yalnızca
     * güvendiğiniz bir vekil sunucu (Cloudflare, nginx) arkasındaysanız
     * anlamlıdır. Biz bunu sadece kaba kuvvet sayacı için kullanıyoruz;
     * yetkilendirme kararı asla IP'ye dayandırılmamalıdır.
     */
    public function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        // Veritabanı sütununu taşırmamak için kırpıyoruz.
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
