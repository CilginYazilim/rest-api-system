<?php
/**
 * =====================================================================
 *  ApiRateLimiter – Jeton başına istek hızı sınırı
 * ---------------------------------------------------------------------
 *  NEDEN GEREKLİ?
 *  Bir API açtığınız anda onu döngü içinde çağıran istemciler de
 *  edinirsiniz. Hız sınırı olmayan bir uç nokta, tek bir hatalı
 *  istemcinin veritabanınızı kilitlemesine yeter. Sınır koymak
 *  "kullanıcıyı cezalandırmak" değil, HERKESİN hizmet alabilmesini
 *  sağlamaktır.
 *
 *  KAYAN PENCERE (sliding window)
 *  ---------------------------------------------------------------
 *  "Dakikada 60 istek" kuralını iki türlü uygulayabilirsiniz:
 *
 *   a) SABİT PENCERE: her dakikanın başında sayaç sıfırlanır.
 *      Kusuru: 12:00:59'da 60, 12:01:00'da 60 istek → iki saniyede
 *      120 istek geçer. Sınır kâğıt üzerinde vardır, pratikte yoktur.
 *
 *   b) KAYAN PENCERE: "SON 60 saniyedeki istekler" sayılır.  ← bunu
 *      Her an geriye doğru bakıldığı için yukarıdaki boşluk oluşmaz.
 *
 *  Sayaç VERİTABANINDA tutulur; oturumda tutulsaydı istemci çerezini
 *  atarak sınırı sıfırlardı.
 *
 *  ÜRETİM NOTU: Çok yüksek trafikte her istek için INSERT yapmak
 *  pahalıdır; orada Redis gibi bellek içi bir sayaç tercih edilir.
 *  Mantık aynıdır, saklama yeri değişir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use PDO;

final class ApiRateLimiter
{
    public function __construct(
        private readonly PDO $db,

        /** Pencere içinde izin verilen istek sayısı. */
        private readonly int $limit = 60,

        /** Pencere uzunluğu (saniye). */
        private readonly int $window = 60,
    ) {
    }

    /**
     * İsteği kaydeder ve sınır aşıldıysa 429 ile yanıtı sonlandırır.
     *
     * Standart başlıkları HER yanıta ekliyoruz (yalnızca sınır
     * aşıldığında değil). İyi bir istemci bu sayaçlara bakarak kendini
     * yavaşlatır; sınıra hiç dayanmaz.
     *
     *      X-RateLimit-Limit     : pencere başına izin
     *      X-RateLimit-Remaining : kalan hak
     *      X-RateLimit-Reset     : sayaç ne zaman boşalır (unix zaman)
     *      Retry-After           : kaç saniye sonra tekrar deneyin (429'da)
     */
    public function check(int $tokenId, string $ip): void
    {
        $this->prune();

        $used = $this->countRecent($tokenId);

        $remaining = max(0, $this->limit - $used);
        $reset     = time() + $this->window;

        ApiResponse::header('X-RateLimit-Limit', (string) $this->limit);
        ApiResponse::header('X-RateLimit-Remaining', (string) max(0, $remaining - 1));
        ApiResponse::header('X-RateLimit-Reset', (string) $reset);

        if ($used >= $this->limit) {
            ApiResponse::header('Retry-After', (string) $this->window);
            ApiResponse::header('X-RateLimit-Remaining', '0');

            ApiResponse::error(
                'rate_limit_exceeded',
                sprintf('İstek sınırı aşıldı: %d saniyede en fazla %d istek.', $this->window, $this->limit),
                429
            );
        }

        $this->record($tokenId, $ip);
    }

    /** Son pencere içindeki istek sayısı. */
    private function countRecent(int $tokenId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM api_requests
              WHERE token_id = :id
                AND requested_at >= (NOW() - INTERVAL :window SECOND)'
        );
        $stmt->execute([':id' => $tokenId, ':window' => $this->window]);

        return (int) $stmt->fetchColumn();
    }

    private function record(int $tokenId, string $ip): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO api_requests (token_id, ip, requested_at) VALUES (:id, :ip, NOW())'
        );
        $stmt->execute([':id' => $tokenId, ':ip' => $ip]);
    }

    /**
     * Eski kayıtları temizler.
     *
     * Her istekte DELETE çalıştırmak gereksiz yüktür; ~%5 olasılıkla
     * yapıyoruz. Tablo yine de sınırlı kalır, maliyet ise 20'de bire
     * düşer. (Gerçek projede bunu bir zamanlanmış göreve almak daha
     * temizdir.)
     */
    private function prune(): void
    {
        if (random_int(1, 20) !== 1) {
            return;
        }

        $this->db->exec('DELETE FROM api_requests WHERE requested_at < (NOW() - INTERVAL 1 HOUR)');
    }
}
