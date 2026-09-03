<?php
/**
 * =====================================================================
 *  ApiExamples – "Örnek Kullanım" kod parçaları
 * ---------------------------------------------------------------------
 *  JETON ÜRETMEK YETMEZ.
 *  Kullanıcı jetonu aldıktan sonra "peki şimdi ne yapacağım?" sorusuyla
 *  baş başa kalıyordu. Bu sınıf o boşluğu dolduran, ÇALIŞIR durumdaki
 *  örnekleri üretir: adres ve jeton yerine gerçek değerler basılır,
 *  kopyala-yapıştır ile doğrudan çalışır.
 *
 *  TEK KAYNAK: Aynı metin hem sayfadaki sekmelerde gösterilir hem de
 *  indirilen dosyaya yazılır. İkiye ayırmak, birini güncelleyip
 *  diğerini unutmanın en kısa yoludur.
 *
 *  İNDİRİLEN DOSYAYA GERÇEK JETON YAZILMAZ.
 *  Sayfada jeton görünür (kullanıcı zaten o an ekranda okuyor), ama
 *  dosyaya yer tutucu konur. Bir kimlik bilgisini dosyaya gömmek, onu
 *  "İndirilenler" klasöründe, yedeklerde ve er geç bir kod deposunda
 *  bırakmanın en kolay yoludur — bu örneğin anlatmaya çalıştığı şeyin
 *  tam tersi.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Support;

use App\Core\Router;
use App\Core\Session;

final class ApiExamples
{
    /** İndirilen dosyalarda gerçek jetonun yerine yazılan metin. */
    public const YER_TUTUCU = 'BURAYA_JETONUNUZU_YAPISTIRIN';

    /** Jeton görünmediğinde sayfada gösterilen örnek değer. */
    public const ORNEK_JETON = 'cy_1a2b3c4d5e6f708192a3b4c5d6e7f809';

    /**
     * Desteklenen diller.
     *
     * Anahtar, indirme adresindeki "dil" parametresidir ve BEYAZ LİSTE
     * görevi görür: buradan geçmeyen bir değer dosya döndürmez.
     *
     * @return array<string,array{ad:string,dil:string,dosya:string,tur:string}>
     */
    public static function diller(): array
    {
        return [
            'curl'   => ['ad' => 'cURL',       'dil' => 'bash',       'dosya' => 'cy-api-ornek.sh',  'tur' => 'text/x-shellscript'],
            'php'    => ['ad' => 'PHP',        'dil' => 'php',        'dosya' => 'cy-api-ornek.php', 'tur' => 'text/x-php'],
            'js'     => ['ad' => 'JavaScript', 'dil' => 'javascript', 'dosya' => 'cy-api-ornek.js',  'tur' => 'text/javascript'],
            'python' => ['ad' => 'Python',     'dil' => 'python',     'dosya' => 'cy-api-ornek.py',  'tur' => 'text/x-python'],
        ];
    }

    /**
     * API'nin TAM taban adresi (şema + alan adı + alt klasör + /api/v1).
     *
     * Örnekler kopyalanıp doğrudan çalıştırılabilsin diye göreli değil
     * MUTLAK adres basılır. Adres tek yerden üretilir; belgeler sayfası
     * ile jeton sayfası farklı adres göstermesin.
     */
    public static function tabanUrl(): string
    {
        return (Session::isHttps() ? 'https://' : 'http://')
             . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')
             . Router::basePath()
             . '/api/v1';
    }

    /**
     * Tüm örnekleri döndürür.
     *
     * @return array<int,array{anahtar:string,ad:string,dil:string,dosya:string,kod:string}>
     */
    public static function tumu(string $tabanUrl, string $jeton): array
    {
        $sonuc = [];

        foreach (self::diller() as $anahtar => $bilgi) {
            $sonuc[] = [
                'anahtar' => $anahtar,
                'ad'      => $bilgi['ad'],
                'dil'     => $bilgi['dil'],
                'dosya'   => $bilgi['dosya'],
                'kod'     => self::kod($anahtar, $tabanUrl, $jeton),
            ];
        }

        return $sonuc;
    }

    /** Tek bir dilin kodunu üretir; bilinmeyen dil için boş dize döner. */
    public static function kod(string $anahtar, string $tabanUrl, string $jeton): string
    {
        $tabanUrl = rtrim($tabanUrl, '/');

        return match ($anahtar) {
            'curl'   => self::curl($tabanUrl, $jeton),
            'php'    => self::php($tabanUrl, $jeton),
            'js'     => self::js($tabanUrl, $jeton),
            'python' => self::python($tabanUrl, $jeton),
            default  => '',
        };
    }

    // =================================================================
    //  DİLLER
    // -----------------------------------------------------------------
    //  Örnekler bilerek ASCII yazıldı: indirilen dosya Windows'ta
    //  ANSI kod sayfasıyla açıldığında Türkçe karakterler bozuluyordu.
    // =================================================================

    private static function curl(string $url, string $jeton): string
    {
        return <<<BASH
            #!/usr/bin/env bash
            # =================================================================
            #  CY REST API - ornek kullanim (cURL)
            # -----------------------------------------------------------------
            #  Jeton BASLIKTA tasinir, adres cubugunda degil: adresteki bir
            #  jeton sunucu gunluklerine, tarayici gecmisine ve Referer
            #  basligina sizar.
            # =================================================================
            set -euo pipefail

            TOKEN="{$jeton}"
            BASE="{$url}"
            AUTH=(-H "Authorization: Bearer \$TOKEN")

            # -----------------------------------------------------------------
            # 1) LISTELE  (kapsam: read)
            #    Yanit data/meta/links zarfiyla gelir; sayfalamayi tahmin
            #    etmeniz gerekmez, meta.last_page icinde yazar.
            # -----------------------------------------------------------------
            curl -s "\${AUTH[@]}" "\$BASE/users?per=10&page=1"

            # Arama ve durum suzgeci
            curl -s "\${AUTH[@]}" "\$BASE/users?q=ali&status=active"

            # -----------------------------------------------------------------
            # 2) TEK KAYIT  (kapsam: read)
            # -----------------------------------------------------------------
            curl -s "\${AUTH[@]}" "\$BASE/users/11"

            # -----------------------------------------------------------------
            # 3) OLUSTUR  (kapsam: write)  ->  201 + Location basligi
            # -----------------------------------------------------------------
            curl -s -X POST "\${AUTH[@]}" \\
                 -H "Content-Type: application/json" \\
                 -d '{"name":"Ayse","surname":"Yilmaz","email":"ayse@ornek.com","password":"Gizli1234"}' \\
                 "\$BASE/users"

            # -----------------------------------------------------------------
            # 4) KISMI GUNCELLE  (kapsam: write)
            #    Yalnizca gonderdiginiz alanlar degisir.
            # -----------------------------------------------------------------
            curl -s -X PATCH "\${AUTH[@]}" \\
                 -H "Content-Type: application/json" \\
                 -d '{"is_active":false}' \\
                 "\$BASE/users/11"

            # -----------------------------------------------------------------
            # 5) SIL  (kapsam: write)  ->  204, govde yok
            # -----------------------------------------------------------------
            curl -s -X DELETE "\${AUTH[@]}" "\$BASE/users/52"

            # -----------------------------------------------------------------
            # 6) HIZ SINIRI
            #    Her yanitta X-RateLimit-* basliklari gelir. -i ekleyip
            #    kalan hakkinizi gorebilirsiniz.
            # -----------------------------------------------------------------
            curl -si "\${AUTH[@]}" "\$BASE/users?per=1" | grep -i "x-ratelimit"
            BASH;
    }

    private static function php(string $url, string $jeton): string
    {
        return <<<PHPKOD
            <?php
            /**
             * =================================================================
             *  CY REST API - ornek kullanim (PHP, kutuphanesiz)
             * -----------------------------------------------------------------
             *  Composer paketi gerekmez; cURL eklentisi yeterlidir.
             * =================================================================
             */

            declare(strict_types=1);

            const TOKEN = '{$jeton}';
            const BASE  = '{$url}';

            /**
             * Tek istek noktasi.
             *
             * HATAYI YUTMAZ: API'nin dondurdugu durum kodunu ve govdeyi
             * oldugu gibi geri verir. Kosullarinizi MESAJ metnine degil
             * error.code degerine kurun; mesaj degisse bile entegrasyonunuz
             * bozulmaz.
             *
             * @return array{durum:int,govde:array<string,mixed>|null}
             */
            function api(string \$yontem, string \$yol, ?array \$veri = null): array
            {
                \$ch = curl_init(BASE . \$yol);

                \$basliklar = ['Authorization: Bearer ' . TOKEN, 'Accept: application/json'];

                if (\$veri !== null) {
                    \$basliklar[] = 'Content-Type: application/json';
                    curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(\$veri, JSON_UNESCAPED_UNICODE));
                }

                curl_setopt_array(\$ch, [
                    CURLOPT_CUSTOMREQUEST  => \$yontem,
                    CURLOPT_HTTPHEADER     => \$basliklar,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                ]);

                \$govde = (string) curl_exec(\$ch);
                \$durum = (int) curl_getinfo(\$ch, CURLINFO_RESPONSE_CODE);

                curl_close(\$ch);

                // 204 (No Content) govdesizdir; json_decode'a bos dize vermeyin.
                return ['durum' => \$durum, 'govde' => \$govde === '' ? null : json_decode(\$govde, true)];
            }

            // -----------------------------------------------------------------
            // 1) LISTELE  (kapsam: read)
            // -----------------------------------------------------------------
            \$cevap = api('GET', '/users?per=10&page=1');

            if (\$cevap['durum'] === 200) {
                foreach (\$cevap['govde']['data'] as \$kullanici) {
                    printf("#%d  %s %s  <%s>\\n", \$kullanici['id'], \$kullanici['name'], \$kullanici['surname'], \$kullanici['email']);
                }

                \$meta = \$cevap['govde']['meta'];
                printf("Toplam %d kayit, %d sayfa (su an %d/%d)\\n", \$meta['total'], \$meta['last_page'], \$meta['current_page'], \$meta['last_page']);
            }

            // -----------------------------------------------------------------
            // 2) TEK KAYIT  (kapsam: read)
            // -----------------------------------------------------------------
            \$cevap = api('GET', '/users/11');

            // -----------------------------------------------------------------
            // 3) OLUSTUR  (kapsam: write)  ->  201
            // -----------------------------------------------------------------
            \$cevap = api('POST', '/users', [
                'name'     => 'Ayse',
                'surname'  => 'Yilmaz',
                'email'    => 'ayse@ornek.com',
                'password' => 'Gizli1234',
            ]);

            // Dogrulama hatasi ALAN BAZLIDIR: hangi kutuyu kirmizi yapacaginizi bilirsiniz.
            if (\$cevap['durum'] === 422) {
                foreach (\$cevap['govde']['error']['details'] as \$alan => \$mesajlar) {
                    printf("%s: %s\\n", \$alan, implode(' ', (array) \$mesajlar));
                }
            }

            // -----------------------------------------------------------------
            // 4) KISMI GUNCELLE / 5) SIL  (kapsam: write)
            // -----------------------------------------------------------------
            \$cevap = api('PATCH', '/users/11', ['is_active' => false]);
            \$cevap = api('DELETE', '/users/52');   // 204, govde yok

            // -----------------------------------------------------------------
            //  SIK KARSILASILAN DURUMLAR
            // -----------------------------------------------------------------
            //  401 invalid_token       Jeton yok, hatali veya iptal edilmis
            //  403 insufficient_scope  Jetonun bu islem icin kapsami yok
            //                          (yazma islemleri "write" ister)
            //  422 validation_failed   Alan bazli hatalar error.details icinde
            //  429 rate_limited        Hiz siniri asildi; Retry-After saniye verir
            PHPKOD;
    }

    private static function js(string $url, string $jeton): string
    {
        return <<<JSKOD
            /* =================================================================
             *  CY REST API - ornek kullanim (JavaScript, fetch)
             * -----------------------------------------------------------------
             *  JETONU TARAYICIDA CALISAN KODA KOYMAYIN.
             *  Bu dosya Node.js ya da sunucu tarafi bir betik icindir.
             *  Tarayicida jeton, sayfanin kaynagini goruntuleyen herkese
             *  aciktir; oradan cagri yapacaksaniz istegi kendi sunucunuz
             *  uzerinden gecirin.
             * ================================================================= */

            const TOKEN = '{$jeton}';
            const BASE  = '{$url}';

            async function api(yontem, yol, veri = null) {
                const secenekler = {
                    method: yontem,
                    headers: {
                        Authorization: `Bearer \${TOKEN}`,
                        Accept: 'application/json',
                    },
                };

                if (veri !== null) {
                    secenekler.headers['Content-Type'] = 'application/json';
                    secenekler.body = JSON.stringify(veri);
                }

                const cevap = await fetch(BASE + yol, secenekler);

                // 204 (No Content) govdesizdir; json() cagirmak hata firlatir.
                const govde = cevap.status === 204 ? null : await cevap.json();

                return { durum: cevap.status, govde };
            }

            (async () => {
                // -------------------------------------------------------------
                // 1) LISTELE  (kapsam: read)
                // -------------------------------------------------------------
                const liste = await api('GET', '/users?per=10&page=1');

                if (liste.durum === 200) {
                    liste.govde.data.forEach((k) => {
                        console.log(`#\${k.id}  \${k.name} \${k.surname}  <\${k.email}>`);
                    });

                    const { total, current_page: sayfa, last_page: sonSayfa } = liste.govde.meta;
                    console.log(`Toplam \${total} kayit, \${sonSayfa} sayfa (su an \${sayfa}/\${sonSayfa})`);
                }

                // -------------------------------------------------------------
                // 2) TEK KAYIT  (kapsam: read)
                // -------------------------------------------------------------
                await api('GET', '/users/11');

                // -------------------------------------------------------------
                // 3) OLUSTUR  (kapsam: write)  ->  201
                // -------------------------------------------------------------
                const olustur = await api('POST', '/users', {
                    name: 'Ayse',
                    surname: 'Yilmaz',
                    email: 'ayse@ornek.com',
                    password: 'Gizli1234',
                });

                // Dogrulama hatasi ALAN BAZLIDIR.
                if (olustur.durum === 422) {
                    Object.entries(olustur.govde.error.details).forEach(([alan, mesajlar]) => {
                        console.warn(alan, [].concat(mesajlar).join(' '));
                    });
                }

                // -------------------------------------------------------------
                // 4) KISMI GUNCELLE / 5) SIL  (kapsam: write)
                // -------------------------------------------------------------
                await api('PATCH', '/users/11', { is_active: false });
                await api('DELETE', '/users/52');   // 204, govde yok
            })();

            /* -----------------------------------------------------------------
             *  SIK KARSILASILAN DURUMLAR
             * -----------------------------------------------------------------
             *  401 invalid_token       Jeton yok, hatali veya iptal edilmis
             *  403 insufficient_scope  Jetonun bu islem icin kapsami yok
             *  422 validation_failed   Alan bazli hatalar error.details icinde
             *  429 rate_limited        Hiz siniri asildi; Retry-After saniye verir
             * ----------------------------------------------------------------- */
            JSKOD;
    }

    private static function python(string $url, string $jeton): string
    {
        return <<<PYKOD
            #!/usr/bin/env python3
            # =================================================================
            #  CY REST API - ornek kullanim (Python, requests)
            # -----------------------------------------------------------------
            #  Kurulum:  pip install requests
            # =================================================================

            import requests

            TOKEN = "{$jeton}"
            BASE = "{$url}"

            oturum = requests.Session()
            oturum.headers.update({
                "Authorization": f"Bearer {TOKEN}",
                "Accept": "application/json",
            })


            def api(yontem, yol, veri=None):
                """Tek istek noktasi. 204 govdesizdir; json() cagirmayin."""
                cevap = oturum.request(yontem, BASE + yol, json=veri, timeout=15)
                govde = None if cevap.status_code == 204 else cevap.json()

                return cevap.status_code, govde


            # -----------------------------------------------------------------
            # 1) LISTELE  (kapsam: read)
            # -----------------------------------------------------------------
            durum, govde = api("GET", "/users?per=10&page=1")

            if durum == 200:
                for k in govde["data"]:
                    print(f"#{k['id']}  {k['name']} {k['surname']}  <{k['email']}>")

                meta = govde["meta"]
                print(f"Toplam {meta['total']} kayit, {meta['last_page']} sayfa (su an {meta['current_page']}/{meta['last_page']})")

            # -----------------------------------------------------------------
            # 2) TEK KAYIT  (kapsam: read)
            # -----------------------------------------------------------------
            durum, govde = api("GET", "/users/11")

            # -----------------------------------------------------------------
            # 3) OLUSTUR  (kapsam: write)  ->  201
            # -----------------------------------------------------------------
            durum, govde = api("POST", "/users", {
                "name": "Ayse",
                "surname": "Yilmaz",
                "email": "ayse@ornek.com",
                "password": "Gizli1234",
            })

            # Dogrulama hatasi ALAN BAZLIDIR.
            if durum == 422:
                for alan, mesajlar in govde["error"]["details"].items():
                    print(alan, " ".join(mesajlar if isinstance(mesajlar, list) else [mesajlar]))

            # -----------------------------------------------------------------
            # 4) KISMI GUNCELLE / 5) SIL  (kapsam: write)
            # -----------------------------------------------------------------
            durum, govde = api("PATCH", "/users/11", {"is_active": False})
            durum, govde = api("DELETE", "/users/52")   # 204, govde yok

            # -----------------------------------------------------------------
            #  SIK KARSILASILAN DURUMLAR
            # -----------------------------------------------------------------
            #  401 invalid_token       Jeton yok, hatali veya iptal edilmis
            #  403 insufficient_scope  Jetonun bu islem icin kapsami yok
            #  422 validation_failed   Alan bazli hatalar error.details icinde
            #  429 rate_limited        Hiz siniri asildi; Retry-After saniye verir
            PYKOD;
    }
}
