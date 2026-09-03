<?php
/**
 * =====================================================================
 *  Paginator – Sunucu taraflı sayfalama
 * ---------------------------------------------------------------------
 *  NEDEN SAYFALAMA?
 *  10.000 satırlık bir tabloyu tek seferde çekmek üç yerde birden
 *  patlar: veritabanı gereksiz iş yapar, PHP belleği şişer, tarayıcı
 *  on binlerce DOM düğümüyle boğulur. Sayfalama, kullanıcının GERÇEKTEN
 *  gördüğü kadarını (20-50 satır) getirir.
 *
 *  NEDEN "JavaScript ile filtrele" YETMEZ?
 *  Tüm veriyi tarayıcıya gönderip orada sayfalamak, sayfalamanın
 *  çözdüğü sorunların hiçbirini çözmez — üstelik kullanıcının
 *  görmemesi gereken satırlar da tarayıcıya ulaşmış olur. Sayfalama
 *  SUNUCUDA yapılmalıdır.
 *
 *  ---------------------------------------------------------------
 *  BU SINIF NE YAPAR?
 *  Ham sayıları (toplam kayıt, sayfa no, sayfa boyutu) alır ve
 *  görünümün ihtiyaç duyduğu her şeyi hesaplar: kaç sayfa var, hangi
 *  sayfa numaraları basılacak, LIMIT/OFFSET ne olacak.
 *
 *  SQL'İ BİLMEZ. Repository, offset() ve perPage() değerlerini alıp
 *  kendi sorgusunu kurar. Böylece aynı sınıf kullanıcı listesinde de,
 *  kuyruk kayıtlarında da, sohbet geçmişinde de çalışır.
 *
 *  KULLANIMI:
 *      $page      = Paginator::pageFromRequest($request);
 *      $total     = $repo->countAll($search);
 *      $paginator = new Paginator($total, $page, 20);
 *      $rows      = $repo->page($paginator->offset(), $paginator->perPage(), $search);
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    /**
     * Sayfa başına izin verilen değerler (BEYAZ LİSTE).
     *
     * Kullanıcı adres çubuğuna ?per=1000000 yazarak sunucuyu
     * zorlayabilirdi. Serbest bir sayı kabul etmek yerine sabit bir
     * listeden seçtiriyoruz: liste dışındaki her değer varsayılana
     * düşer. Bu, "kaynak tüketimi" saldırılarına karşı en ucuz ve en
     * kesin savunmadır.
     *
     * @var array<int,int>
     */
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const DEFAULT_PER_PAGE = 20;

    /** Toplam sayfa sayısı (en az 1 — boş liste de "1. sayfa"dır). */
    private int $lastPage;

    /**
     * @param int $total   Filtreden GEÇMİŞ toplam kayıt sayısı
     * @param int $page    İstenen sayfa (1'den başlar)
     * @param int $perPage Sayfa başına kayıt
     */
    public function __construct(
        private readonly int $total,
        private int $page = 1,
        private readonly int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        // (int) ceil(0 / 20) = 0 olurdu; en az bir sayfa olmalı.
        $this->lastPage = max(1, (int) ceil($this->total / max(1, $this->perPage)));

        /* SON SAYFANIN ÖTESİNE GEÇMEK:
         * Kullanıcı 5. sayfadayken bir filtre uygularsa sonuç 2 sayfaya
         * düşebilir. O anda page=5 hâlâ adres çubuğundadır ve BOŞ bir
         * tablo görünür — "sistem bozuldu" izlenimi verir.
         * Sayfayı sınırın içine çekerek bunu engelliyoruz. */
        $this->page = min(max(1, $this->page), $this->lastPage);
    }

    /* =================================================================
     *  İSTEKTEN OKUMA
     * ============================================================== */

    /**
     * Adresteki page değerini güvenle okur.
     *
     * (int) dönüşümü "abc" için 0, "-3" için -3 verir; ikisi de geçersiz
     * sayfa numaralarıdır. max(1, ...) bunları 1'e çeker.
     */
    public static function pageFromRequest(Request $request, string $key = 'page'): int
    {
        return max(1, (int) $request->raw($key, '1'));
    }

    /**
     * Adresteki per değerini BEYAZ LİSTEDEN geçirir.
     * Listede olmayan her değer varsayılana döner.
     */
    public static function perPageFromRequest(Request $request, string $key = 'per'): int
    {
        $value = (int) $request->raw($key, (string) self::DEFAULT_PER_PAGE);

        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : self::DEFAULT_PER_PAGE;
    }

    /* =================================================================
     *  SORGU İÇİN DEĞERLER
     * ============================================================== */

    /**
     * SQL OFFSET değeri.
     *
     * 1. sayfa → 0, 2. sayfa → 20, 3. sayfa → 40 …
     *
     * DİKKAT: Bu değer sorguya hazırlanmış ifade parametresi olarak
     * değil, tamsayıya çevrilmiş hâliyle gömülür (bkz. Repository).
     * MySQL, LIMIT/OFFSET konumunda parametreyi bazı sürücü
     * yapılandırmalarında kabul etmez. Değerin int olduğunu burada
     * garanti ettiğimiz için SQL enjeksiyonu riski yoktur — dışarıdan
     * gelen metin hiçbir zaman sorguya ulaşmaz.
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    /* =================================================================
     *  GÖRÜNÜM İÇİN DEĞERLER
     * ============================================================== */

    public function total(): int       { return $this->total; }
    public function currentPage(): int { return $this->page; }
    public function lastPage(): int    { return $this->lastPage; }

    public function hasPages(): bool    { return $this->lastPage > 1; }
    public function onFirstPage(): bool { return $this->page <= 1; }
    public function onLastPage(): bool  { return $this->page >= $this->lastPage; }

    /** Bu sayfadaki ilk kaydın sıra numarası (1 tabanlı). */
    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    /** Bu sayfadaki son kaydın sıra numarası. */
    public function to(): int
    {
        return min($this->offset() + $this->perPage, $this->total);
    }

    /**
     * Basılacak sayfa numaraları. Boşluklar null ile işaretlenir:
     *
     *      [1, null, 7, 8, 9, 10, 11, null, 42]
     *             ↑                       ↑
     *            "…"                     "…"
     *
     * NEDEN HEPSİNİ BASMIYORUZ?
     * 500 sayfalık bir listede 500 bağlantı basmak, sayfalamanın
     * amacını yok eder: çubuk ekrandan taşar, telefonda hiç
     * kullanılamaz. İlk sayfa, son sayfa ve bulunduğunuz yerin
     * çevresi — ihtiyaç duyulan tek şey budur.
     *
     * @param int $around Bulunduğunuz sayfanın iki yanında kaç numara
     * @return array<int,int|null>
     */
    public function pages(int $around = 2): array
    {
        // Az sayfa varsa akıllı davranmaya gerek yok; hepsini bas.
        if ($this->lastPage <= 7) {
            return range(1, $this->lastPage);
        }

        $window = range(
            max(1, $this->page - $around),
            min($this->lastPage, $this->page + $around)
        );

        $pages = [];

        // İlk sayfa her zaman erişilebilir olmalı.
        if (!in_array(1, $window, true)) {
            $pages[] = 1;

            // Araya en az bir sayfa giriyorsa "…" koy.
            if ($window[0] > 2) {
                $pages[] = null;
            }
        }

        foreach ($window as $page) {
            $pages[] = $page;
        }

        // Son sayfa da her zaman erişilebilir olmalı.
        if (!in_array($this->lastPage, $window, true)) {
            if (end($window) < $this->lastPage - 1) {
                $pages[] = null;
            }

            $pages[] = $this->lastPage;
        }

        return $pages;
    }

    /**
     * Belirli bir sayfanın adresi.
     *
     * MEVCUT FİLTRELERİ KORUMAK ŞARTTIR: kullanıcı arama yaptıktan
     * sonra "2. sayfa"ya bastığında araması kaybolursa, sayfalama
     * kullanılamaz hâle gelir. $query ile gelen tüm parametreleri
     * bağlantıya taşıyoruz.
     *
     * @param array<string,string|int> $query Korunacak parametreler
     */
    public function url(int $page, string $route, array $query = []): string
    {
        return url($route, array_merge($query, ['page' => $page]));
    }

    /**
     * AJAX yanıtları ve REST uç noktaları için özet bilgi.
     *
     * @return array<string,int|bool>
     */
    public function toArray(): array
    {
        return [
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->page,
            'last_page'    => $this->lastPage,
            'from'         => $this->from(),
            'to'           => $this->to(),
            'has_more'     => !$this->onLastPage(),
        ];
    }
}
