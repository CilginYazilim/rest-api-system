<?php
/**
 * =====================================================================
 *  SOL MENÜ TANIMI
 * ---------------------------------------------------------------------
 *  Yeni bir sayfa eklerken sırayla üç dosyaya dokunursunuz:
 *      1. routes/web.php   → adres hangi denetleyiciye gidiyor?
 *      2. config/menu.php  → menüde nasıl görünüyor?  ← bu dosya
 *      3. views/...        → ekranda ne var?
 *
 *  Mobil alt çubuk bu listedeki İLK ÜÇ bağlantıyı gösterir.
 * =====================================================================
 */

declare(strict_types=1);

return [
    [
        'label' => 'Genel',
        'items' => [
            ['route' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Kontrol Paneli', 'short' => 'Panel'],
            ['route' => 'tokens',    'icon' => 'lock',      'label' => 'API Jetonları',  'short' => 'Jeton'],
            ['route' => 'api-belgeleri', 'icon' => 'activity',  'label' => 'API Belgeleri',  'short' => 'Belge'],
        ],
    ],
    [
        'label' => 'Veri',
        'items' => [
            ['route' => 'users', 'icon' => 'users', 'label' => 'Kullanıcılar', 'short' => 'Kullanıcı'],
        ],
    ],
];
