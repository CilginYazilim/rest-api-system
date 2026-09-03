<?php
/**
 * =====================================================================
 *  DashboardController – Kontrol paneli
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Http\Controller;
use App\Repositories\ApiTokenRepository;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $userId = (int) Auth::id();

        $users  = $this->users();
        $tokens = new ApiTokenRepository($this->db);

        $this->view('dashboard/index', [
            'title'    => 'Kontrol Paneli',
            'subtitle' => 'Hoş geldiniz, ' . (Auth::user()?->name ?? ''),

            'stats' => [
                ['label' => 'API Jetonlarım', 'value' => $tokens->countForUser($userId), 'icon' => 'lock'],
                ['label' => 'Toplam Kullanıcı', 'value' => $users->countAll(),           'icon' => 'users',
                 'hint'  => 'API üzerinden erişilebilir'],
                ['label' => 'Aktif Hesap',      'value' => $users->countAll('', true),   'icon' => 'check'],
            ],

            'activity' => $this->activity()->latest(8),
        ]);
    }
}
