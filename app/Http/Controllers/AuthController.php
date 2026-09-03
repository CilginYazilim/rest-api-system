<?php
/**
 * =====================================================================
 *  AuthController – Giriş / çıkış ekranları
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Http\Controller;

final class AuthController extends Controller
{
    /** Giriş formunu gösterir. */
    public function showLogin(Request $request): void
    {
        /* Oturum zaman aşımına uğradıysa kullanıcıyı bilgilendir.
         * "Neden atıldım?" sorusunu ortadan kaldırır. */
        if (Session::pull('_expired') === true) {
            Flash::warning('Uzun süre işlem yapılmadığı için oturumunuz sonlandırıldı.');
        }

        $this->view('auth/login', [
            'title'  => 'Giriş Yap',
            'errors' => Flash::errors(),
            'old'    => Flash::old(),
        ], 'layouts/auth');
    }

    /**
     * Giriş isteğini işler.
     *
     * POST → Redirect → GET kalıbı kullanılır: başarılı ya da başarısız,
     * her durumda yönlendirme yaparız. Böylece kullanıcı F5'e bastığında
     * form yeniden gönderilmez.
     */
    public function login(Request $request): void
    {
        $validator = new Validator($_POST);
        $validator->email('email');

        // Girişte parola KURALLARI uygulanmaz; sadece boş olmamalıdır.
        // (Kural uygularsak, eski parolası kurala uymayan kullanıcı
        //  hesabına hiç giremez.)
        $password = (string) ($_POST['password'] ?? '');

        if ($password === '') {
            $validator->addError('password', 'Parola alanı boş bırakılamaz.');
        }

        if ($validator->fails()) {
            Flash::withInput($validator->errors(), ['email' => $request->input('email')]);
            Response::redirect(url('login'));
        }

        $result = Auth::attempt(
            (string) $validator->validated()['email'],
            $password,
            $request,
            // Onay kutusu işaretliyse kalıcı çerez verilir.
            $request->bool('remember')
        );

        if (!$result['ok']) {
            Flash::error($result['message']);
            Flash::withInput([], ['email' => $request->input('email')]);
            Response::redirect(url('login'));
        }

        Flash::success($result['message']);

        /* Kullanıcı giriş yapmadan önce korumalı bir sayfaya gitmek
         * istemişse oraya geri götürüyoruz. Adres oturumda saklandığı
         * için dışarıdan yönlendirme (open redirect) riski yoktur. */
        $intended = (string) Session::pull('_intended', 'dashboard');

        Response::redirect(url($intended));
    }

    /** Oturumu kapatır. */
    public function logout(Request $request): void
    {
        Auth::logout($request);

        /* Session::destroy() flash mesajlarını da sildiği için mesajı
         * oturum yeniden başladıktan SONRA yazıyoruz. */
        Session::start();
        Flash::info('Oturumunuz güvenli bir şekilde kapatıldı.');

        Response::redirect(url('login'));
    }
}
