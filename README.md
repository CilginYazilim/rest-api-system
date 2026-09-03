<div align="center">

<img src="assets/images/logo.png" alt="Çılgın Yazılım" width="90">

# REST API Sistemi

### PHP 8 · PDO · MySQL · Jeton Yetkilendirme · Kapsam (Scope) · Hız Sınırı · Sayfalama · Çılgın Yazılım Tasarım Kalıbı

**Jetonu panelden üretin, kapsamını seçin, `curl` ile deneyin.**

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![REST](https://img.shields.io/badge/REST-JSON-0ea5e9?style=flat-square)](#api-referansı)
[![Composer](https://img.shields.io/badge/Composer-gerekmiyor-16a34a?style=flat-square)](#kurulum)
[![License](https://img.shields.io/badge/Lisans-MIT-16a34a?style=flat-square)](LICENSE)

**🇹🇷 Türkçe** · [🇬🇧 English](README.en.md)

[**▶ Canlı Demo**](https://cilginyazilim.com/kutuphane/uygulama/rest-api-system/) · [Kaynak Kütüphanesi](https://cilginyazilim.com/kutuphane/php-rest-api-panel) · [cilginyazilim.com](https://cilginyazilim.com)

</div>

---

<div align="center">

## Canlı Demo

**Kurulum yok, kayıt yok, indirme yok — tarayıcınızdan 3 saniyede deneyin.**

<a href="https://cilginyazilim.com/kutuphane/uygulama/rest-api-system/"><img src="https://img.shields.io/badge/CANLI_DEMOYU_A%C3%87-0b5cb5?style=for-the-badge&logo=googlechrome&logoColor=white&labelColor=061321" alt="Canlı Demoyu Aç" height="42"></a>
<a href="https://cilginyazilim.com/kutuphane/php-rest-api-panel"><img src="https://img.shields.io/badge/KAYNAK_KODU_%C4%B0NCELE-0ea5e9?style=for-the-badge&logo=readthedocs&logoColor=white&labelColor=061321" alt="Kaynak Kodu İncele" height="42"></a>
<a href="https://github.com/CilginYazilim/rest-api-system/archive/refs/heads/main.zip"><img src="https://img.shields.io/badge/ZIP_%C4%B0ND%C4%B0R-16a34a?style=for-the-badge&logo=github&logoColor=white&labelColor=061321" alt="ZIP İndir" height="42"></a>

<br><br>

<a href="https://cilginyazilim.com/kutuphane/uygulama/rest-api-system/" title="Canlı demoyu açmak için tıklayın">
  <img src="docs/screenshots/03-api-jetonlari.png" alt="REST API sistemi canlı demo önizlemesi" width="860">
</a>

<sub>▲ Görsele tıklayarak demoyu açabilirsiniz</sub>

</div>

<br>

### Demo hesapları

| Rol | E-posta | Parola |
|---|---|---|
| Yönetici | `admin@cilginyazilim.com` | `Admin1234` |
| Kullanıcı | `demo@cilginyazilim.com` | `Demo1234` |

### Demoda 60 saniyede neleri deneyebilirsiniz?

| # | Şunu deneyin | Perde arkasında ne oluyor? |
|---|---|---|
| **1** | **API Jetonları** sayfasında **"Jeton üret"** deyin, `read` kutusunu işaretleyin | Açık metin jeton **yalnızca bu bir kez** gösterilir. Veritabanında yalnızca SHA-256 özeti durur; sayfayı kapatırsanız jetonu bir daha kimse göremez |
| **2** | Jetonu kopyalayıp `curl` ile bir istek atın | `curl -H "Authorization: Bearer <jeton>" .../api/v1/users` — JSON döner |
| **3** | Yanıtın `meta` bölümüne bakın | Toplam kayıt, sayfa, sayfa boyutu ve toplam sayfa sayısı orada. İstemcinin sayfalamayı tahmin etmesi gerekmez |
| **4** | `links` bölümüne bakın | Bir sonraki sayfanın **tam adresi** hazır gelir. İstemci adres kurmaz, takip eder |
| **5** | `?per=200` deneyin | 200 listede olmadığı için istek **20'ye düşer**. Sayfa boyutu bir **beyaz listedir**; aksi hâlde tek istekle tüm tabloyu çekmek mümkün olurdu |
| **6** | Sadece `read` kapsamlı jetonla **POST** atmayı deneyin | `403` ve `insufficient_scope` döner. Kapsam kontrolü rotanın **ara katmanında** yapılır, denetleyicinin içinde unutulabilecek bir `if` değildir |
| **7** | Yanıt başlıklarına bakın (`curl -i`) | `X-RateLimit-Limit`, `X-RateLimit-Remaining` ve `X-RateLimit-Reset` gelir. İstemci sınıra çarpmadan önce yavaşlayabilir |
| **8** | Aynı jetonla 60'tan fazla istek atın | `429` ve `Retry-After` başlığı döner. Sayım **jeton başına** yapılır; başkasının trafiği sizi engellemez |
| **9** | Jetonun **"İptal et"** düğmesine basın, sonra tekrar istek atın | `401`. Jeton silinmez, `revoked_at` doldurulur — geçmiş istek kayıtları bağlı kaldığı için "hangi jeton ne yaptı" sorusu cevapsız kalmaz |
| **10** | **API Belgeleri** sayfasını açın | Bütün uç noktalar, kapsamları, hata kodları ve kopyalanabilir `curl` örnekleri orada |
| **11** | Aynı sayfadaki **Örnek Kullanım** bölümünden dilinizi seçin | cURL, PHP, JavaScript ve Python örnekleri **bu sunucunun gerçek adresiyle** basılır. **İndir** düğmesi çalışır bir dosya verir; içine jeton yazılmaz, yer tutucu konur |

> **İpucu:** Hata yanıtları da düzenlidir: her hatanın makine tarafından okunabilir bir `code` alanı (`invalid_token`, `insufficient_scope`, `validation_failed`, `rate_limit_exceeded`) ve insan tarafından okunabilir bir `message` alanı vardır.

### Demo alanı hakkında bilinmesi gerekenler

| Konu | Durum |
|---|---|
| **Veriler** | `database.sql` içindeki **51 kullanıcı + 3 örnek jeton + 28 istek kaydı**. Gerçek kişi verisi yoktur. |
| **Hazır jeton** | **Yoktur ve olmayacaktır.** Depoda duran bir jeton, projeyi indiren herkesin bildiği bir jeton demektir. Kendinizinkini panelden üretin. |
| **Sıfırlama** | Demo veritabanı **düzenli aralıklarla** başlangıç hâline döner; ürettiğiniz jeton kalıcı değildir. |
| **Hız sınırı** | Pencere başına **60 istek**. Örnek jetonların kayıtları sizin hakkınızı **yemez**; sayım jeton başınadır. |
| **`APP_DEBUG`** | Canlıda **kendiliğinden `false`** — sunucu adından türetilir. |
| **Bağımlılık** | **Sıfır.** Composer yok, npm yok, CDN yok. |

---

## Bu Proje Nedir?

"API yazalım" denince ortaya çoğu zaman şu çıkar: bir `api.php` dosyası, içinde `if ($_GET['action'] == 'kullanicilar')` ve sonunda `echo json_encode($rows)`. Çalışır — ta ki şu sorular gelene kadar:

- **Bu isteği kim yaptı?** Anahtar yoksa cevap yok.
- **Bu anahtar neyi yapabilir?** Okuyabilen her istemci silebiliyorsa, raporlama betiğiniz bir hatada veritabanınızı boşaltabilir.
- **Anahtar sızdı, ne yapacağım?** Anahtarı düz metin sakladıysanız veritabanı sızıntısı doğrudan hesap sızıntısıdır.
- **Bir istemci saniyede 300 istek atıyor**, diğerleri bekliyor.
- **10.000 kayıt döndürdüm**, yanıt 8 MB.

Bu proje bu beş soruyu birden cevaplayan bir API katmanı kuruyor. Jetonlar panelden üretilir, **kapsamla** sınırlanır (`read` / `write`), veritabanında **yalnızca özeti** durur, iptal edilebilir; her istek **jeton başına** hız sınırından geçer ve listeler her zaman **sayfalanır**.

Öne çıkan tarafı, API'nin tek başına değil, **onu yöneten bir panelle birlikte** gelmesidir: jetonu üretmek, kapsamını seçmek, son kullanımını görmek ve iptal etmek için `phpMyAdmin` açmanız gerekmez.

**Kimler için uygun?**

- Mobil uygulamasına veya başka bir servise API açacaklar
- Anahtarı düz metin saklamanın neden tehlikeli olduğunu ve alternatifini öğrenmek isteyenler
- Kapsam (scope), hız sınırı ve sayfalama kalıplarını doğru kurmak isteyenler
- JWT'ye ihtiyaç duymayan, sunucu tarafında iptal edilebilir jeton isteyenler
- Bootstrap 5 üzerine kurulu, tekrar kullanılabilir bir panel kalıbı arayanlar

Bu proje, **[Çılgın Yazılım Kütüphanesi](https://cilginyazilim.com/kutuphane)** altında yayınlanan açıklamalı, üretime hazır örneklerden biridir.

---

## İçindekiler

- [Canlı Demo](#canlı-demo)
- [Bu Proje Nedir?](#bu-proje-nedir)
- [Ekran Görüntüleri](#ekran-görüntüleri)
- [Hızlı başlangıç](#hızlı-başlangıç)
- [Kritik Kararlar](#kritik-kararlar)
- [Neler Var?](#neler-var)
- [API Referansı](#api-referansı)
- [Hata kodları](#hata-kodları)
- [Güvenlik: Neyi, Nasıl Kapattık?](#güvenlik-neyi-nasıl-kapattık)
- [Kurulum](#kurulum)
- [Yapılandırma](#yapılandırma)
- [Dosya Yapısı](#dosya-yapısı)
- [Nasıl Çalışıyor?](#nasıl-çalışıyor)
- [Veritabanı Şeması](#veritabanı-şeması)
- [SSS](#sss)
- [Canlı Ortama Alırken](#canlı-ortama-alırken)
- [Sorun Giderme](#sorun-giderme)
- [Yol Haritası](#yol-haritası)
- [Katkı](#katkı)
- [Lisans](#lisans)

---

## Ekran Görüntüleri

| Giriş | Kontrol Paneli |
|---|---|
| <img src="docs/screenshots/01-giris.png" width="420" alt="Giriş ekranı"> | <img src="docs/screenshots/02-kontrol-paneli.png" width="420" alt="Kontrol paneli"> |

| API Jetonları | API Belgeleri |
|---|---|
| <img src="docs/screenshots/03-api-jetonlari.png" width="420" alt="API jetonları"> | <img src="docs/screenshots/04-api-belgeleri.png" width="420" alt="API belgeleri"> |

<div align="center">
<img src="docs/screenshots/05-koyu-tema.png" width="720" alt="Koyu tema">
<br><sub>Koyu tema</sub>
<br><br>
<img src="docs/screenshots/06-mobil.png" width="300" alt="Mobil görünüm">
<br><sub>390px genişlikte mobil görünüm</sub>
</div>

---

## Hızlı başlangıç

**1 · Jeton üretin.** Panele girin → **API Jetonları** → "Jeton üret". Kapsamı seçin (`read`, `write` veya ikisi).

Açık metin jeton **yalnızca o an** gösterilir:

```
cy_9f2b7c1d5e83a04f6b91c2d7e0a5f38b4c6d9e2a1f7b30c8d5e4a9b6c3f1d827
```

**2 · İstek atın.**

```bash
curl -H "Authorization: Bearer cy_9f2b..." \
     "http://localhost/rest-api-system/api/v1/users?per=10&page=2"
```

**3 · Yanıtı okuyun.**

```json
{
  "data": [
    { "id": 11, "name": "Fatma", "surname": "YILDIZ",
      "email": "fatma.yildiz@ornek.com", "is_active": false,
      "created_at": "2025-01-15T23:58:29+03:00" }
  ],
  "meta": {
    "total": 51,
    "per_page": 10,
    "current_page": 2,
    "last_page": 6,
    "from": 11,
    "to": 20,
    "has_more": true
  },
  "links": {
    "self": "http://localhost/rest-api-system/api/v1/users?page=2&per=10",
    "next": "http://localhost/rest-api-system/api/v1/users?page=3&per=10",
    "prev": "http://localhost/rest-api-system/api/v1/users?page=1&per=10"
  }
}
```

**4 · Başlıklara bakın.**

```bash
curl -i -H "Authorization: Bearer cy_9f2b..." .../api/v1/users
```

```http
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 57
X-RateLimit-Reset: 1757012400
```

---

## Kritik Kararlar

### 1. Jeton veritabanında **düz metin** durmaz

Bir API anahtarı, bir paroladır. Düz metin saklarsanız veritabanı sızıntısı doğrudan hesap sızıntısına dönüşür — üstelik jeton parola gibi kullanıcı tarafından değiştirilmez, aylarca aynı kalır.

```php
// app/Repositories/ApiTokenRepository.php
private static function hash(string $plain): string
{
    return hash('sha256', $plain);
}
```

Tabloda yalnızca `token_hash` durur. Doğrulama, gelen jetonun özetini alıp **özete göre** arama yapar.

**Neden `password_hash()` değil, SHA-256?** İkisi farklı işler içindir. `password_hash()` bilerek **yavaştır** (bcrypt), çünkü paroları sözlük saldırısına karşı korur — insanlar `123456` seçer. API jetonu ise 32 bayt **rastgeledir**; sözlük saldırısına konu değildir, tuza ihtiyacı yoktur. Buna karşılık her API isteğinde doğrulanır: bcrypt kullanmak her isteğe 100 ms eklerdi. SHA-256 hem yeterli hem hızlıdır.

### 2. Kapsam kontrolü **rotada** yapılır, denetleyicide değil

```php
// routes/web.php
$router->get('api/v1/users',        UserApiController::class, 'index',  ['api', 'scope:read']);
$router->post('api/v1/users',       UserApiController::class, 'store',  ['api', 'scope:write']);
$router->delete('api/v1/users/{id}',UserApiController::class,'destroy', ['api', 'scope:write']);
```

Kapsam, denetleyicinin ilk satırına yazılan bir `if` olsaydı, yeni bir uç nokta ekleyen kişi bir gün onu yazmayı unuturdu — ve bu unutma **sessizce** açık bırakırdı.

Rota tablosunda ise yetki, uç noktanın **tanımının parçasıdır**. Kimin neye erişebildiğini görmek için tek bir dosyaya bakmak yeterlidir.

### 3. Hız sınırı **jeton başına**, IP başına değil

IP başına sınır iki yönden de yanlış çalışır: aynı ofisten çıkan yüz kullanıcı tek IP'dedir (haksız yere engellenirler), tek bir saldırgan ise binlerce IP kullanabilir (hiç engellenmez).

Jeton başına sayım, sınırı **kimliğe** bağlar. Sizin trafiğiniz başkasının hakkını yemez.

```sql
SELECT COUNT(*) FROM api_requests
 WHERE token_id = :id AND requested_at >= (NOW() - INTERVAL :window SECOND)
```

Üç başlık her yanıtta gönderilir (`X-RateLimit-Limit`, `-Remaining`, `-Reset`), böylece iyi niyetli istemci sınıra **çarpmadan önce** yavaşlayabilir. Sınır aşılınca `429` ve `Retry-After` döner.

Bir saatten eski satırlar kendiliğinden temizlenir; tablo sonsuza kadar büyümez.

### 4. İptal edilen jeton **silinmez**

```php
UPDATE api_tokens SET revoked_at = NOW() WHERE id = :id
```

Satır silinseydi ona bağlı `api_requests` kayıtları da giderdi (`ON DELETE CASCADE`) ve "geçen ay hangi jeton ne yaptı?" sorusu cevapsız kalırdı. Bir güvenlik olayından sonra tam da o soruyu sormak istersiniz.

Doğrulama `revoked_at IS NULL` koşulunu arar; iptal edilmiş jeton anında geçersizdir.

### 5. Sayfa boyutu beyaz listeden geçer

```php
public const PER_PAGE_OPTIONS = [10, 20, 50, 100];
public const DEFAULT_PER_PAGE = 20;
```

`?per=100000` yazan bir istemci — çoğu zaman kötü niyetle değil, dikkatsizlikle — tek istekte tüm tabloyu ister. Bu, sunucunun belleğini de yanıtın boyutunu da patlatır.

**Bu bir tavan değil, beyaz listedir** ve fark önemlidir. Tavan olsaydı `per=100000` sessizce 100'e çekilirdi; istemci yanlış yazdığını hiç fark etmezdi. Beyaz listede, listede olmayan HER değer varsayılana (`20`) döner:

| İstenen | Dönen |
|---|---|
| `10` · `20` · `50` · `100` | aynısı |
| `3` | `20` — küçük değerler de listede değil |
| `250` · `100000` · `abc` | `20` |

Tek satırlık bir `in_array` kontrolü, "acaba bu sayı çok mu büyük?" diye düşünmek zorunda kalmadan bütün uç durumları kapatır. İstemcinin iyi niyetine bırakılmaz.

### 6. Yanıt zarfı: `data` + `meta` + `links`

Dizi doğrudan döndürülseydi (`[{...},{...}]`), sayfalama bilgisi eklenecek yer kalmazdı ve bir gün eklemek **kırıcı bir değişiklik** olurdu.

`data` içeriği, `meta` sayımı, `links` gezinmeyi taşır. İstemci bir sonraki sayfanın adresini kurmaz, `links.next`'i takip eder — sayfalama biçimini sonradan değiştirseniz bile istemci çalışmaya devam eder.

### 7. Hataların da makine tarafından okunabilir bir kodu var

```json
{
  "error": {
    "code": "insufficient_scope",
    "message": "Bu işlem için 'write' kapsamı gerekiyor.",
    "details": { "required_scope": "write", "granted": ["read"] }
  }
}
```

İstemci `message` metnini karşılaştırmaz — metin dile ve sürüme göre değişir. `code` sabittir ve programla ele alınabilir.

### 8. `WWW-Authenticate` gönderirken durum kodu sırası önemlidir

PHP, `WWW-Authenticate` başlığını görünce durum kodunu kendiliğinden `401`'e çevirir. `403` dönmek istediğiniz bir yerde bu başlığı önce yazarsanız yanıt sessizce `401` olur.

Bu yüzden `http_response_code()` **başlıklardan sonra** çağrılır. (Aynı davranış `Location:` başlığı için de geçerlidir; orada da kod sessizce `302` olur.)

---

## Neler Var?

<table>
<tr><td valign="top" width="50%">

**API katmanı**

- Bearer jeton doğrulama
- Kapsam: `read` / `write`, rota düzeyinde
- Jeton başına hız sınırı + üç bilgi başlığı
- Sayfalama: `meta` + `links`, beyaz listeli `per`
- Tutarlı hata zarfı (`code` · `message` · `details`)
- Doğru HTTP kodları: 200 · 201 · 204 · 400 · 401 · 403 · 404 · 405 · 422 · 429
- `201` ile birlikte `Location` başlığı
- ISO-8601 tarihler, zaman dilimi bilgisiyle

</td><td valign="top" width="50%">

**Panel**

- Jeton üretme, kapsam seçimi
- Açık metin **yalnızca bir kez** gösterilir
- Son kullanım tarihi ve IP
- Ömür boyu istek sayacı (Detaylar modalında)
- İptal etme (silmeden)
- API belgeleri sayfası, kopyalanabilir `curl` örnekleri
- **Örnek Kullanım**: cURL · PHP · JavaScript · Python
- Örnek dosyayı indirme (jeton yerine yer tutucu)

**Ortak altyapı**

- Oturum girişi, "beni hatırla", hız sınırı, CSRF
- CSP (`script-src 'self'`), `X-Frame-Options: DENY`
- Açık / koyu tema, hesaba kayıtlı
- Mobilde alt navigasyon, yatay kaydırma yok
- Sıfır bağımlılık

</td></tr>
</table>

---

## API Referansı

Bütün uç noktalar `Authorization: Bearer <jeton>` başlığı ister.

<details>
<summary><b>GET /api/v1/users</b> — kullanıcıları listele · kapsam: <code>read</code></summary>

**Sorgu parametreleri**

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| `page` | int | `1` | Sayfa numarası |
| `per` | int | `20` | Sayfa boyutu. Yalnızca `10, 20, 50, 100` kabul edilir; **başka her değer `20`'ye döner** |

**Örnek**

```bash
curl -H "Authorization: Bearer cy_..." \
     "http://localhost/rest-api-system/api/v1/users?page=2&per=10"
```

**Yanıt · 200**

```json
{
  "data": [ { "id": 11, "name": "Fatma", "surname": "YILDIZ",
              "email": "fatma.yildiz@ornek.com", "is_active": false,
              "created_at": "2025-01-15T23:58:29+03:00" } ],
  "meta":  { "total": 51, "per_page": 10, "current_page": 2, "last_page": 6, "from": 11, "to": 20, "has_more": true },
  "links": { "self": "...page=2&per=10",
             "next": "...page=3&per=10",
             "prev": "...page=1&per=10" }
}
```

</details>

<details>
<summary><b>GET /api/v1/users/{id}</b> — tek kullanıcı · kapsam: <code>read</code></summary>

```bash
curl -H "Authorization: Bearer cy_..." \
     "http://localhost/rest-api-system/api/v1/users/11"
```

**Yanıt · 200**

```json
{ "data": { "id": 11, "name": "Fatma", "surname": "YILDIZ",
            "email": "fatma.yildiz@ornek.com", "is_active": false,
            "created_at": "2025-01-15T23:58:29+03:00" } }
```

Bulunamazsa **404** ve `not_found` kodu döner.

</details>

<details>
<summary><b>POST /api/v1/users</b> — kullanıcı oluştur · kapsam: <code>write</code></summary>

```bash
curl -X POST \
     -H "Authorization: Bearer cy_..." \
     -H "Content-Type: application/json" \
     -d '{"name":"Ayse","surname":"Yilmaz","email":"ayse@ornek.com","password":"Gizli1234"}' \
     "http://localhost/rest-api-system/api/v1/users"
```

**Yanıt · 201** — `Location` başlığı yeni kaydın adresini taşır.

```json
{ "data": { "id": 52, "name": "Ayse", "surname": "Yilmaz",
            "email": "ayse@ornek.com", "is_active": true,
            "created_at": "2026-09-03T04:12:00+03:00" } }
```

**Doğrulama hatası · 422**

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Gönderilen veri geçersiz.",
    "details": {
      "email": ["Bu e-posta zaten kayıtlı."],
      "password": ["En az 8 karakter olmalı."]
    }
  }
}
```

Hata **alan bazlıdır**: istemci hangi kutuyu kırmızı yapacağını bilir.

</details>

<details>
<summary><b>PATCH /api/v1/users/{id}</b> — kullanıcı güncelle · kapsam: <code>write</code></summary>

Yalnızca gönderdiğiniz alanlar değişir (kısmi güncelleme).

```bash
curl -X PATCH \
     -H "Authorization: Bearer cy_..." \
     -H "Content-Type: application/json" \
     -d '{"is_active":false}' \
     "http://localhost/rest-api-system/api/v1/users/11"
```

**Yanıt · 200** — güncellenmiş kaydın tamamı döner.

</details>

<details>
<summary><b>DELETE /api/v1/users/{id}</b> — kullanıcı sil · kapsam: <code>write</code></summary>

```bash
curl -X DELETE -H "Authorization: Bearer cy_..." \
     "http://localhost/rest-api-system/api/v1/users/52"
```

**Yanıt · 204** — gövde yoktur. Silme işleminde döndürülecek bir kayıt kalmadığı için `204 No Content` doğru koddur.

</details>

---

## Hata kodları

| HTTP | `code` | Ne zaman |
|---|---|---|
| 400 | `invalid_json` | Gövde JSON olarak ayrıştırılamadı |
| 401 | `unauthenticated` | `Authorization` başlığı yok veya biçimi hatalı |
| 401 | `invalid_token` | Jeton bulunamadı, iptal edilmiş veya hesap pasif |
| 403 | `insufficient_scope` | Jetonun bu işlem için kapsamı yok |
| 403 | `self_delete` | Kendi hesabınızı API üzerinden silemezsiniz |
| 404 | `not_found` | Kayıt veya uç nokta yok |
| 405 | `method_not_allowed` | Adres var ama bu HTTP metodu tanımlı değil |
| 409 | `email_taken` | Bu e-posta başka bir hesapta kayıtlı |
| 422 | `validation_failed` | Alan bazlı doğrulama hatası (`details` içinde) |
| 422 | `nothing_to_update` | PATCH gövdesinde güncellenecek alan yok |
| 429 | `rate_limit_exceeded` | Hız sınırı aşıldı (`Retry-After` başlığıyla) |
| 500 | `server_error` | Beklenmeyen hata — ayrıntı **istemciye gönderilmez**, log'a yazılır |

---

## Güvenlik: Neyi, Nasıl Kapattık?

| Açık | Tipik hatalı kod | Bu projede |
|---|---|---|
| **Jeton sızıntısı** | Jetonu düz metin saklamak | Yalnızca **SHA-256 özeti** saklanır; açık metin bir kez gösterilir |
| **Yetki aşımı** | Tek anahtarla her şeyi yapabilmek | **Kapsam** (`read`/`write`), rota düzeyinde zorunlu |
| **İptal edilemeyen anahtar** | Süresiz JWT | Jeton sunucuda tutulur; `revoked_at` ile **anında** geçersiz olur |
| **Zamanlama saldırısı** | `if ($hash == $gelen)` | Aramanın kendisi özet üzerinden yapılır; eşitlik karşılaştırmaları `hash_equals()` ile |
| **SQL enjeksiyonu** | `"... WHERE id = $id"` | Tüm sorgular hazır ifade; `ATTR_EMULATE_PREPARES = false` |
| **Aşırı veri çekme** | `?per=100000` | Sayfa boyutu **beyaz listeden** geçer; liste dışı değer `20`'ye döner |
| **Kaynak tüketimi** | Sınırsız istek | Jeton başına hız sınırı + `429` + `Retry-After` |
| **Hata sızıntısı** | İstisna mesajını JSON'a basmak | `500` yanıtı ayrıntı taşımaz; ayrıntı log'a yazılır |
| **Yanlış durum kodu** | `WWW-Authenticate`'ten sonra `403` denemek | `http_response_code()` **başlıklardan sonra** çağrılır |
| **Sabit yol kırılması** | `.htaccess` içinde `RewriteBase /api` | Sabit yol **yazılmadı**; Apache tabanı dizinden türetir |
| **Kullanıcı sayımı** | "Böyle bir e-posta yok" | Girişte hem yanlış e-postada hem yanlış parolada **aynı** mesaj ve **aynı** süre |
| **Bozuk UTF-8'de sessiz JSON kaybı** | `json_encode($v)` | `JSON_INVALID_UTF8_SUBSTITUTE` |

---

## Kurulum

### Gereksinimler

| | |
|---|---|
| PHP | 8.0 veya üzeri |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Web sunucusu | Apache (`mod_rewrite`) veya Nginx |
| PHP eklentileri | `pdo_mysql`, `mbstring` |

### Adımlar

```bash
git clone https://github.com/CilginYazilim/rest-api-system.git
cd rest-api-system

mysql -u root -p < database.sql
cp .env.example .env        # Windows: copy .env.example .env
```

Açın: `http://localhost/rest-api-system/` · Giriş: `admin@cilginyazilim.com` / `Admin1234`

Sonra **API Jetonları** sayfasından kendi jetonunuzu üretin.

> **Apache'de `Authorization` başlığı kaybolabilir.** Apache, CGI/FastCGI modunda bu başlığı güvenlik gerekçesiyle PHP'ye geçirmez; sonuç, jeton doğru olsa bile her isteğin `401` dönmesidir. Proje `.htaccess` dosyası başlığı bir ortam değişkenine kopyalayarak bunu çözer:

```apache
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

PHP bunu `$_SERVER['HTTP_AUTHORIZATION']` olarak görür. Nginx + PHP-FPM kullanıyorsanız karşılığı:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

---

## Yapılandırma

```env
APP_DEBUG=true          # silerseniz: yerelde açık, canlıda kapalı
APP_URL=
APP_PRETTY_URLS=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=cy_rest_api
DB_USER=root
DB_PASS=
```

API davranışını belirleyen değerler koddadır:

| Değer | Yeri | Varsayılan | Ne yapar |
|---|---|---|---|
| hız sınırı | `ApiRateLimiter::__construct` | `60` | Pencere içinde izin verilen istek |
| pencere | `ApiRateLimiter::__construct` | `60` sn | Sayım penceresi |
| sayfa boyutu | `Paginator::DEFAULT_PER_PAGE` | `20` | `per` verilmezse |
| izin verilen sayfa boyutları | `Paginator::PER_PAGE_OPTIONS` | `10, 20, 50, 100` | Liste dışı her değer `DEFAULT_PER_PAGE`'e döner |
| kapsamlar | `ApiToken::SCOPES` | `read`, `write` | Tanımlı kapsam listesi |

---

## Dosya Yapısı

```
rest-api-system/
│
├── index.php                      Ön denetleyici — TEK giriş noktası
├── database.sql                   Şema + 51 kullanıcı + 3 jeton + istek kayıtları
├── .env.example
│
├── app/
│   ├── Core/
│   │   ├── ApiAuth.php            ★ Bearer doğrulama · requireScope()
│   │   ├── ApiRateLimiter.php     ★ Jeton başına sayım · X-RateLimit-* başlıkları
│   │   ├── ApiResponse.php        ★ data/meta/links zarfı · hata zarfı · HTTP kodları
│   │   ├── Paginator.php          Sayfalama ve beyaz liste
│   │   ├── Middleware.php         'api' ve 'scope:read|write' ara katmanları
│   │   ├── Auth.php · Session.php · Csrf.php · RateLimiter.php
│   │   ├── Database.php           PDO (EMULATE_PREPARES = false)
│   │   ├── Env.php                .env okuyucu + isLocalHost()
│   │   └── ...
│   │
│   ├── Http/Controllers/
│   │   ├── Api/V1/UserApiController.php   ★ index · show · store · update · destroy
│   │   ├── Api/PreferenceApiController.php
│   │   ├── TokenController.php    Jeton üret / iptal et
│   │   ├── ApiDocController.php   API belgeleri sayfası
│   │   └── Auth · Dashboard · User
│   │
│   ├── Models/ApiToken.php        SCOPE_READ · SCOPE_WRITE
│   ├── Repositories/ApiTokenRepository.php  ★ create() · findByPlain() · revoke()
│   └── Support/helpers.php
│
├── views/                         Düzenler, jeton ve belge sayfaları
├── assets/                        css · js · images
├── config/config.php
│   ├── Support/ApiExamples.php    ★ Örnek kullanım kodlarını üretir
├── routes/web.php                 ★ Kapsamlar burada tanımlı
└── docs/                          index.html · screenshots/
```

---

## Nasıl Çalışıyor?

```
İstemci
   │  GET /api/v1/users?per=10&page=2
   │  Authorization: Bearer cy_9f2b...
   ▼
index.php  →  Router::dispatch()
   │
   ▼
Rota bulundu: ['api', 'scope:read']
   │
   ├──────────── ARA KATMAN: api ─────────────────────────────┐
   │  ApiAuth::authenticate()                                 │
   │    1. Authorization başlığı var mı?  yoksa → 401         │
   │    2. hash('sha256', $jeton)                             │
   │    3. SELECT ... WHERE token_hash = ? AND revoked_at IS NULL
   │       bulunamadı / hesap pasif → 401 invalid_token       │
   │    4. last_used_at, last_used_ip güncellenir,           │
   │       request_count += 1 (atomik)                        │
   │                                                          │
   │  ApiRateLimiter::check($tokenId, $ip)                    │
   │    SELECT COUNT(*) FROM api_requests                     │
   │     WHERE token_id = ? AND requested_at >= NOW()-60sn    │
   │       sayı >= 60 → 429 + Retry-After                     │
   │    INSERT INTO api_requests (...)                        │
   │    X-RateLimit-Limit / -Remaining / -Reset başlıkları    │
   └──────────────────────────────────────────────────────────┘
   │
   ├──────────── ARA KATMAN: scope:read ──────────────────────┐
   │  ApiAuth::requireScope('read')                           │
   │    jetonun kapsamları arasında yoksa → 403               │
   │      insufficient_scope + details{required, granted}     │
   └──────────────────────────────────────────────────────────┘
   │
   ▼
UserApiController::index()
   │   Paginator: per beyaz listeden gecer (10/20/50/100)
   │   UserRepository::page($offset, $limit)
   ▼
ApiResponse::collection($items, $paginator, 'api/v1/users')
   │
   ▼
{ "data": [...], "meta": {...}, "links": {...} }
   json_encode(..., JSON_INVALID_UTF8_SUBSTITUTE)
```

---

## Veritabanı Şeması

### `api_tokens`

| Sütun | Tip | İşi |
|---|---|---|
| `id` | INT UNSIGNED | Birincil anahtar |
| `user_id` | INT UNSIGNED | Jeton kimin adına çalışıyor (`ON DELETE CASCADE`) |
| `name` | VARCHAR(100) | "Mobil uygulama" gibi tanıtıcı ad |
| `token_hash` | CHAR(64) | **SHA-256 özeti** — açık metin hiçbir yerde saklanmaz |
| `scopes` | VARCHAR(100) | Virgülle ayrılmış kapsamlar (`read,write`) |
| `last_used_at` · `last_used_ip` | DATETIME · VARCHAR(45) | Son kullanım — şüpheli hareketi görmenin en kolay yolu |
| `request_count` | INT UNSIGNED | **Ömür boyu** istek sayısı. `api_requests`'ten değil bu sütundan okunur — o tablo yalnızca hız sınırı penceresidir ve bir saat sonra silinir (aşağıya bakın); toplamı oradan üretmek zamanla küçülen, yanlış bir sayı verirdi. Her istekte `request_count = request_count + 1` ile **atomik** artırılır |
| `revoked_at` | DATETIME | Dolu ise jeton geçersiz; satır **silinmez** |
| `created_at` | DATETIME | Üretilme anı |

### `api_requests` — hız sınırı penceresi

| Sütun | Tip | İşi |
|---|---|---|
| `id` | BIGINT UNSIGNED | Birincil anahtar |
| `token_id` | INT UNSIGNED | Hangi jeton (`ON DELETE CASCADE`) |
| `ip` | VARCHAR(45) | İsteği yapan adres (IPv6 için 45 karakter) |
| `requested_at` | DATETIME | İstek anı (indeksli) |

| Karar | Neden |
|---|---|
| Sayım **jeton başına** | IP başına sayım aynı ofisteki yüz kullanıcıyı haksız yere engeller, IP değiştiren saldırganı ise hiç engellemez |
| Bir saatten eski satırlar silinir | Tablo hız sınırı penceresidir, arşiv değildir; sonsuza kadar büyümemeli |
| `revoked_at`, satır silme yerine | Silinen jetonun istek kayıtları da giderdi; olay incelemesinde tam o kayıtlara bakılır |
| `scopes` metin sütunu, ayrı tablo değil | Kapsam sayısı iki; ilişki tablosu her istekte fazladan bir `JOIN` maliyeti demek olurdu |
| `token_hash` `CHAR(64)` | SHA-256 çıktısı her zaman 64 onaltılık karakterdir; sabit uzunluk hem küçük hem hızlıdır |

---

## SSS

<details>
<summary><b>Neden JWT kullanmadınız?</b></summary>

JWT'nin asıl faydası **durumsuz** olmasıdır: sunucu jetonu saklamaz, imzasını doğrular ve geçer. Bu, birden çok servise dağılmış mimarilerde çok değerlidir.

Ama aynı özellik en büyük dezavantajını doğurur: **iptal edilemez**. Sızan bir JWT, süresi dolana kadar geçerlidir. Bunu çözmek için bir "iptal listesi" tutarsınız — ve o an durumsuzluğu kaybedersiniz.

Tek uygulamalı bir sistemde jetonu veritabanında tutmanın maliyeti bir indeksli sorgudur; karşılığında **anında iptal**, kapsam yönetimi ve son kullanım bilgisi elde edersiniz. Bu değiş tokuş burada mantıklıdır.

JWT örneği isterseniz kütüphanedeki [JWT ile REST API](https://cilginyazilim.com/kutuphane/php-rest-api-jwt) örneğine bakın.
</details>

<details>
<summary><b>Jetonu kaybettim, nereden görebilirim?</b></summary>

Göremezsiniz — bu bilerek böyledir. Veritabanında yalnızca özet vardır ve özetten açık metni geri üretmek mümkün değildir.

Yeni bir jeton üretin ve eskisini iptal edin. Bu, "jetonumu unuttum" durumunun sızıntıdan ayırt edilemediği her sistemde doğru davranıştır.
</details>

<details>
<summary><b>Her istek `401` dönüyor, jeton doğru</b></summary>

Neredeyse her zaman `Authorization` başlığının PHP'ye ulaşmamasıdır. Apache bazı yapılandırmalarda bu başlığı düşürür.

Proje `.htaccess` dosyası başlığı `RewriteRule ... [E=HTTP_AUTHORIZATION:...]` ile bir ortam değişkenine kopyalayarak çözer. Kendi sunucunuzda dosyanın okunduğundan emin olun (`AllowOverride All`). Nginx + PHP-FPM için:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

Doğrulamak için başlığı geçici olarak yazdırın: `var_dump($_SERVER['HTTP_AUTHORIZATION'] ?? 'YOK');`
</details>

<details>
<summary><b>Hız sınırını nasıl değiştiririm?</b></summary>

`ApiRateLimiter` yapıcısındaki iki değeri değiştirin: `$limit` (istek sayısı) ve `$window` (saniye).

Farklı jetonlara farklı sınır vermek isterseniz `api_tokens` tablosuna bir `rate_limit` sütunu ekleyin ve sınırlayıcıya o değeri geçirin — kodun geri kalanı değişmez.
</details>

<details>
<summary><b>Yeni bir uç nokta nasıl eklerim?</b></summary>

İki adım:

1. Denetleyiciye metodu yazın (`app/Http/Controllers/Api/V1/`).
2. Rotayı **kapsamıyla birlikte** tanımlayın:

```php
$router->get('api/v1/siparisler', SiparisApiController::class, 'index', ['api', 'scope:read']);
```

Kapsamı yazmayı unutursanız uç nokta jeton ister ama **kapsam denetlemez**. Rota tablosunu gözden geçirirken bu göze çarpar — kapsamı denetleyicinin içine gizlemek yerine rotada tutmanın sebebi tam olarak budur.

**Dikkat:** sabit yollar (`api/v1/users`) desenli yollardan (`api/v1/users/{id}`) **önce** tanımlanmalıdır.
</details>

<details>
<summary><b>API sürümünü nasıl yükseltirim?</b></summary>

`api/v2/...` rotalarını ekleyin ve denetleyicileri `Api/V2/` altına koyun. `v1` çalışmaya devam eder.

Sürüm numarasını adrese koymanın sebebi budur: istemciler kendi hızlarında geçer. `v1`'i kapatacağınız tarihi önceden duyurun ve `api_tokens.last_used_at` ile kimin hâlâ eski sürümü kullandığını görün.
</details>

---

## Canlı Ortama Alırken

- [ ] `.env` içinde `APP_DEBUG=false` (veya satırı tümüyle silin)
- [ ] **HTTPS zorunlu olsun** — Bearer jeton düz HTTP'de açıkça taşınır
- [ ] Hız sınırını trafiğinize göre ayarlayın
- [ ] `Authorization` başlığının PHP'ye ulaştığını doğrulayın
- [ ] Demo jetonlarını iptal edin veya silin
- [ ] `api_requests` tablosunun temizlendiğini doğrulayın (bir saatlik pencere)
- [ ] Veritabanı için **root olmayan** bir kullanıcı açın
- [ ] `config/`, `app/`, `routes/`, `views/` klasörlerinin `.htaccess` dosyaları yerinde mi?
- [ ] Demo hesaplarının parolalarını değiştirin veya hesapları silin

---

## Sorun Giderme

| Belirti | Sebep | Çözüm |
|---|---|---|
| Her istek `401` | `Authorization` başlığı PHP'ye ulaşmıyor | `CGIPassAuth On` / `fastcgi_param HTTP_AUTHORIZATION` |
| `403 insufficient_scope` | Jetonun kapsamı yetmiyor | `write` kapsamlı yeni jeton üretin |
| `429` sürekli geliyor | Hız sınırı aşıldı | `Retry-After` kadar bekleyin veya sınırı yükseltin |
| `404` — uç nokta var ama bulunmuyor | Desenli rota sabit rotadan önce tanımlanmış | `routes/web.php` içinde sırayı düzeltin |
| `405 method_not_allowed` | Adres doğru, metot tanımlı değil | Rota tablosunda o metot var mı bakın |
| Tarihler saatsiz geliyor | Zaman dilimi ayarı | `config/config.php` → `app.timezone` |
| Türkçe karakterler bozuk | Bağlantı karakter kümesi | `Database.php` içinde `charset=utf8mb4` olduğunu doğrulayın |
| Tüm adresler 404 | `mod_rewrite` kapalı | Açın veya `APP_PRETTY_URLS=false` yapın |

---

## Yol Haritası

- [ ] Jeton başına ayarlanabilir hız sınırı
- [ ] Jetona son kullanma tarihi (`expires_at`)
- [ ] `If-None-Match` / `ETag` ile koşullu istek
- [ ] Webhook giden çağrıları (imzalı)
- [ ] OpenAPI (Swagger) tanım dosyası üretimi

---

## Katkı

Hata bildirimi ve öneriler için [issue açabilirsiniz](https://github.com/CilginYazilim/rest-api-system/issues).

## Lisans

[MIT](LICENSE) — ticari projelerinizde de özgürce kullanabilirsiniz.

---

<div align="center">

**[Çılgın Yazılım](https://cilginyazilim.com)** · [Kaynak Kütüphanesi](https://cilginyazilim.com/kutuphane) · [Tüm Örnekler](https://github.com/CilginYazilim)

</div>
