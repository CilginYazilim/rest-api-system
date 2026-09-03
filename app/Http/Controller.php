<?php
/**
 * =====================================================================
 *  Controller – Tüm denetleyicilerin ortak atası
 * ---------------------------------------------------------------------
 *  Denetleyici (controller) ne yapar?
 *    1. İstekten gelen veriyi alır
 *    2. Doğrulama kurallarını uygular
 *    3. Repository'den veriyi ister
 *    4. Sonucu görünüme (veya JSON'a) devreder
 *
 *  İÇİNDE SQL YAZILMAZ, HTML BASILMAZ. İkisi de ayrı katmanların işidir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Http;

use App\Core\Database;
use App\Core\View;
use App\Repositories\ActivityRepository;
use App\Repositories\UserRepository;
use PDO;

abstract class Controller
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function users(): UserRepository
    {
        return new UserRepository($this->db);
    }

    protected function activity(): ActivityRepository
    {
        return new ActivityRepository($this->db);
    }

    /**
     * Görünümü basar.
     *
     * @param array<string,mixed> $data
     */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        View::render($view, $data, $layout);
    }
}
