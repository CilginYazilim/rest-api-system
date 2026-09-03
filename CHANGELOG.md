# Değişiklik Günlüğü

Bu dosyanın biçimi [Keep a Changelog](https://keepachangelog.com/tr/1.1.0/)
kalıbını izler ve proje [Semantic Versioning](https://semver.org/lang/tr/)
kurallarına uyar.

---

## [1.1.0] — 2026-09-03

### Eklendi

- **Örnek Kullanım** bölümü: cURL, PHP, JavaScript ve Python için çalışır
  durumda örnek kodlar. Hem *API Jetonları* hem *API Belgeleri* sayfasında
  aynı parçadan basılır. Adres bu sunucunun gerçek adresidir; jeton yeni
  üretildiyse gerçek jeton, değilse örnek bir değer gösterilir.
- Örnek dosya indirme (`GET tokens/ornek?dil=…`). İndirilen dosyaya
  **gerçek jeton yazılmaz**; yerine `BURAYA_JETONUNUZU_YAPISTIRIN` konur.
- `docs/index.html`: ekran görüntüsü galerisi. `docs/` adresi, dizin
  listeleme kapalı olduğu için `403` dönüyordu.
- Jeton listesinde **Detaylar** düğmesi ve modal: ad, numara, yetkiler,
  oluşturma, son kullanım + IP, durum ve iptal tarihi. Jetonun kendisi
  gösterilemez (veritabanında yalnızca SHA-256 özeti var); modal bunun
  nedenini de anlatır.
- Kullanıcılar sayfasında filtreler **"Uygula"ya basmadan** çalışır:
  açılır listeler anında, arama kutusu 450 ms beklemeyle. JavaScript
  kapalıysa düğme görünür kalır ve form normal gönderilir.
- Jeton başına **ömür boyu istek sayacı** (`api_tokens.request_count`).
  Jeton listesinde yeni bir sütun ve Detaylar modalında görünür; mobilde
  ad hücresinin altındaki özet satırına da eklendi.
  `ApiTokenRepository::touch()` içinde `request_count = request_count + 1`
  ile **atomik** artırılır — iki isteğin aynı anda "önce oku sonra yaz"
  yapıp birbirini ezmesi riski yok.

  **`api_requests`'ten DEĞİL bu sütundan okunur.** O tablo yalnızca hız
  sınırının kayan penceresidir; `ApiRateLimiter::prune()` satırları bir
  saat sonra siler. Oradan `COUNT(*)` ile toplam üretmeye çalışmak,
  zamanla küçülen ve yanlış bir sayı verirdi.

  > **Var olan bir kurulumu yükseltiyorsanız:** proje ayrı bir migration
  > aracı kullanmaz, `database.sql` yalnızca ilk kuruluş içindir. Sütunu
  > elle eklemeniz gerekir:
  > ```sql
  > ALTER TABLE api_tokens
  >   ADD COLUMN request_count INT UNSIGNED NOT NULL DEFAULT 0
  >   AFTER last_used_ip;
  > ```
- `.gitattributes`: satır sonları depoda her zaman LF olarak saklanır,
  ikili dosyalar (PNG, font) işaretlendi. Bu dosya olmadan satır sonu
  davranışı her katkıcının `core.autocrlf` ayarına kalıyor ve tek bir
  boşluk değişmediği hâlde "dosyanın tamamı değişti" diyen commit'ler
  çıkıyordu.

### Düzeltildi

- README'lerde `per` parametresi **yanlış anlatılıyordu**. Dokuz ayrı yerde
  "tavanlanır" deniyordu; oysa `Paginator::perPageFromRequest()` bir
  **beyaz liste** uygular. Tavan olsaydı `per=100000` → `100` olurdu;
  gerçekte `20`'ye döner. Küçük değerler de listede değildir: `per=3` de
  `20` verir. Ölçülen davranış:

  | İstenen | Dönen |
  |---|---|
  | `10` · `20` · `50` · `100` | aynısı |
  | `3` · `250` · `100000` · `abc` | `20` |

  Kodun kendi yorumları ve uygulama içindeki API belgeleri sayfası
  zaten doğruydu; kayan yalnızca README'lerdi.

- **Örnek Kullanım** kodlarındaki yazma çağrıları (`POST`/`PATCH`/`DELETE`)
  varsayılan olarak **yorum satırına alındı**. Dört dilin örneği de dosyayı
  olduğu gibi çalıştıran birine 5 gerçek istek attırıyordu (listele + tek
  kayıt + oluştur + güncelle + sil) — ömür boyu istek sayacı her çalıştırmada
  5 artıyordu ve ikinci çalıştırmada `POST` `409 email_taken`, `DELETE`
  `404 not_found` dönüyordu (ilk çalıştırma zaten o e-postayı almış ve o
  kaydı silmişti). Artık yalnızca **okuma** çağrıları (listele, tek kayıt)
  otomatik çalışır; yazma örnekleri kod olarak duruyor, denemek isteyen
  yorum işaretini kendisi kaldırır.

- **Örnek Kullanım** kodlarındaki PHP/JS/Python uyarısı: `meta.total_pages`
  diye bir alan hiç var olmadı. Gerçek API yanıtı `total`, `per_page`,
  `current_page`, `last_page`, `from`, `to`, `has_more` alanlarını taşır
  (bkz. `Paginator::toArray()`); örnekler `total_pages` okumaya çalışınca
  indirilen PHP dosyası `Warning: Undefined array key "total_pages"`
  veriyordu. README'deki iki örnek yanıt gövdesi de aynı yanlış alanı
  gösteriyordu; ikisi de gerçek şemaya göre düzeltildi.

### Değişti

- API belgeleri sayfasının adresi `docs` → **`api-belgeleri`**. Diskteki
  gerçek `docs/` klasörü rotayı gölgeliyordu: temiz adres kuralı, var olan
  bir klasöre denk gelen isteği `index.php`'ye devretmiyor
  (`RewriteCond %{REQUEST_FILENAME} !-d`). Sayfaya hiç ulaşılamıyordu.
- `.htaccess`: `DirectoryIndex`'e `index.html` eklendi.
- Jeton sayfasının bölüm sırası: **Yeni Jeton → Jetonlarım → Örnek
  Kullanım**. Önce jeton üretilir, sonra üretilenler görülür, en sonda
  "bununla ne yapacağım?" sorusunun yanıtı gelir.

### Kaldırıldı

- `views/errors/403.php`. Ortak panel iskeletinden geliyordu ama bu
  projede rol tabanlı kısıt yok: hiçbir kod yolu bu görünümü basmıyordu.
  (Panel yetkisizlikte `login`'e yönlendirir, CSRF hatasında `dashboard`'a
  döner; API ise HTML değil `403 insufficient_scope` JSON'u döndürür.)
- `config/config.php` içindeki `'locale' => 'tr_TR'` anahtarı. Hiçbir
  yerden okunmuyordu; tarih ve sayı biçimlendirmesi `human_date()` ile
  elle yapılıyor.

### Güvenlik

- `.env.example` artık `APP_DEBUG=true` ile **gelmiyor**; satır yorumda.
  Dosyayı olduğu gibi kopyalayıp canlıya çıkan biri, açık hata yığınıyla
  dosya yollarını, tablo adlarını ve sorgularını sızdırıyordu. Satır
  yokken `Env::isLocalHost()` karar veriyor: yerelde açık, gerçek alan
  adında kapalı.

---

## [1.0.0] — 2026-09-03

İlk yayın. REST API Sistemi, Çılgın Yazılım Kaynak Kütüphanesi'nde yayınlandı.

### Eklendi

- Bearer jeton doğrulama; veritabanında yalnızca SHA-256 özeti saklanıyor
- Kapsam (scope) denetimi rota düzeyinde: `read` / `write`
- Jeton başına hız sınırı ve `X-RateLimit-Limit` / `-Remaining` / `-Reset` başlıkları
- Sınır aşımında `429` ve `Retry-After`
- `data` + `meta` + `links` yanıt zarfı; tavanlanmış sayfa boyutu
- Tutarlı hata zarfı: makine tarafından okunabilir `code` + `message` + `details`
- Doğru HTTP kodları: 200 · 201 · 204 · 400 · 401 · 403 · 404 · 405 · 409 · 422 · 429
- `201` yanıtıyla birlikte `Location` başlığı
- Jeton yönetimi paneli: üretme, kapsam seçimi, son kullanım, iptal etme
- İptal edilen jetonun silinmemesi (`revoked_at`) — istek kayıtları bağlı kalıyor
- API belgeleri sayfası, kopyalanabilir `curl` örnekleriyle

**Ortak altyapı (bütün panelli örneklerde aynı)**

- Oturum girişi, "beni hatırla" jetonu ve giriş denemesi hız sınırı
- CSRF koruması (`hash_equals` ile karşılaştırma)
- Sertleştirilmiş oturum: `HttpOnly`, `SameSite`, girişte kimlik yenileme
- Güvenlik başlıkları: CSP (`script-src 'self'`), `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy`
- Tüm sorgular hazır ifade; `ATTR_EMULATE_PREPARES = false`
- Sunucu tarafında sayfalama ve arama; sıralama sütunu beyaz listeden
- Açık / koyu tema, kullanıcı hesabına kayıtlı
- Mobilde alt navigasyon; sayfa gövdesinde yatay kaydırma yok
- Türkçe ve İngilizce belgeler, ekran görüntüleriyle
- Sıfır bağımlılık: Composer yok, npm yok, CDN yok

### Güvenlik

- `APP_DEBUG` **ortamdan türetiliyor**: `.env` dosyası olmadan canlıya
  alınsa bile hata yığını ziyaretçiye görünmez (`Env::isLocalHost()`)
- `json_encode()` çağrılarında `JSON_INVALID_UTF8_SUBSTITUTE`; bozuk tek
  bir bayt yanıtın tamamını yutmuyor
- Komut satırı betikleri hem `.htaccess` ile hem `PHP_SAPI` kontrolüyle
  web erişimine kapalı

### Düzeltildi

- Koyu temada tablo hücrelerinin kontrastı 1,10:1 idi (okunamıyordu).
  Bootstrap'in `--bs-table-color` değişkeni markanın metin rengine
  bağlandı; ölçülen kontrast **14,48:1**
- `--cy-primary` ve `--cy-surface-2` CSS değişkenleri hiçbir yerde
  tanımlı değildi; tanımsız değişken sessizce başarısız olduğu için aktif
  menü rengi ve mobil alt çubuk renksiz kalıyordu
- "Son İşlemler" listesi `id DESC` ile sıralanıyordu; başlık zamana işaret
  ettiği hâlde sıra tarihle uyuşmuyordu. Artık `created_at DESC, id DESC`

[1.1.0]: https://github.com/CilginYazilim/rest-api-system/releases/tag/v1.1.0
[1.0.0]: https://github.com/CilginYazilim/rest-api-system/releases/tag/v1.0.0
