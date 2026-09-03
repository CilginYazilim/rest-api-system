<?php
/**
 * =====================================================================
 *  PreferenceApiController – Kişisel arayüz tercihleri
 * ---------------------------------------------------------------------
 *  Şu an tek bir tercih var: AÇIK / KOYU TEMA.
 *
 *  TEMA NEDEN VERİTABANINDA TUTULUYOR?
 *  Önceki sürümde tercih yalnızca çerezdeydi. Çerez tarayıcıya
 *  bağlıdır; kullanıcı işten eve geçtiğinde, başka bir tarayıcı
 *  açtığında veya çerezleri temizlediğinde tercihi kayboluyordu.
 *  Tercihi HESABA bağlayınca kullanıcı nereden girerse girsin
 *  kendi düzenini buluyor.
 *
 *  Çerez yine de yazılıyor ama artık farklı bir işi var: sunucu,
 *  sayfanın İLK HTML'ini doğru temayla üretebilsin diye. Böylece
 *  sayfa bir an açık temada görünüp koyuya atlamıyor
 *  ("flash of wrong theme").
 *
 *  GÜVENLİK: Kullanıcı yalnızca KENDİ tercihini değiştirebilir.
 *  Hedef ID istekten okunmaz, oturumdan alınır (IDOR koruması).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Http\Controller;
use App\Models\User;

final class PreferenceApiController extends Controller
{
    /**
     * Tema tercihini kaydeder.
     *
     * Rota: POST api/preferences/theme   (auth + csrf)
     */
    public function theme(Request $request): void
    {
        /* Gelen değer beyaz listeden geçer: "dark" dışındaki HER ŞEY
         * "light" sayılır. Böylece uydurma bir değer veritabanına
         * ulaşamaz (ENUM sütunu da ikinci savunma hattıdır). */
        $theme = User::normalizeTheme($request->input('theme'));

        /* Hedef kullanıcı İSTEKTEN DEĞİL oturumdan alınır.
         * Formdan "user_id" kabul etseydik, herhangi biri başkasının
         * ID'sini göndererek onun temasını değiştirebilirdi (IDOR). */
        $userId = Auth::id();

        if ($userId === null) {
            Response::error('Oturumunuz sonlandı. Lütfen tekrar giriş yapın.', 401);
        }

        $this->users()->updateTheme($userId, $theme);

        /* Sunucunun bir sonraki sayfayı doğrudan doğru temayla
         * üretebilmesi için oturuma da yazıyoruz; her istekte
         * kullanıcıyı yeniden sorgulamaya gerek kalmaz. */
        Session::set('_theme', $theme);

        Response::json([
            'success' => true,
            'theme'   => $theme,
        ]);
    }
}
