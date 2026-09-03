<?php
/**
 * =====================================================================
 *  Auth – Kimlik doğrulama
 * ---------------------------------------------------------------------
 *  ÖNEMLİ TASARIM KARARI: Oturumda yalnızca KULLANICI ID'si tutulur.
 *  Ad, e-posta, hesap durumu gibi bilgiler her istekte veritabanından
 *  TAZE okunur.
 *
 *  Neden? Bu bilgileri oturuma yazsaydık, bir yönetici hesabı
 *  kapattığında o kişi oturumu açık kaldığı sürece sistemi
 *  kullanmaya devam ederdi. Taze okuma bu açığı kapatır; bedeli
 *  istek başına tek bir hafif sorgudur.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Repositories\ActivityRepository;
use App\Repositories\RememberTokenRepository;
use App\Repositories\UserRepository;

final class Auth
{
    private const SESSION_KEY = '_auth_user_id';

    /**
     * "Kullanıcı yok" durumunda oyalanmak için kullanılan SAHTE parola özeti.
     *
     * NEDEN GERÇEK BİR HASH OLMAK ZORUNDA?
     * Buraya uydurma bir metin yazmak işe YARAMAZ, hatta zararlıdır:
     * password_verify() geçersiz bir özet gördüğünde bcrypt'i hiç
     * çalıştırmaz ve bambaşka bir sürede döner. Ölçtüğümüzde geçersiz
     * özet ~1000 ms, gerçek özet ~115 ms sürüyordu — yani tam da
     * gizlemek istediğimiz farkı YARATIYORDU.
     *
     * Aşağıdaki değer rastgele bir metnin gerçek bcrypt özetidir ve
     * maliyeti (cost 10) veritabanındaki özetlerle aynıdır. Böylece
     * "e-posta kayıtlı değil" ile "parola yanlış" yolları aynı sürede
     * biter ve zamanlama üzerinden kullanıcı sayımı yapılamaz.
     *
     * Karşılığı olan bir parola YOKTUR; bu özetle giriş yapılamaz.
     */
    private const DUMMY_HASH = '$2y$10$4b/csI5waIGNTzOJFUoQe.QHkusrpywBSxCMippslZ2SipT3jmeZa';

    /** İstek boyunca aynı kullanıcıyı tekrar tekrar sorgulamamak için. */
    private static ?User $cached = null;

    private static bool $resolved = false;

    /* =================================================================
     *  DURUM SORGULARI
     * ============================================================== */

    /** Giriş yapan kullanıcı (yoksa null). */
    public static function user(): ?User
    {
        if (self::$resolved) {
            return self::$cached;
        }

        self::$resolved = true;

        $id = Session::get(self::SESSION_KEY);

        /* Oturumda kimlik yok ama "beni hatırla" çerezi olabilir.
         * Tarayıcı kapanınca oturum çerezi silinir; kalıcı çerez ise
         * kalır ve kullanıcıyı sessizce geri içeri alır. */
        if (!is_int($id) && !is_numeric($id)) {
            $id = self::recallFromCookie();

            if ($id === null) {
                return self::$cached = null;
            }
        }

        $user = (new UserRepository(Database::connection()))->find((int) $id);

        /* Hesap silinmiş veya pasifleştirilmişse oturumu anında düşür.
         * "Pasif kullanıcı giriş yapmış sayılmaz" kuralı burada uygulanır. */
        if ($user === null || !$user->isActive) {
            self::forget();

            return self::$cached = null;
        }

        return self::$cached = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()?->id;
    }

    /** İşlemi yapan kişi, hedef kaydın sahibi mi? */
    public static function isSelf(int $userId): bool
    {
        return self::id() === $userId;
    }

    /* =================================================================
     *  GİRİŞ
     * ============================================================== */

    /**
     * Kimlik bilgilerini doğrular ve oturumu açar.
     *
     * @return array{ok:bool,message:string,user:?User}
     */
    public static function attempt(
        string $email,
        string $password,
        Request $request,
        bool $remember = false
    ): array {
        $db       = Database::connection();
        $users    = new UserRepository($db);
        $limiter  = new RateLimiter($db);
        $activity = new ActivityRepository($db);

        // Sayaç anahtarı: e-posta + IP (ikisi birlikte, tek başına değil).
        $key = $email . '|' . $request->ip();

        /* --- 1) Kilit kontrolü ------------------------------------- */
        $lockedFor = $limiter->lockedFor($key);

        if ($lockedFor > 0) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    'Çok fazla hatalı deneme yaptınız. Lütfen %d dakika sonra tekrar deneyin.',
                    (int) ceil($lockedFor / 60)
                ),
                'user'    => null,
            ];
        }

        $user = $users->findByEmail($email);

        /* --- 2) Parola doğrulama ------------------------------------
         * KULLANICI SAYIMI (user enumeration) SIZINTISI:
         * "Bu e-posta kayıtlı değil" ve "Parola yanlış" diye AYRI
         * mesajlar verirsek, saldırgan hangi e-postaların sistemde
         * kayıtlı olduğunu öğrenir. Bu yüzden TEK ve AYNI mesajı
         * kullanıyoruz.
         *
         * Ayrıca kullanıcı bulunamadığında da bir kez password_verify
         * çalıştırıyoruz: aksi halde yanıt SÜRESİ farkından "bu e-posta
         * var mı yok mu" anlaşılabilirdi (timing attack). */
        if ($user === null) {
            password_verify($password, self::DUMMY_HASH);

            $limiter->hit($key, $request->ip());
            $activity->log(null, ActivityRepository::LOGIN_FAILED, 'Bilinmeyen e-posta: ' . $email, $request->ip());

            return ['ok' => false, 'message' => self::genericFailure(), 'user' => null];
        }

        if (!$user->verifyPassword($password)) {
            $limiter->hit($key, $request->ip());
            $activity->log($user->id, ActivityRepository::LOGIN_FAILED, 'Hatalı parola denemesi.', $request->ip());

            $remaining = $limiter->remaining($key);

            /* Son 2 hakta kullanıcıyı uyarıyoruz. Bu, gerçek kullanıcıya
             * yardım eder; saldırgana kayda değer bir bilgi vermez. */
            $message = self::genericFailure();

            if ($remaining > 0 && $remaining <= 2) {
                $message .= sprintf(' (Kalan deneme hakkı: %d)', $remaining);
            }

            return ['ok' => false, 'message' => $message, 'user' => null];
        }

        /* --- 3) Hesap durumu ---------------------------------------- */
        if (!$user->isActive) {
            $activity->log($user->id, ActivityRepository::LOGIN_FAILED, 'Pasif hesapla giriş denemesi.', $request->ip());

            return [
                'ok'      => false,
                'message' => 'Hesabınız pasif durumda. Lütfen yönetici ile iletişime geçin.',
                'user'    => null,
            ];
        }

        /* --- 4) Başarılı giriş -------------------------------------- */
        $limiter->clear($key);

        /* Parola özeti eski algoritmayla üretilmişse sessizce yenile.
         * Kullanıcı hiçbir şey fark etmez ama hesabı güçlenir. */
        if ($user->needsRehash()) {
            $users->updatePasswordHash($user->id, password_hash($password, PASSWORD_DEFAULT));
        }

        self::login($user);
        $users->touchLogin($user->id);

        /* "Beni hatırla" işaretlendiyse kalıcı anahtar ver.
         * Bu ADIM GİRİŞTEN SONRA yapılır: parola doğrulanmadan
         * kalıcı anahtar üretmek, denemeyi ödüllendirmek olurdu. */
        if ($remember) {
            self::issueRememberCookie($user->id);
        }

        $activity->log(
            $user->id,
            ActivityRepository::LOGIN,
            $remember ? 'Panele giriş yapıldı (beni hatırla açık).' : 'Panele giriş yapıldı.',
            $request->ip()
        );

        return ['ok' => true, 'message' => 'Hoş geldiniz, ' . $user->name . '!', 'user' => $user];
    }

    /* =================================================================
     *  "BENİ HATIRLA"
     * -----------------------------------------------------------------
     *  Ayrıntılı tasarım notu:
     *  app/Repositories/RememberTokenRepository.php
     * ============================================================== */

    /** Çerez adı. */
    private const REMEMBER_COOKIE = 'cy_remember';

    /**
     * Kalıcı çerezden kullanıcıyı geri getirir.
     *
     * @return int|null Doğrulanırsa kullanıcı ID'si
     */
    private static function recallFromCookie(): ?int
    {
        $raw = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');

        if ($raw === '') {
            return null;
        }

        /* Biçim: "<selector>:<validator>". Tam olarak iki parça
         * beklediğimiz için sınırı 2 veriyoruz; kurcalanmış bir
         * çerez buradan geçemez. */
        $parts = explode(':', $raw, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            self::forgetRememberCookie();

            return null;
        }

        [$selector, $validator] = $parts;

        $db     = Database::connection();
        $tokens = new RememberTokenRepository($db);

        $userId = $tokens->verify($selector, $validator);

        if ($userId === null) {
            self::forgetRememberCookie();

            return null;
        }

        /* Hesap hâlâ geçerli mi? Silinmiş veya pasifleştirilmiş bir
         * kullanıcı, elindeki eski çerezle geri giremesin. */
        $user = (new UserRepository($db))->find($userId);

        if ($user === null || !$user->isActive) {
            $tokens->deleteBySelector($selector);
            self::forgetRememberCookie();

            return null;
        }

        /* ANAHTAR TEK KULLANIMLIKTIR: kullanıldığı anda eskisi silinir,
         * yenisi verilir ("token rotation"). Çerezi kopyalayan biri
         * en fazla bir kez kullanabilir; asıl kullanıcı bir sonraki
         * ziyaretinde geçersiz anahtarla karşılaşır ve oturum düşer.
         * Sessiz ve süresiz bir arka kapı oluşmasını böyle engelliyoruz. */
        $tokens->deleteBySelector($selector);
        self::issueRememberCookie($userId);

        /* Oturumu da tazeliyoruz ki sonraki isteklerde çerez
         * doğrulamasına hiç gerek kalmasın. */
        Session::regenerate();
        Session::set(self::SESSION_KEY, $userId);
        Csrf::rotate();

        return $userId;
    }

    /** Yeni bir kalıcı anahtar üretip çereze yazar. */
    public static function issueRememberCookie(int $userId): void
    {
        $lifetime = (int) Config::get('session.remember_lifetime', 60 * 60 * 24 * 30);

        $token = (new RememberTokenRepository(Database::connection()))
            ->create($userId, $lifetime);

        setcookie(self::REMEMBER_COOKIE, $token['selector'] . ':' . $token['validator'], [
            'expires'  => time() + $lifetime,
            'path'     => '/',

            /* httponly: JavaScript bu çerezi OKUYAMAZ. Bir XSS açığı
             * oluşsa bile kalıcı giriş anahtarı çalınamaz.
             * samesite=Lax: başka sitelerin tetiklediği isteklerde
             * çerez gönderilmez (CSRF yüzeyi daralır). */
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => Session::isHttps(),
        ]);
    }

    /** Kalıcı çerezi tarayıcıdan siler. */
    private static function forgetRememberCookie(): void
    {
        if (!isset($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }

        unset($_COOKIE[self::REMEMBER_COOKIE]);

        // Geçmiş bir tarih göndermek, çerezi silmenin standart yoludur.
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => Session::isHttps(),
        ]);
    }

    /**
     * Kullanıcıyı oturuma yazar.
     *
     * session_regenerate_id ŞART: Saldırgan giriş öncesinde kurbana bir
     * oturum kimliği kabul ettirmişse ("session fixation"), giriş anında
     * kimliği değiştirerek o kimliği işe yaramaz hale getiririz.
     */
    public static function login(User $user): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, $user->id);

        // Yeni oturum = yeni CSRF anahtarı.
        Csrf::rotate();

        self::$cached   = $user;
        self::$resolved = true;
    }

    /** Oturumu kapatır. */
    public static function logout(Request $request): void
    {
        $user = self::user();

        if ($user !== null) {
            (new ActivityRepository(Database::connection()))
                ->log($user->id, ActivityRepository::LOGOUT, 'Oturum kapatıldı.', $request->ip());
        }

        /* Yalnızca BU CİHAZIN kalıcı anahtarını siliyoruz.
         * Kullanıcının diğer cihazlarındaki "beni hatırla" tercihi
         * bozulmasın; "çıkış yap" o cihaz için verilmiş bir karardır.
         * (Tüm cihazlardan çıkış, parola değişiminde yapılır.) */
        $raw = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');

        if ($raw !== '') {
            $selector = explode(':', $raw, 2)[0];

            (new RememberTokenRepository(Database::connection()))
                ->deleteBySelector($selector);
        }

        self::forgetRememberCookie();

        Session::destroy();

        self::$cached   = null;
        self::$resolved = true;
    }

    /** Oturumdaki kimliği düşürür (hesap silinmiş/pasif durumu için). */
    private static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Giriş hatalarında kullanılan TEK mesaj.
     * Ayrıntı vermemek bilinçli bir güvenlik tercihidir.
     */
    private static function genericFailure(): string
    {
        return 'E-posta adresi veya parola hatalı.';
    }
}
