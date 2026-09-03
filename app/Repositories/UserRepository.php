<?php
/**
 * =====================================================================
 *  UserRepository – users tablosuna erişimin TEK kapısı
 * ---------------------------------------------------------------------
 *  SQL yalnızca burada yazılır. Denetleyiciler ve görünümler tabloyu,
 *  sütun adlarını, JOIN'leri BİLMEZ. Kazancı:
 *
 *    - Sütun adı değiştiğinde tek dosya düzeltilir
 *    - Her sorgu hazırlanmış ifadeyle (prepared statement) çalışır;
 *      SQL enjeksiyonu tek bir yerde, sistematik olarak kapanır
 *    - Sorguları görmek için tüm projeyi taramak gerekmez
 *
 *  SAYFALAMA BURADA İKİ SORGUYLA YAPILIR:
 *      1) countAll()  → filtreye uyan TOPLAM kayıt (kaç sayfa var?)
 *      2) page()      → yalnızca o sayfanın satırları (LIMIT/OFFSET)
 *
 *  İki sorgu tek sorgudan iyidir: SQL_CALC_FOUND_ROWS artık önerilmez
 *  (MySQL 8'de kullanımdan kaldırıldı) ve indeks kullanan ayrı bir
 *  COUNT sorgusu pratikte daha hızlıdır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /* =================================================================
     *  TEKİL OKUMA
     * ============================================================== */

    public function find(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    /**
     * E-posta ile kullanıcı arar (giriş için).
     *
     * E-postayı küçük harfe çevirip arıyoruz: "Admin@..." ile
     * "admin@..." aynı hesaptır. Kullanıcıya "büyük harf yazmışsınız"
     * demek yerine sorunu sessizce çözmek doğrusudur.
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => mb_strtolower(trim($email), 'UTF-8')]);

        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    /* =================================================================
     *  SAYFALAMA
     * ============================================================== */

    /**
     * Filtreye uyan TOPLAM kayıt sayısı.
     *
     * Paginator kaç sayfa olduğunu bu sayıdan hesaplar. Filtre
     * koşulunu page() ile AYNI tutmak zorunludur; farklı olurlarsa
     * "3 sayfa var" yazıp 2. sayfada boş tablo gösterirsiniz.
     */
    public function countAll(string $search = '', ?bool $activeOnly = null): int
    {
        [$where, $params] = $this->filter($search, $activeOnly);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Tek bir sayfanın satırları.
     *
     * LIMIT/OFFSET NEDEN PARAMETRE DEĞİL?
     * PDO, emülasyon kapalıyken (ATTR_EMULATE_PREPARES = false) bu
     * konumdaki değerleri metin olarak gönderir ve MySQL
     * "LIMIT '20'" ifadesini sözdizimi hatası sayar. bindValue ile
     * PDO::PARAM_INT vermek de sürücüye göre değişken davranır.
     *
     * Değerler Paginator'dan TAMSAYI olarak gelir ve burada (int) ile
     * bir kez daha zorlanır; dışarıdan gelen metin sorguya asla
     * ulaşmaz, dolayısıyla enjeksiyon riski yoktur.
     *
     * @return array<int,User>
     */
    public function page(int $offset, int $limit, string $search = '', ?bool $activeOnly = null): array
    {
        [$where, $params] = $this->filter($search, $activeOnly);

        $sql = 'SELECT * FROM users '
             . $where
             . ' ORDER BY id DESC'
             . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn (array $row): User => User::fromRow($row),
            $stmt->fetchAll()
        );
    }

    /**
     * WHERE parçasını ve parametrelerini üretir.
     *
     * Tek yerde toplanması ŞART: countAll() ile page() farklı filtre
     * kurarsa sayfalama sessizce yanlış çalışır — bulunması en zor
     * hatalardandır.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filter(string $search, ?bool $activeOnly): array
    {
        $conditions = [];
        $params     = [];

        $search = trim($search);

        if ($search !== '') {
            /* LIKE deseni parametre olarak gönderilir; % işaretleri
             * DEĞERİN içindedir, SQL metninin değil. Böylece kullanıcı
             * ne yazarsa yazsın sorgunun yapısını değiştiremez.
             *
             * NEDEN ÜÇ AYRI PARAMETRE (:q1, :q2, :q3)?
             * Aynı adlı bir yer tutucuyu sorguda birden fazla kez
             * kullanmak YALNIZCA emülasyon açıkken çalışır. Bu proje
             * PDO::ATTR_EMULATE_PREPARES = false ile çalışır (gerçek
             * hazırlanmış ifade, daha güvenli) ve o modda MySQL sürücüsü
             * her yer tutucu için AYRI değer bekler:
             *     SQLSTATE[HY093]: Invalid parameter number
             * Üç ayrı ad vermek, bu tuzağın en basit çözümüdür. */
            $conditions[] = '(name LIKE :q1 OR surname LIKE :q2 OR email LIKE :q3)';

            $pattern = '%' . $search . '%';

            $params[':q1'] = $pattern;
            $params[':q2'] = $pattern;
            $params[':q3'] = $pattern;
        }

        if ($activeOnly !== null) {
            $conditions[] = 'is_active = :active';
            $params[':active'] = $activeOnly ? 1 : 0;
        }

        return [
            $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions),
            $params,
        ];
    }

    /* =================================================================
     *  YAZMA
     * ============================================================== */

    /** Başarılı girişten sonra son giriş zamanını damgalar. */
    public function touchLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Parola özetini yeniler (algoritma güncellendiğinde). */
    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password = :hash WHERE id = :id');
        $stmt->execute([':hash' => $hash, ':id' => $id]);
    }

    /** Kullanıcının tema tercihini kaydeder. */
    public function updateTheme(int $id, string $theme): void
    {
        $stmt = $this->db->prepare('UPDATE users SET theme = :theme WHERE id = :id');
        $stmt->execute([':theme' => User::normalizeTheme($theme), ':id' => $id]);
    }

    /* =================================================================
     *  CRUD (REST uç noktaları için)
     * ============================================================== */

    /**
     * Yeni kullanıcı ekler ve ID'sini döndürür.
     *
     * E-posta KÜÇÜK HARFE çevrilerek saklanır. Aksi halde
     * "Ali@x.com" ve "ali@x.com" veritabanı için iki farklı değer
     * olur, UNIQUE kısıt devreye girmez ve aynı kişi iki hesap açar.
     */
    public function create(string $name, string $surname, string $email, string $password, bool $isActive = true): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, surname, email, password, is_active, created_at)
             VALUES (:name, :surname, :email, :password, :active, NOW())'
        );

        $stmt->execute([
            ':name'     => $name,
            ':surname'  => $surname,
            ':email'    => mb_strtolower(trim($email), 'UTF-8'),

            /* PASSWORD_DEFAULT sabittir, algoritma adı DEĞİLDİR:
             * PHP sürümleri ilerledikçe daha güçlü bir algoritmaya
             * işaret eder ve kodunuz kendiliğinden güçlenir. */
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':active'   => $isActive ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Yalnızca GÖNDERİLEN alanları günceller (PATCH davranışı).
     *
     * NEDEN KISMİ GÜNCELLEME?
     * Tüm alanları zorunlu tutan bir güncelleme (PUT), istemcinin
     * yalnızca adı değiştirmek isterken diğer alanları da göndermesini
     * şart koşar. Göndermeyi unutursa o alanlar SİLİNİR. PATCH bu
     * tehlikeyi ortadan kaldırır.
     *
     * @param array<string,mixed> $fields Beyaz listeden geçmiş alanlar
     */
    public function update(int $id, array $fields): bool
    {
        /* BEYAZ LİSTE ŞART: Gelen diziyi doğrudan SQL'e çevirmek,
         * istemcinin "id" veya "password" gibi alanları da
         * güncelleyebilmesi demektir (mass assignment açığı).
         * Yalnızca burada sayılan sütunlara izin veriyoruz. */
        $allowed = ['name', 'surname', 'email', 'is_active'];

        $sets   = [];
        $params = [':id' => $id];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $fields)) {
                continue;
            }

            $value = $fields[$column];

            if ($column === 'email') {
                $value = mb_strtolower(trim((string) $value), 'UTF-8');
            }

            if ($column === 'is_active') {
                $value = $value ? 1 : 0;
            }

            /* Sütun adı BEYAZ LİSTEDEN gelir, istekten değil; değer ise
             * her zaman parametre olarak bağlanır. İkisi birlikte SQL
             * enjeksiyonuna kapıyı tamamen kapatır. */
            $sets[]             = $column . ' = :' . $column;
            $params[':' . $column] = $value;
        }

        if ($sets === []) {
            return false;
        }

        $sets[] = 'updated_at = NOW()';

        $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);

        return true;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * E-posta başka bir kullanıcıya ait mi?
     *
     * $exceptId, güncelleme sırasında kaydın KENDİ e-postasını
     * "çakışma" saymamak içindir.
     *
     * NOT: Bu kontrol yarış durumuna açıktır (iki istek aynı anda
     * gelirse ikisi de "boş" görebilir). Son sözü veritabanındaki
     * UNIQUE kısıt söyler; buradaki kontrol yalnızca kullanıcıya
     * DÜZGÜN BİR MESAJ verebilmek içindir.
     */
    public function emailTaken(string $email, int $exceptId = 0): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id'
        );
        $stmt->execute([
            ':email' => mb_strtolower(trim($email), 'UTF-8'),
            ':id'    => $exceptId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
