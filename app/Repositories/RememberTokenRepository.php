<?php
/**
 * =====================================================================
 *  RememberTokenRepository – "Beni hatırla" anahtarları
 * ---------------------------------------------------------------------
 *  "Beni hatırla", tarayıcı kapansa bile kullanıcıyı giriş yapmış
 *  tutar. Yanlış kurgulanırsa kalıcı bir arka kapıya dönüşür; bu
 *  yüzden tasarımı baştan anlatmak gerekiyor.
 *
 *  ÇEREZE NE YAZILMAZ?
 *    - Parola (asla!)
 *    - Kullanıcı ID'si tek başına  → "id=1" yazıp yönetici olunurdu
 *    - Tahmin edilebilir bir sayı  → denenerek bulunurdu
 *
 *  KULLANDIĞIMIZ YÖNTEM: "selector + validator" (iki parçalı anahtar)
 *
 *    Çerez içeriği:   <selector>:<validator>
 *    Veritabanında :   selector DÜZ,  validator'ın SHA-256 ÖZETİ
 *
 *  Neden iki parça?
 *
 *    1. SELECTOR, satırı bulmak içindir. İndekslenmiş olduğu için
 *       arama tek ve hızlı bir sorgudur.
 *
 *    2. VALIDATOR, kimliği kanıtlamak içindir ve veritabanında DÜZ
 *       DURMAZ. Veritabanı sızsa bile saldırgan özetlerden çereze
 *       geri dönemez; yani çalınan tablo ile kimsenin hesabına
 *       girilemez. (Parolayı neden hash'liyorsak, aynı gerekçe.)
 *
 *  Tek parça kullanıp "WHERE token = ..." deseydik, karşılaştırma
 *  ya düz metin saklamayı gerektirirdi ya da her satırı tek tek
 *  denemeyi. İki parça bu ikilemi çözer.
 *
 *  EK ÖNLEMLER
 *    - Anahtar TEK KULLANIMLIKTIR: her otomatik girişte yenilenir.
 *      Çalınan bir çerez ikinci kez işe yaramaz.
 *    - Anahtarın son kullanma tarihi vardır (varsayılan 30 gün).
 *    - Parola değiştiğinde kullanıcının TÜM anahtarları silinir;
 *      "parolamı değiştirdim, diğer cihazlar düşsün" beklentisi budur.
 *    - Çıkışta yalnızca o cihazın anahtarı silinir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RememberTokenRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Yeni bir anahtar üretir ve saklar.
     *
     * @param  int $userId
     * @param  int $lifetime Saniye cinsinden ömür
     * @return array{selector:string,validator:string} Çereze yazılacak ham değerler
     */
    public function create(int $userId, int $lifetime): array
    {
        /* random_bytes(): kriptografik olarak güvenli rastgelelik.
         * rand()/mt_rand() ÇIKTISI TAHMİN EDİLEBİLİR; oturum anahtarı
         * üretmek için asla kullanılmamalıdır. */
        $selector  = bin2hex(random_bytes(9));    // 18 karakter – yalnızca arama anahtarı
        $validator = bin2hex(random_bytes(32));   // 64 karakter – asıl sır

        $stmt = $this->db->prepare(
            'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at)
             VALUES (:user_id, :selector, :hash, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
        );

        $stmt->execute([
            ':user_id'  => $userId,
            ':selector' => $selector,
            ':hash'     => $this->hash($validator),
            ':ttl'      => $lifetime,
        ]);

        return ['selector' => $selector, 'validator' => $validator];
    }

    /**
     * Çerezden gelen anahtarı doğrular.
     *
     * @return int|null Geçerliyse kullanıcı ID'si, değilse null
     */
    public function verify(string $selector, string $validator): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, validator_hash
               FROM remember_tokens
              WHERE selector = :selector
                AND expires_at > NOW()
              LIMIT 1'
        );
        $stmt->execute([':selector' => $selector]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        /* hash_equals(): sabit süreli karşılaştırma.
         * "===" ilk farklı karakterde durduğu için, saldırgan yanıt
         * süresini ölçerek anahtarı karakter karakter tahmin
         * edebilirdi (timing attack). */
        if (!hash_equals((string) $row['validator_hash'], $this->hash($validator))) {
            /* Selector doğru ama validator yanlış: bu normal bir hata
             * DEĞİLDİR. Ya çerez kurcalanıyor ya da çalınmış bir
             * selector deneniyor. Anahtarı iptal ediyoruz. */
            $this->deleteBySelector($selector);

            return null;
        }

        return (int) $row['user_id'];
    }

    /** Tek bir anahtarı siler (çıkış yaparken o cihaz için). */
    public function deleteBySelector(string $selector): void
    {
        $stmt = $this->db->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $stmt->execute([':selector' => $selector]);
    }

    /**
     * Kullanıcının TÜM anahtarlarını siler.
     * Parola değişikliğinde çağrılır: "diğer tüm cihazlardan çık".
     */
    public function deleteAllFor(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM remember_tokens WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
    }

    /** Süresi dolmuş anahtarları temizler (tablo şişmesin). */
    public function prune(): void
    {
        $this->db->exec('DELETE FROM remember_tokens WHERE expires_at < NOW()');
    }

    /**
     * Validator'ın veritabanında saklanacak özeti.
     *
     * NEDEN password_hash() DEĞİL DE sha256?
     * password_hash() bilerek YAVAŞTIR (parola denemelerini
     * zorlaştırmak için). Buradaki değer 64 karakterlik rastgele bir
     * dizedir; kaba kuvvetle bulunması zaten imkânsızdır, dolayısıyla
     * yavaşlatmaya gerek yoktur. Her sayfa yüklemesinde çalışacak bir
     * kontrolü gereksiz yere yavaşlatmak istemeyiz.
     */
    private function hash(string $validator): string
    {
        return hash('sha256', $validator);
    }
}
