<?php
/**
 * =====================================================================
 *  ApiResponse – Tek biçimli JSON yanıtları
 * ---------------------------------------------------------------------
 *  BİR API'NİN EN DEĞERLİ ÖZELLİĞİ ÖNGÖRÜLEBİLİRLİKTİR.
 *  İstemci, yanıtın biçimini bir kez öğrenip her uç noktada aynı
 *  şekilde işleyebilmelidir. Bu yüzden TÜM yanıtlar aynı "zarfa"
 *  konur:
 *
 *  BAŞARILI
 *      {
 *        "data": { ... }  veya  [ ... ],
 *        "meta": { "page": 1, "per_page": 20, "total": 137, ... }
 *      }
 *
 *  HATALI
 *      {
 *        "error": {
 *          "code": "validation_failed",
 *          "message": "Gönderilen veriler geçersiz.",
 *          "details": { "email": "Geçerli bir e-posta girin." }
 *        }
 *      }
 *
 *  "code" MAKİNE İÇİN, "message" İNSAN İÇİNDİR.
 *  İstemci koşullarını "code" üzerine kurar; mesaj metni değişse bile
 *  entegrasyon bozulmaz. Yalnızca mesaj metnine bakan istemciler,
 *  siz bir yazım hatasını düzelttiğinizde bile kırılır.
 *
 *  ---------------------------------------------------------------
 *  HTTP DURUM KODU DA YANITIN PARÇASIDIR
 *  Her şeye 200 dönüp gövdeye "success: false" yazmak yaygın ama
 *  yanlıştır: vekil sunucular, önbellekler, izleme araçları ve
 *  istemci kütüphaneleri DURUM KODUNA bakar. Doğru kodu göndermek
 *  bedavaya gelen bir uyumluluktur.
 *
 *      200 OK          okuma başarılı
 *      201 Created     kayıt oluşturuldu (+ Location başlığı)
 *      204 No Content  silindi, gövde yok
 *      400 Bad Request istek bozuk (JSON hatası gibi)
 *      401 Unauthorized jeton yok / geçersiz
 *      403 Forbidden   jeton geçerli ama yetki (scope) yetersiz
 *      404 Not Found   kaynak yok
 *      405 Method Not Allowed
 *      409 Conflict    çakışma (aynı e-posta)
 *      422 Unprocessable Entity  doğrulama hatası
 *      429 Too Many Requests     hız sınırı
 *      500 Internal Server Error
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class ApiResponse
{
    /**
     * Yanıta eklenecek ek başlıklar (hız sınırı sayaçları gibi).
     *
     * @var array<string,string>
     */
    private static array $headers = [];

    /** Yanıt gönderilmeden önce eklenecek bir başlık kaydeder. */
    public static function header(string $name, string $value): void
    {
        self::$headers[$name] = $value;
    }

    /* =================================================================
     *  BAŞARILI YANITLAR
     * ============================================================== */

    /**
     * Tek bir kaynak veya serbest veri döndürür.
     *
     * @param mixed                $data
     * @param array<string,mixed>  $meta
     */
    public static function data(mixed $data, int $status = 200, array $meta = []): never
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        self::send($payload, $status);
    }

    /**
     * Sayfalanmış liste döndürür.
     *
     * SAYFALAMA BİLGİSİ GÖVDEDE OLMALIDIR. Yalnızca satırları
     * döndürürseniz istemci "başka sayfa var mı?" sorusunu
     * yanıtlayamaz ve ya eksik veri gösterir ya da sonsuz döngüye
     * girer.
     *
     * BAĞLANTILAR FİLTREYİ TAŞIMALIDIR
     * ÖLÇÜLEN SORUN: `?per=10&page=2` isteğinin yanıtındaki bağlantılar
     * `?page=3` idi — `per` ve `q` düşüyordu. "next" bağlantısını
     * izleyen bir istemci sayfa boyutunun sessizce 20'ye dönmesi
     * yüzünden 10 kaydı ATLIYORDU. Sayfalama bağlantısının tek işi
     * "aynı listenin sonraki sayfası"nı göstermektir; filtre
     * değişiyorsa o artık aynı liste değildir.
     *
     * ÇÖZÜM: Çağıran, korunacak sorgu parametrelerini $query ile verir.
     *
     * @param array<int,mixed>            $items
     * @param array<string,string|int>    $query  Bağlantılarda korunacak filtreler
     */
    public static function collection(array $items, Paginator $paginator, string $route = '', array $query = []): never
    {
        $meta = $paginator->toArray();

        /* Bağlantıları da veriyoruz (HATEOAS'ın hafif hâli): istemci
         * sayfa adresini kendi kurmak zorunda kalmaz, "next" boşsa
         * listenin sonuna geldiğini bilir. */
        $links = [];

        if ($route !== '') {
            // Boş değerler adrese yazılmasın: "?q=&status=" gürültüdür.
            $query = array_filter($query, static fn ($v): bool => $v !== null && $v !== '');

            $links = [
                'self' => $paginator->url($paginator->currentPage(), $route, $query),
                'next' => $paginator->onLastPage()  ? null : $paginator->url($paginator->currentPage() + 1, $route, $query),
                'prev' => $paginator->onFirstPage() ? null : $paginator->url($paginator->currentPage() - 1, $route, $query),
            ];
        }

        $payload = ['data' => $items, 'meta' => $meta];

        if ($links !== []) {
            $payload['links'] = $links;
        }

        self::send($payload, 200);
    }

    /**
     * Kayıt oluşturuldu.
     *
     * Location başlığı standarttır: istemciye "oluşturduğun kayıt
     * ŞU adreste" der ve tek istekle ulaşmasını sağlar.
     */
    public static function created(mixed $data, string $location = ''): never
    {
        if ($location !== '') {
            self::header('Location', $location);
        }

        self::send(['data' => $data], 201);
    }

    /**
     * Gövdesiz başarı (silme işlemi).
     *
     * 204, "başardım ama söyleyecek bir şeyim yok" demektir ve
     * GÖVDE İÇERMEMELİDİR. Gövde yazarsanız bazı istemciler
     * ayrıştırma hatası verir.
     */
    public static function noContent(): never
    {
        if (!headers_sent()) {
            self::flushHeaders();
            http_response_code(204);
        }

        exit;
    }

    /* =================================================================
     *  HATA YANITLARI
     * ============================================================== */

    /**
     * @param string              $code    Makine tarafından okunacak kod
     * @param string              $message İnsan tarafından okunacak açıklama
     * @param array<string,mixed> $details Alan bazlı ayrıntılar
     */
    public static function error(string $code, string $message, int $status = 400, array $details = []): never
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        self::send(['error' => $error], $status);
    }

    /**
     * Doğrulama hatası.
     *
     * 422 kullanıyoruz, 400 değil: istek BİÇİMSEL olarak doğrudur
     * (geçerli JSON), ama İÇERİĞİ kabul edilemez. Bu ayrım, istemcinin
     * "isteğimi mi yanlış kurdum, verimi mi?" sorusunu ayırmasını
     * sağlar.
     *
     * @param array<string,string> $errors
     */
    public static function validationFailed(array $errors): never
    {
        self::error('validation_failed', 'Gönderilen veriler geçersiz.', 422, $errors);
    }

    /* =================================================================
     *  GÖNDERİM
     * ============================================================== */

    /**
     * @param array<string,mixed> $payload
     */
    private static function send(array $payload, int $status): never
    {
        if (!headers_sent()) {
            http_response_code($status);

            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');

            /* API yanıtları önbelleğe alınmasın: istemci eski veriyi
             * "güncel" sanmasın. Gerçekten önbelleklenebilir bir uç
             * nokta varsa orada bilinçli olarak ETag/Cache-Control
             * verilmelidir. */
            header('Cache-Control: no-store');

            self::flushHeaders();
        }

        /* JSON_UNESCAPED_UNICODE: Türkçe karakterler "ç" değil
         * doğrudan "ç" olarak yazılır — hem okunur hem küçük.
         * JSON_UNESCAPED_SLASHES: adreslerdeki "\/" gürültüsü kalkar. */
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        exit;
    }

    private static function flushHeaders(): void
    {
        foreach (self::$headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }
}
