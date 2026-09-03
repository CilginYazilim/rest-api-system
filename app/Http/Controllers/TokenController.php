<?php
/**
 * =====================================================================
 *  TokenController – API jetonu yönetim ekranı (panel)
 * ---------------------------------------------------------------------
 *  Bu ekran API'nin PARÇASI DEĞİLDİR; panelin bir sayfasıdır ve oturum
 *  + CSRF ile korunur. Jeton üretmek için API jetonu gerekseydi
 *  "tavuk-yumurta" sorunu doğardı: ilk jetonu nasıl alacaktınız?
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Http\Controller;
use App\Models\ApiToken;
use App\Repositories\ApiTokenRepository;

final class TokenController extends Controller
{
    public function index(Request $request): void
    {
        $userId = (int) Auth::id();

        $repository = new ApiTokenRepository($this->db);

        $total     = $repository->countForUser($userId);
        $paginator = new Paginator($total, Paginator::pageFromRequest($request), 10);
        $tokens    = $repository->pageForUser($userId, $paginator->offset(), $paginator->perPage());

        $this->view('tokens/index', [
            'title'     => 'API Jetonları',
            'subtitle'  => 'Uygulamalarınızın API erişimi',
            'tokens'    => $tokens,
            'paginator' => $paginator,

            /* YENİ ÜRETİLEN JETON YALNIZCA BİR KEZ GÖSTERİLİR.
             * Oturumda "flash" olarak taşınıp okunduğu anda silinir;
             * sayfa yenilenirse bir daha görünmez. Böylece jeton
             * ekranda, tarayıcı geçmişinde ve sunucuda kalıcı olmaz. */
            'freshToken' => Session::pull('_fresh_token'),

            // Sayfaya özel kod; her sayfada yüklenmez.
            'scripts'    => ['tokens.js'],
        ]);
    }

    /**
     * Yeni jeton üretir.
     *
     * POST + CSRF ile korunur: jeton üretmek yetki veren bir işlemdir,
     * GET ile tetiklenebilseydi kötü niyetli bir sitedeki <img>
     * etiketi bile kurbanın hesabında jeton oluşturabilirdi.
     */
    public function store(Request $request): void
    {
        $userId = (int) Auth::id();

        $name = trim($request->input('name'));

        if ($name === '') {
            Flash::error('Jetona bir ad verin (örn. "Mobil uygulama").');
            Response::redirect(url('tokens'));
        }

        /* Yetkiler onay kutularından gelir ve BEYAZ LİSTEDEN geçer.
         * Kullanıcı formu değiştirip "admin" gönderse bile kabul
         * edilmez (bkz. ApiTokenRepository::normalizeScopes). */
        $scopes = [];

        if ($request->bool('scope_read')) {
            $scopes[] = ApiToken::SCOPE_READ;
        }

        if ($request->bool('scope_write')) {
            $scopes[] = ApiToken::SCOPE_WRITE;
        }

        $created = (new ApiTokenRepository($this->db))->create($userId, $name, $scopes);

        $this->activity()->log(
            $userId,
            'api_token_created',
            sprintf('API jetonu üretildi: %s (#%d).', $name, $created['id']),
            $request->ip()
        );

        // Jetonu tek seferlik göstermek üzere oturuma koy.
        Session::set('_fresh_token', $created['token']);

        Flash::success('Jeton oluşturuldu. Bu ekranda yalnızca BİR KEZ gösterilecek.');

        /* POST → Redirect → GET: kullanıcı F5'e bastığında form
         * yeniden gönderilmesin, ikinci bir jeton üretilmesin. */
        Response::redirect(url('tokens'));
    }

    /** Jetonu iptal eder. */
    public function revoke(Request $request): void
    {
        $userId = (int) Auth::id();
        $id     = (int) $request->input('id');

        /* İptal, kullanıcının KENDİ jetonlarıyla sınırlıdır; hedef
         * sorguya user_id koşuluyla girer (IDOR koruması). */
        if ((new ApiTokenRepository($this->db))->revoke($userId, $id)) {
            $this->activity()->log($userId, 'api_token_revoked', sprintf('API jetonu iptal edildi (#%d).', $id), $request->ip());

            Flash::success('Jeton iptal edildi. Bu jetonla yapılan istekler artık reddedilecek.');
        } else {
            Flash::error('Jeton bulunamadı veya zaten iptal edilmiş.');
        }

        Response::redirect(url('tokens'));
    }
}
