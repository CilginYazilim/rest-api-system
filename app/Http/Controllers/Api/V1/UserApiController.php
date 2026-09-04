<?php
/**
 * =====================================================================
 *  UserApiController (v1) – Kullanıcı kaynağının REST uç noktaları
 * ---------------------------------------------------------------------
 *  GET    /api/v1/users        → liste (sayfalanmış, filtreli)
 *  POST   /api/v1/users        → oluştur           (201 + Location)
 *  GET    /api/v1/users/{id}   → tek kayıt
 *  PATCH  /api/v1/users/{id}   → kısmi güncelle
 *  DELETE /api/v1/users/{id}   → sil               (204)
 *
 *  ---------------------------------------------------------------
 *  NEDEN "v1" KLASÖRÜ VAR?
 *  Yayınladığınız bir API'yi kullanan istemcileri artık siz
 *  güncelleyemezsiniz. Bir alanın adını değiştirmek, çalışan
 *  uygulamaları kırar. Sürümleme, "eskiyi bozmadan yeniyi sunma"
 *  imkânı verir: v1 çalışmaya devam ederken v2 yanında yaşar.
 *
 *  Sürümü ADRESTE taşımak (başlıkta değil) en anlaşılır yoldur:
 *  tarayıcıdan, curl'den, günlüklerden hangi sürümün kullanıldığı
 *  bakılır bakılmaz görülür.
 *
 *  ---------------------------------------------------------------
 *  BU SINIFTA PAROLA DÖNDÜRÜLMEZ, HASH BİLE.
 *  User::toApiArray() parola alanını hiç taşımaz; "unutmak" mümkün
 *  olmasın diye kısıtlama VARLIK sınıfına konmuştur.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Router;
use App\Core\Validator;
use App\Http\Controller;
use App\Models\User;

final class UserApiController extends Controller
{
    /* =================================================================
     *  LİSTE
     * ============================================================== */

    /**
     * GET /api/v1/users?q=ali&status=active&page=2&per=20
     *
     * SAYFALAMA BİR SEÇENEK DEĞİL, ZORUNLULUKTUR.
     * Sınırsız liste döndüren bir uç nokta, tablo büyüdüğü gün
     * sunucuyu da istemciyi de kilitler. Varsayılan 20, üst sınır
     * 100'dür ve beyaz listeden geçer.
     */
    public function index(Request $request): void
    {
        $search  = trim($request->input('q'));
        $perPage = Paginator::perPageFromRequest($request);
        $page    = Paginator::pageFromRequest($request);

        $activeOnly = match ($request->input('status')) {
            'active'  => true,
            'passive' => false,
            default   => null,
        };

        $total     = $this->users()->countAll($search, $activeOnly);
        $paginator = new Paginator($total, $page, $perPage);

        $rows = $this->users()->page(
            $paginator->offset(),
            $paginator->perPage(),
            $search,
            $activeOnly
        );

        ApiResponse::collection(
            array_map(static fn (User $user): array => $user->toApiArray(), $rows),
            $paginator,
            'api/v1/users',
            // Sayfalama bağlantıları bu filtreleri korumalı; aksi hâlde
            // "next" farklı bir listeye götürür (bkz. ApiResponse::collection).
            ['per' => $perPage, 'q' => $search, 'status' => $request->input('status')]
        );
    }

    /* =================================================================
     *  TEK KAYIT
     * ============================================================== */

    /**
     * GET /api/v1/users/{id}
     *
     * @param array<string,string> $params Router'ın çıkardığı yol parametreleri
     */
    public function show(Request $request, array $params = []): void
    {
        $user = $this->users()->find($this->id($params));

        if ($user === null) {
            ApiResponse::error('not_found', 'Kullanıcı bulunamadı.', 404);
        }

        ApiResponse::data($user->toApiArray());
    }

    /* =================================================================
     *  OLUŞTURMA
     * ============================================================== */

    /**
     * POST /api/v1/users
     *
     * Gövde (JSON veya form): name, surname, email, password, is_active
     */
    public function store(Request $request): void
    {
        $validator = new Validator($request->all());
        $validator->name('name', 'Ad')
                  ->name('surname', 'Soyad')
                  ->email('email')
                  ->password('password');

        if ($validator->fails()) {
            ApiResponse::validationFailed($validator->errors());
        }

        $data = $validator->validated();

        /* E-posta çakışmasını 422 DEĞİL 409 ile bildiriyoruz:
         * veri biçimsel olarak geçerlidir, sorun kaynağın MEVCUT
         * DURUMUYLA çakışmasıdır. İstemci bu ikisine farklı tepki
         * verir (biri formu düzeltmek, diğeri "zaten kayıtlı" demek). */
        if ($this->users()->emailTaken((string) $data['email'])) {
            ApiResponse::error('email_taken', 'Bu e-posta adresi zaten kayıtlı.', 409);
        }

        $id = $this->users()->create(
            (string) $data['name'],
            (string) $data['surname'],
            (string) $data['email'],
            (string) $data['password'],
            $request->has('is_active') ? $request->bool('is_active') : true
        );

        $user = $this->users()->find($id);

        /* 201 + Location: "oluşturdum, ŞU adreste" demek istemciye
         * fazladan bir arama isteği yaptırmaz. */
        ApiResponse::created(
            $user?->toApiArray() ?? ['id' => $id],
            Router::basePath() . '/api/v1/users/' . $id
        );
    }

    /* =================================================================
     *  GÜNCELLEME
     * ============================================================== */

    /**
     * PATCH /api/v1/users/{id}
     *
     * PATCH, PUT'tan farklıdır: yalnızca GÖNDERİLEN alanları değiştirir.
     * PUT kaynağın tamamını değiştirir ve eksik gönderilen alanları
     * siler — istemcinin unuttuğu bir alan veri kaybına yol açar.
     * Bu yüzden kısmi güncellemeyi PATCH ile sunuyoruz.
     *
     * @param array<string,string> $params
     */
    public function update(Request $request, array $params = []): void
    {
        $id   = $this->id($params);
        $user = $this->users()->find($id);

        if ($user === null) {
            ApiResponse::error('not_found', 'Kullanıcı bulunamadı.', 404);
        }

        $fields    = [];
        $validator = new Validator($request->all());

        /* has() ile "gönderilmedi" ile "boş gönderildi" ayrımını
         * koruyoruz. isset() yeterli olmazdı: null gönderilen bir alan
         * da "gönderilmiş" sayılmalıdır. */
        if ($request->has('name')) {
            $validator->name('name', 'Ad');
            $fields['name'] = $request->input('name');
        }

        if ($request->has('surname')) {
            $validator->name('surname', 'Soyad');
            $fields['surname'] = $request->input('surname');
        }

        if ($request->has('email')) {
            $validator->email('email');
            $fields['email'] = $request->input('email');
        }

        if ($request->has('is_active')) {
            $fields['is_active'] = $request->bool('is_active');
        }

        if ($validator->fails()) {
            ApiResponse::validationFailed($validator->errors());
        }

        if ($fields === []) {
            ApiResponse::error('nothing_to_update', 'Güncellenecek alan gönderilmedi.', 422);
        }

        if (isset($fields['email']) && $this->users()->emailTaken((string) $fields['email'], $id)) {
            ApiResponse::error('email_taken', 'Bu e-posta adresi başka bir hesapta kayıtlı.', 409);
        }

        $this->users()->update($id, $fields);

        ApiResponse::data($this->users()->find($id)?->toApiArray() ?? []);
    }

    /* =================================================================
     *  SİLME
     * ============================================================== */

    /**
     * DELETE /api/v1/users/{id}
     *
     * @param array<string,string> $params
     */
    public function destroy(Request $request, array $params = []): void
    {
        $id = $this->id($params);

        /* KENDİ HESABINI SİLDİRMEME KURALI
         * Jetonun sahibi kendi hesabını silerse, o jeton dahil her şey
         * ortadan kalkar ve geri dönüşü olmayan bir duruma düşer.
         * Bu tür "ayağına sıkma" senaryolarını kapıda durdurmak,
         * sonradan veri kurtarmaktan çok daha ucuzdur. */
        if (\App\Core\ApiAuth::user()?->id === $id) {
            ApiResponse::error('self_delete', 'Kendi hesabınızı bu uç noktadan silemezsiniz.', 403);
        }

        if (!$this->users()->delete($id)) {
            ApiResponse::error('not_found', 'Kullanıcı bulunamadı.', 404);
        }

        // 204: başarılı, gövde YOK.
        ApiResponse::noContent();
    }

    /* =================================================================
     *  YARDIMCI
     * ============================================================== */

    /**
     * Yol parametresini güvenli bir tamsayıya çevirir.
     *
     * Router yalnızca harf/rakam/tire geçirir; yine de "abc" gelebilir
     * ve (int) onu sessizce 0 yapar. 0 hiçbir kaydın ID'si olmadığı
     * için sorgu boş döner ve istemci düzgün bir 404 alır.
     *
     * @param array<string,string> $params
     */
    private function id(array $params): int
    {
        return (int) ($params['id'] ?? 0);
    }
}
