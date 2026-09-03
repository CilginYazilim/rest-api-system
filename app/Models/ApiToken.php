<?php
/**
 * =====================================================================
 *  ApiToken – API jetonu varlığı
 * ---------------------------------------------------------------------
 *  DİKKAT: Bu nesne jetonun KENDİSİNİ TAŞIMAZ; yalnızca özeti
 *  veritabanındadır ve o da buraya alınmaz. Düz jeton, üretildiği
 *  anda BİR KEZ gösterilir ve bir daha erişilemez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Models;

final class ApiToken
{
    /**
     * YETKİ KAPSAMLARI (scopes)
     * ---------------------------------------------------------------
     *  Jeton "her şeyi yapabilir" olmamalıdır. Yalnızca veri okuyan
     *  bir rapor betiği, silme yetkisi olmayan bir jetonla çalışmalıdır;
     *  jeton sızarsa hasarın sınırı da böylece çizilmiş olur.
     *
     *  Bu ilkenin adı EN AZ YETKİ (least privilege) ilkesidir.
     */
    public const SCOPE_READ  = 'read';
    public const SCOPE_WRITE = 'write';

    /** @var array<int,string> */
    public const SCOPES = [self::SCOPE_READ, self::SCOPE_WRITE];

    /** @param array<int,string> $scopes */
    public function __construct(
        public readonly int     $id,
        public readonly int     $userId,
        public readonly string  $name,
        public readonly array   $scopes,
        public readonly ?string $lastUsedAt = null,
        public readonly string  $lastUsedIp = '',

        /* ÖMÜR BOYU istek sayısı. api_requests tablosundan DEĞİL, bu
         * alandan okunur — o tablo yalnızca hız sınırının kayan
         * penceresidir ve 1 saat sonra budanır (bkz. ApiRateLimiter). */
        public readonly int     $requestCount = 0,

        public readonly ?string $revokedAt = null,
        public readonly ?string $createdAt = null,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:         (int) ($row['id'] ?? 0),
            userId:     (int) ($row['user_id'] ?? 0),
            name:       (string) ($row['name'] ?? ''),

            /* Boş metinde explode(',', '') tek elemanlı [''] döndürür;
             * array_filter bu hayalet yetkiyi temizler. */
            scopes:     array_values(array_filter(explode(',', (string) ($row['scopes'] ?? '')))),

            lastUsedAt:   isset($row['last_used_at']) ? (string) $row['last_used_at'] : null,
            lastUsedIp:   (string) ($row['last_used_ip'] ?? ''),
            requestCount: (int) ($row['request_count'] ?? 0),
            revokedAt:    isset($row['revoked_at']) ? (string) $row['revoked_at'] : null,
            createdAt:  isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }

    /** Bu jeton belirtilen işi yapabilir mi? */
    public function can(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    /**
     * Arayüz için güvenli dizi.
     * DİKKAT: token_hash BİLEREK yoktur.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'scopes'       => $this->scopes,
            'last_used_at'  => $this->lastUsedAt,
            'request_count' => $this->requestCount,
            'revoked'       => $this->isRevoked(),
            'created_at'   => $this->createdAt,
        ];
    }
}
