<?php
/**
 * =====================================================================
 *  ApiAuth – Bearer jetonuyla kimlik doğrulama
 * ---------------------------------------------------------------------
 *  OTURUM (session) İLE JETON ARASINDAKİ FARK
 *  ---------------------------------------------------------------
 *  Panel, ÇEREZ tabanlı oturum kullanır: tarayıcı çerezi her isteğe
 *  otomatik ekler. Bu kolaylık aynı zamanda CSRF'in kaynağıdır —
 *  başka bir site sizin adınıza istek attırabilir; bu yüzden panelde
 *  CSRF anahtarı zorunludur.
 *
 *  API ise DURUMSUZDUR (stateless): kimlik her istekte
 *  "Authorization: Bearer ..." başlığıyla AÇIKÇA gönderilir.
 *  Tarayıcı bu başlığı kendiliğinden eklemediği için CSRF riski
 *  yoktur; dolayısıyla API uç noktalarında CSRF anahtarı ARANMAZ.
 *  Bu, kuralı "unutmak" değil, tehdit modelinin farklı olmasıdır.
 *
 *  Buna karşılık jeton, gövdede veya adreste DEĞİL, başlıkta
 *  taşınmalıdır: adres çubuğundaki bir jeton sunucu günlüklerine,
 *  tarayıcı geçmişine ve Referer başlığına sızar.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\ApiToken;
use App\Models\User;
use App\Repositories\ApiTokenRepository;
use App\Repositories\UserRepository;

final class ApiAuth
{
    /** Bu istekte doğrulanan jeton. */
    private static ?ApiToken $token = null;

    /** Jetonun sahibi. */
    private static ?User $user = null;

    /**
     * İsteği doğrular. Başarısızsa JSON hata döndürür ve durur.
     *
     * Bu metot ara katmandan çağrılır (routes/web.php → 'api').
     */
    public static function authenticate(Request $request): void
    {
        $plain = $request->bearerToken();

        if ($plain === '') {
            /* WWW-Authenticate başlığı 401 yanıtlarında STANDARTTIR:
             * istemciye hangi kimlik doğrulama şemasını beklediğinizi
             * söyler. Kütüphaneler buna bakarak davranır. */
            ApiResponse::header('WWW-Authenticate', 'Bearer realm="api"');

            ApiResponse::error(
                'unauthenticated',
                'Authorization başlığında Bearer jetonu bulunamadı.',
                401
            );
        }

        $db     = Database::connection();
        $tokens = new ApiTokenRepository($db);

        $token = $tokens->findByPlainToken($plain);

        if ($token === null) {
            /* HATA MESAJINDA AYRINTI VERMİYORUZ.
             * "jeton yok" ile "jeton iptal edilmiş" ayrımını söylemek,
             * saldırgana hangi jetonun bir zamanlar var olduğunu
             * öğretir. Tek ve aynı mesaj yeterlidir. */
            ApiResponse::error('invalid_token', 'Jeton geçersiz veya iptal edilmiş.', 401);
        }

        self::$token = $token;
        self::$user  = (new UserRepository($db))->find($token->userId);

        // Hız sınırı jeton BAŞINA uygulanır (IP başına değil):
        // aynı ofisten çalışan iki istemci birbirini engellemesin.
        (new ApiRateLimiter($db))->check($token->id, $request->ip());

        // Kullanım damgası, hız sınırından SONRA atılır: sınıra takılan
        // istek "kullanıldı" sayılmamalıdır.
        $tokens->touch($token->id, $request->ip());
    }

    /**
     * Jetonun belirtilen yetkisi var mı?
     *
     * 401 DEĞİL 403 döndürüyoruz: kimlik doğrulandı (kim olduğunuzu
     * biliyoruz), ama bu iş için yetkiniz yok. İkisini karıştırmak,
     * istemcinin "jetonumu yenileyeyim" diye boşuna uğraşmasına yol
     * açar.
     */
    public static function requireScope(string $scope): void
    {
        if (self::$token !== null && self::$token->can($scope)) {
            return;
        }

        ApiResponse::error(
            'insufficient_scope',
            sprintf('Bu işlem için "%s" yetkisi gerekiyor.', $scope),
            403,
            ['required_scope' => $scope, 'granted' => self::$token?->scopes ?? []]
        );
    }

    public static function token(): ?ApiToken
    {
        return self::$token;
    }

    public static function user(): ?User
    {
        return self::$user;
    }
}
