<?php
/**
 * =====================================================================
 *  UserController – Sayfalanmış kullanıcı listesi
 * ---------------------------------------------------------------------
 *  BU DOSYA SAYFALAMANIN ÖRNEK UYGULAMASIDIR. Akış her listede aynıdır
 *  ve dört adımdan oluşur:
 *
 *      1. İstekten sayfa ve filtreleri OKU (hepsi doğrulanarak)
 *      2. Filtreye uyan TOPLAM kaydı say   → kaç sayfa var?
 *      3. Paginator'ı kur                  → LIMIT/OFFSET hesapla
 *      4. Yalnızca o sayfanın satırlarını çek
 *
 *  Sıra önemlidir: Paginator'ı toplam sayıyı bilmeden kuramazsınız,
 *  çünkü "son sayfanın ötesine geçme" düzeltmesini o sayı belirler.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Paginator;
use App\Core\Request;
use App\Http\Controller;

final class UserController extends Controller
{
    public function index(Request $request): void
    {
        /* --- 1) İstekten oku -----------------------------------------
         * Arama metnini kırpıyoruz; kullanıcının kopyala-yapıştır
         * sırasında getirdiği boşluk yüzünden "sonuç yok" görmesin. */
        $search  = trim($request->input('q'));
        $perPage = Paginator::perPageFromRequest($request);
        $page    = Paginator::pageFromRequest($request);

        /* "durum" filtresi üç değerlidir: hepsi / aktif / pasif.
         * null = filtre yok. Beyaz liste dışındaki her değer null'a
         * düşer; uydurma bir parametre sorguyu etkileyemez. */
        $status     = $request->input('status');
        $activeOnly = match ($status) {
            'active'  => true,
            'passive' => false,
            default   => null,
        };

        /* --- 2) Toplam kayıt ----------------------------------------
         * Aynı filtre iki sorguda da kullanılır (bkz. Repository).
         * Farklı olurlarsa sayfalama sessizce yanlış çalışır. */
        $total = $this->users()->countAll($search, $activeOnly);

        /* --- 3) Paginator -------------------------------------------- */
        $paginator = new Paginator($total, $page, $perPage);

        /* --- 4) Yalnızca bu sayfanın satırları ----------------------- */
        $rows = $this->users()->page(
            $paginator->offset(),
            $paginator->perPage(),
            $search,
            $activeOnly
        );

        $this->view('users/index', [
            'title'     => 'Kullanıcılar',
            'subtitle'  => 'Sunucu taraflı sayfalama örneği',
            'rows'      => $rows,
            'paginator' => $paginator,

            /* Filtreler görünüme geri gönderilir: hem form alanları
             * dolu kalsın hem de sayfa bağlantıları filtreyi taşısın. */
            'search'    => $search,
            'status'    => $status,
            'perPage'   => $perPage,

            /* Filtreleri "Uygula"ya basmadan çalıştıran kod.
             * Sayfaya özel; her sayfada yüklenmez. */
            'scripts'   => ['users.js'],
        ]);
    }
}
