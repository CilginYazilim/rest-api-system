<?php
/**
 * =====================================================================
 *  ApiTokenRepository – API jetonları
 * ---------------------------------------------------------------------
 *  JETON NEDEN VERİTABANINDA DÜZ SAKLANMAZ?
 *  Jeton, parolanın API'deki karşılığıdır: onu bilen, hesabın
 *  yapabildiği her şeyi yapabilir. Veritabanı bir gün sızarsa düz
 *  saklanan jetonlar anında kullanılabilir hâle gelir. Bu yüzden
 *  yalnızca SHA-256 ÖZETİNİ saklıyoruz.
 *
 *  NEDEN bcrypt DEĞİL DE SHA-256?
 *  Parolalarda bcrypt kullanırız çünkü insanlar kısa ve tahmin
 *  edilebilir parolalar seçer; yavaş algoritma kaba kuvveti
 *  pahalılaştırır. Jeton ise 32 BAYT RASTGELE veridir — tahmin
 *  edilemez, sözlük saldırısı anlamsızdır. Ayrıca jeton her istekte
 *  doğrulanır; bcrypt kullanmak her API çağrısına ~100 ms eklerdi.
 *
 *  Tuz (salt) da kullanmıyoruz: tuz olsaydı özetten ARAMA yapamaz,
 *  tüm jetonları tek tek denemek zorunda kalırdık. Rastgele veri için
 *  tuzun sağladığı ek koruma zaten yoktur.
 *
 *  JETON YALNIZCA BİR KEZ GÖSTERİLİR. Kaybeden kullanıcı yenisini
 *  üretir; "bana jetonumu tekrar göster" diyebilen bir sistem,
 *  saklamanın anlamını ortadan kaldırır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ApiToken;
use PDO;

final class ApiTokenRepository
{
    /** Jetonun ham uzunluğu (bayt). 32 bayt = 256 bit entropi. */
    private const TOKEN_BYTES = 32;

    /** Jetonun okunabilir ön eki; günlüklerde tanınmasını kolaylaştırır. */
    private const PREFIX = 'cy_';

    public function __construct(private readonly PDO $db)
    {
    }

    /* =================================================================
     *  ÜRETME
     * ============================================================== */

    /**
     * Yeni jeton üretir ve DÜZ hâlini bir kez döndürür.
     *
     * @param array<int,string> $scopes 'read' ve/veya 'write'
     *
     * @return array{token:string,id:int}
     */
    public function create(int $userId, string $name, array $scopes): array
    {
        /* random_bytes KRİPTOGRAFİK olarak güvenlidir.
         * rand() / mt_rand() ASLA kullanılmaz: çıktıları tahmin
         * edilebilir ve birkaç örnekle iç durumları çözülebilir. */
        $plain = self::PREFIX . rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');

        $stmt = $this->db->prepare(
            'INSERT INTO api_tokens (user_id, name, token_hash, scopes, created_at)
             VALUES (:user_id, :name, :hash, :scopes, NOW())'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':name'    => mb_substr($name, 0, 100),
            ':hash'    => self::hash($plain),
            ':scopes'  => implode(',', self::normalizeScopes($scopes)),
        ]);

        return ['token' => $plain, 'id' => (int) $this->db->lastInsertId()];
    }

    /* =================================================================
     *  DOĞRULAMA
     * ============================================================== */

    /**
     * Düz jetondan kaydı bulur. Bulunamazsa veya iptal edilmişse null.
     */
    public function findByPlainToken(string $plain): ?ApiToken
    {
        if ($plain === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT t.*, u.is_active AS user_active
               FROM api_tokens t
               INNER JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = :hash
              LIMIT 1'
        );
        $stmt->execute([':hash' => self::hash($plain)]);

        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        /* HESAP PASİFSE JETON DA GEÇERSİZDİR.
         * Bunu her istekte veritabanından TAZE okuyoruz. Jetona
         * "sahibi aktif" bilgisini gömseydik, bir hesabı kapattığınızda
         * elindeki jetonla çalışmaya devam ederdi. */
        if ((int) $row['user_active'] !== 1 || $row['revoked_at'] !== null) {
            return null;
        }

        return ApiToken::fromRow($row);
    }

    /** Son kullanım zamanını damgalar (kullanılmayan jetonları görmek için). */
    public function touch(int $id, string $ip): void
    {
        $stmt = $this->db->prepare(
            'UPDATE api_tokens SET last_used_at = NOW(), last_used_ip = :ip WHERE id = :id'
        );
        $stmt->execute([':ip' => $ip, ':id' => $id]);
    }

    /* =================================================================
     *  LİSTELEME (sayfalanmış)
     * ============================================================== */

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM api_tokens WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * LIMIT/OFFSET tamsayı olarak gömülür; bkz. UserRepository::page().
     *
     * @return array<int,ApiToken>
     */
    public function pageForUser(int $userId, int $offset, int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM api_tokens
              WHERE user_id = :id
              ORDER BY id DESC
              LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $stmt->execute([':id' => $userId]);

        return array_map(
            static fn (array $row): ApiToken => ApiToken::fromRow($row),
            $stmt->fetchAll()
        );
    }

    /* =================================================================
     *  İPTAL
     * ============================================================== */

    /**
     * Jetonu iptal eder.
     *
     * SİLMİYORUZ, İŞARETLİYORUZ: "bu jeton ne zaman iptal edildi"
     * bilgisi bir güvenlik olayında paha biçilmezdir. Ayrıca
     * api_requests kayıtlarının bağlandığı satır ayakta kalır.
     *
     * GÜVENLİK: user_id koşulu şarttır; aksi halde ID'yi bilen biri
     * başkasının jetonunu iptal edebilirdi (IDOR).
     */
    public function revoke(int $userId, int $tokenId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE api_tokens SET revoked_at = NOW()
              WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL'
        );
        $stmt->execute([':id' => $tokenId, ':user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /* =================================================================
     *  YARDIMCILAR
     * ============================================================== */

    private static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Yetki listesini BEYAZ LİSTEDEN geçirir.
     *
     * Uydurma bir yetki adı ("admin", "*") kaydedilirse, ileride o adı
     * kontrol eden bir kod yazıldığında beklenmedik erişim doğar.
     * Kapıda elemek en ucuzudur.
     *
     * @param  array<int,string> $scopes
     * @return array<int,string>
     */
    public static function normalizeScopes(array $scopes): array
    {
        $allowed = array_values(array_intersect(ApiToken::SCOPES, $scopes));

        // En az bir yetki olmalı; hiçbiri yoksa salt okunur veriyoruz.
        return $allowed === [] ? [ApiToken::SCOPE_READ] : $allowed;
    }
}
