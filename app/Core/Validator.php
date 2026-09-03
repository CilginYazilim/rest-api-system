<?php
/**
 * =====================================================================
 *  Validator – Form doğrulama
 * ---------------------------------------------------------------------
 *  ALTIN KURAL: JavaScript tarafındaki doğrulama sadece KULLANICI
 *  DENEYİMİ içindir. Kötü niyetli biri tarayıcıyı hiç kullanmadan
 *  doğrudan sunucuya istek atabilir. Her kontrol SUNUCUDA TEKRARLANIR.
 *
 *  KULLANIMI (zincirleme / fluent arayüz):
 *
 *      $v = new Validator($_POST);
 *      $v->name('name', 'Ad')
 *        ->email('email')
 *        ->password('password', required: false);
 *
 *      if ($v->fails()) {
 *          return $v->errors();
 *      }
 *      $temiz = $v->validated();
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string,string> alan => hata mesajı */
    private array $errors = [];

    /** @var array<string,mixed> Doğrulamadan geçmiş temiz değerler */
    private array $clean = [];

    /** @param array<string,mixed> $data Ham girdi (genelde $_POST) */
    public function __construct(private array $data)
    {
    }

    /* =================================================================
     *  KURALLAR
     * ============================================================== */

    /**
     * Ad / soyad alanı: harf, boşluk, nokta, kesme işareti ve tire.
     * Rakam ve < > ; gibi karakterler kabul edilmez.
     */
    public function name(string $field, string $label): self
    {
        $value = $this->normalizeSpaces($this->get($field));
        $min   = (int) Config::get('validation.name_min', 2);
        $max   = (int) Config::get('validation.name_max', 150);

        if ($value === '') {
            return $this->fail($field, $label . ' alanı boş bırakılamaz.');
        }

        /* mb_strlen(): Çok baytlı karakterleri doğru sayar.
         *   strlen("Çılgın")    = 8  (yanlış)
         *   mb_strlen("Çılgın") = 6  (doğru) */
        $length = mb_strlen($value, 'UTF-8');

        if ($length < $min) {
            return $this->fail($field, $label . ' en az ' . $min . ' karakter olmalıdır.');
        }

        if ($length > $max) {
            return $this->fail($field, $label . ' en fazla ' . $max . ' karakter olabilir.');
        }

        /* Desen açıklaması:
         *   \p{L} → herhangi bir dildeki HARF (ç, ğ, ş, é, 漢 ...)
         *   \p{M} → harflere eklenen işaretler (aksan vb.)
         *   \s    → boşluk
         *   . ' - → "Ayşe-Nur", "D'Angelo" gibi adlar için
         * Sondaki /u : desenin UTF-8 metinle çalışmasını sağlar. */
        if (!preg_match("/^[\p{L}\p{M}\s.'-]+$/u", $value)) {
            return $this->fail(
                $field,
                $label . ' yalnızca harf, boşluk, nokta, kesme işareti ve tire içerebilir.'
            );
        }

        $this->clean[$field] = $value;

        return $this;
    }

    /** E-posta adresi doğrular ve küçük harfe çevirir. */
    public function email(string $field = 'email', string $label = 'E-posta'): self
    {
        $value = mb_strtolower(trim($this->get($field)), 'UTF-8');

        if ($value === '') {
            return $this->fail($field, $label . ' alanı boş bırakılamaz.');
        }

        if (mb_strlen($value) > 190) {
            return $this->fail($field, $label . ' en fazla 190 karakter olabilir.');
        }

        /* filter_var, elle yazılmış regex'lerden çok daha güvenilirdir.
         * E-posta biçimi RFC'de sanılandan çok daha karmaşıktır. */
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $this->fail($field, 'Geçerli bir e-posta adresi giriniz.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    /**
     * Parola kuralları. $required=false ise boş bırakılabilir
     * (düzenleme formunda "değiştirmek istemiyorum" anlamına gelir).
     */
    public function password(string $field = 'password', bool $required = true, ?string $confirmField = null): self
    {
        // trim YAPMIYORUZ: baştaki/sondaki boşluk parolanın parçası olabilir.
        $value = (string) ($this->data[$field] ?? '');
        $min   = (int) Config::get('security.password_min', 8);

        if ($value === '') {
            if ($required) {
                return $this->fail($field, 'Parola alanı boş bırakılamaz.');
            }

            return $this; // İsteğe bağlı ve boş → doğrulanacak bir şey yok
        }

        if (mb_strlen($value) < $min) {
            return $this->fail($field, 'Parola en az ' . $min . ' karakter olmalıdır.');
        }

        // password_hash() 72 bayttan sonrasını yok sayar; kullanıcıyı uyaralım.
        if (strlen($value) > 72) {
            return $this->fail($field, 'Parola en fazla 72 karakter olabilir.');
        }

        // En az bir harf ve bir rakam: sözlük saldırılarını zorlaştırır.
        if (!preg_match('/[\p{L}]/u', $value) || !preg_match('/\d/', $value)) {
            return $this->fail($field, 'Parola en az bir harf ve bir rakam içermelidir.');
        }

        if ($confirmField !== null && (string) ($this->data[$confirmField] ?? '') !== $value) {
            return $this->fail($confirmField, 'Parolalar birbiriyle eşleşmiyor.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    /**
     * Değerin izin verilen listede olduğunu doğrular (rol, durum vb.).
     *
     * BEYAZ LİSTE, güvenliğin temel taşıdır: "neyi yasaklayacağımı"
     * değil "neye izin vereceğimi" tanımlarım.
     *
     * @param array<int,string> $allowed
     */
    public function in(string $field, array $allowed, string $label): self
    {
        $value = $this->get($field);

        if (!in_array($value, $allowed, true)) {
            return $this->fail($field, $label . ' için geçersiz bir değer seçildi.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    /** İsteğe bağlı serbest metin (biyografi, not vb.). */
    public function text(string $field, string $label, int $max = 500, bool $required = false): self
    {
        $value = trim($this->get($field));

        if ($value === '') {
            if ($required) {
                return $this->fail($field, $label . ' alanı boş bırakılamaz.');
            }

            $this->clean[$field] = '';

            return $this;
        }

        if (mb_strlen($value, 'UTF-8') > $max) {
            return $this->fail($field, $label . ' en fazla ' . $max . ' karakter olabilir.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    /** Telefon: rakam, boşluk, +, -, parantez. İsteğe bağlıdır. */
    public function phone(string $field = 'phone', string $label = 'Telefon'): self
    {
        $value = trim($this->get($field));

        if ($value === '') {
            $this->clean[$field] = '';

            return $this;
        }

        if (!preg_match('/^[0-9+()\s-]{7,25}$/', $value)) {
            return $this->fail($field, $label . ' geçerli bir biçimde değil.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    /* =================================================================
     *  SONUÇLAR
     * ============================================================== */

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,mixed> Yalnızca kurallardan geçmiş değerler */
    public function validated(): array
    {
        return $this->clean;
    }

    /** Dışarıdan (örn. "bu e-posta zaten kayıtlı") hata eklemek için. */
    public function addError(string $field, string $message): self
    {
        return $this->fail($field, $message);
    }

    /* =================================================================
     *  İÇ YARDIMCILAR
     * ============================================================== */

    private function get(string $field): string
    {
        $value = $this->data[$field] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /** "Ali    Veli" → "Ali Veli" (aradaki çoklu boşlukları teke indirir) */
    private function normalizeSpaces(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function fail(string $field, string $message): self
    {
        // İlk hata kazanır; kullanıcıya aynı alan için tek mesaj gösterilir.
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }

        return $this;
    }
}
