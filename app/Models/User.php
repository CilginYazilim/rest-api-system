<?php
/**
 * =====================================================================
 *  User – Kullanıcı varlığı (Entity)
 * ---------------------------------------------------------------------
 *  Veritabanından gelen satır ($row['name'] gibi bir dizi) yerine
 *  gerçek bir NESNE kullanıyoruz. Kazancı:
 *
 *    - Yazım hatası anında yakalanır:
 *          $row['surnmae']  → sessizce null (fark etmezsiniz)
 *          $user->surnmae   → PHP uyarı verir
 *    - Davranış veriyle birlikte durur:
 *          $user->fullName(), $user->initials()
 *    - Parola gibi hassas alanlar dışarı sızmaz (toArray() gizler)
 *
 *  Bu sınıf veritabanını BİLMEZ. Sorgular UserRepository'dedir.
 *  (Katman ayrımı: varlık ≠ veri erişimi)
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class User
{
    /**
     * Geçerli tema değerleri.
     *
     * Varsayılan bilinçli olarak AÇIK temadır: işletim sistemi koyu
     * modda olsa bile panel açık açılır, kullanıcı isterse değiştirir.
     * Tercihi tahmin etmek yerine kullanıcıya sormak daha öngörülebilir.
     */
    public const THEME_LIGHT = 'light';
    public const THEME_DARK  = 'dark';

    /** Dışarıdan gelen tema değerini beyaz listeden geçirir. */
    public static function normalizeTheme(mixed $value): string
    {
        return $value === self::THEME_DARK ? self::THEME_DARK : self::THEME_LIGHT;
    }

    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $surname,
        public readonly string $email,
        public readonly bool   $isActive = true,
        /** Arayüz teması: "light" veya "dark". Hesaba bağlıdır. */
        public readonly string $theme = self::THEME_LIGHT,
        public readonly ?string $lastLoginAt = null,
        public readonly ?string $createdAt = null,
        /** Parola özeti. private: dışarıdan okunamaz, sızdırılamaz. */
        private readonly string $passwordHash = '',
    ) {
    }

    /**
     * Veritabanı satırını User nesnesine çevirir.
     *
     * Tek bir dönüşüm noktası olması önemlidir: sütun adı değişirse
     * sadece burayı düzeltirsiniz.
     *
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int) ($row['id'] ?? 0),
            name:         (string) ($row['name'] ?? ''),
            surname:      (string) ($row['surname'] ?? ''),
            email:        (string) ($row['email'] ?? ''),
            isActive:     (bool) ($row['is_active'] ?? true),
            theme:        self::normalizeTheme($row['theme'] ?? self::THEME_LIGHT),
            lastLoginAt:  isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
            createdAt:    isset($row['created_at']) ? (string) $row['created_at'] : null,
            passwordHash: (string) ($row['password'] ?? ''),
        );
    }

    /* =================================================================
     *  GÖRÜNTÜLEME YARDIMCILARI
     * ============================================================== */

    public function fullName(): string
    {
        return trim($this->name . ' ' . $this->surname);
    }

    /**
     * Görseli olmayan kullanıcılar için baş harf rozeti metni.
     * mb_* fonksiyonları şart: "İ" gibi çok baytlı harfler substr ile
     * ortadan kesilip bozuk karakter üretir.
     */
    public function initials(): string
    {
        $first = mb_substr($this->name, 0, 1, 'UTF-8');
        $last  = mb_substr($this->surname, 0, 1, 'UTF-8');

        return mb_strtoupper($first . $last, 'UTF-8');
    }

    /**
     * Parolayı doğrular.
     *
     * password_verify() sabit süreli karşılaştırma yapar; "===" ile
     * özet karşılaştırmak zamanlama saldırısına açıktır.
     */
    public function verifyPassword(string $plain): bool
    {
        return $this->passwordHash !== '' && password_verify($plain, $this->passwordHash);
    }

    /**
     * Parola özeti güncellenmeli mi?
     *
     * PHP'nin varsayılan algoritması sürümlerle güçlenir. Kullanıcı her
     * giriş yaptığında bunu kontrol edip özeti sessizce yenileriz.
     */
    public function needsRehash(): bool
    {
        return $this->passwordHash !== '' && password_needs_rehash($this->passwordHash, PASSWORD_DEFAULT);
    }

    /* =================================================================
     *  DIŞA AKTARIM
     * ============================================================== */

    /**
     * JSON'a çevrilebilir güvenli dizi.
     * DİKKAT: passwordHash BİLEREK yoktur; asla dışarı çıkmamalıdır.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'surname'       => $this->surname,
            'full_name'     => $this->fullName(),
            'email'         => $this->email,
            'is_active'     => $this->isActive,
            'theme'         => $this->theme,
            'last_login_at' => self::formatDate($this->lastLoginAt),
            'created_at'    => self::formatDate($this->createdAt),
        ];
    }

    /**
     * REST uç noktaları için dizi.
     *
     * NEDEN toArray()'DEN AYRI?
     * ÖLÇÜLEN SORUN: Panel ile API aynı toArray()'i paylaşıyordu ve
     * API şunu döndürüyordu:
     *
     *     "created_at":    "13.02.2025 09:17"      ← ekran biçimi
     *     "last_login_at": "—"                     ← em dash
     *
     * İkisi de bir İNSAN için doğru, bir İSTEMCİ için yanlıştır:
     *   · "13.02.2025 09:17" hangi zaman diliminde olduğunu söylemez;
     *     ayrıştırmak için istemcinin Türkçe biçimi bilmesi gerekir.
     *   · "—" bir tarih değildir. "Değer yok" demenin JSON'daki
     *     karşılığı null'dır; istemci `if (x === null)` yazabilmelidir.
     *
     * Üstelik bu deponun kendi README'si ISO 8601 vaat ediyordu.
     *
     * ÇÖZÜM: Panelin biçimi toArray()'de kaldı (orada DOĞRU),
     * API kendi dizisini kullanıyor. Ekran biçimini değiştirmek
     * artık API sözleşmesini bozmuyor — iki ihtiyaç ayrıldı.
     *
     * @return array<string,mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'surname'       => $this->surname,
            'full_name'     => $this->fullName(),
            'email'         => $this->email,
            'is_active'     => $this->isActive,
            'theme'         => $this->theme,
            'last_login_at' => self::isoDate($this->lastLoginAt),
            'created_at'    => self::isoDate($this->createdAt),
        ];
    }

    /**
     * Veritabanı tarihini ISO 8601'e çevirir (RFC 3339):
     *   "2025-01-06 19:34:27"  →  "2025-01-06T19:34:27+03:00"
     *
     * Ofsetin yazılması ŞART: ofsetsiz bir damga, istemcinin kendi
     * dilimini varsaymasına yol açar ve yaz saati geçişlerinde
     * sessizce bir saat kayar. Değer yoksa null döner — "—" değil.
     */
    public static function isoDate(?string $value): ?string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Veritabanı tarihini okunabilir hale getirir:
     *   "2025-01-06 19:34:27"  →  "06.01.2025 19:34"
     */
    public static function formatDate(?string $value): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return '—';
        }

        try {
            return (new DateTimeImmutable($value))->format('d.m.Y H:i');
        } catch (\Exception) {
            // Tarih bozuksa uygulamayı çökertmek yerine ham değeri göster.
            return $value;
        }
    }
}
