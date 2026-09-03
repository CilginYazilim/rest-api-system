<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Giriş ekranı
 * ---------------------------------------------------------------------
 *  @var array<string,string> $errors Alan bazlı doğrulama hataları
 *  @var array<string,mixed>  $old    Kullanıcının önceki girdisi
 * =====================================================================
 */

$errors = $errors ?? [];
$old    = $old ?? [];

// Örnek hesaplar. GERÇEK PROJEDE BU BLOĞU SİLİN; yalnızca bu açık
// kaynak demoyu deneyenlerin hızlı giriş yapabilmesi içindir.
$demoAccounts = [
    ['role' => 'Yönetici', 'email' => 'admin@cilginyazilim.com', 'pass' => 'Admin1234'],
    ['role' => 'Kullanıcı', 'email' => 'demo@cilginyazilim.com', 'pass' => 'Demo1234'],
];
?>
<div class="cy-auth">

    <!-- ============ SOL: Marka tanıtımı (mobilde gizlenir) ============ -->
    <aside class="cy-auth__aside">
        <div class="cy-auth__brand">
            <img src="<?= e(asset('images/logo.png')) ?>" alt="">
            <strong><?= e($appBrand ?? 'Çılgın Yazılım') ?></strong>
        </div>

        <div class="cy-auth__pitch">
            <h2>Belgelenmiş, jetonla korunan REST API</h2>
            <p>Bearer jeton kimlik doğrulaması, sürümlenmiş uç noktalar, tutarlı JSON zarfı, sayfalama üst verisi ve istek hızı sınırlaması içeren örnek PHP REST API.</p>

            <div class="cy-auth__features">
                <span class="cy-auth__feature"><?= icon('shield', 'cy-icon cy-icon--sm') ?> Bearer jeton doğrulaması (SHA-256 saklama)</span>
                <span class="cy-auth__feature"><?= icon('activity', 'cy-icon cy-icon--sm') ?> Sürümlenmiş uç noktalar: /api/v1/...</span>
                <span class="cy-auth__feature"><?= icon('users', 'cy-icon cy-icon--sm') ?> Sayfalama üst verisi ve doğru HTTP kodları</span>
                <span class="cy-auth__feature"><?= icon('lock', 'cy-icon cy-icon--sm') ?> Jeton başına istek hızı sınırı</span>
            </div>
        </div>

        <p class="cy-auth__note mb-0">
            MIT lisanslıdır · cilginyazilim.com
        </p>
    </aside>

    <!-- ============ SAĞ: Form ============ -->
    <main class="cy-auth__panel">
        <div class="cy-auth__form">

            <div class="cy-auth__logo-mobile">
                <img src="<?= e(asset('images/logo.png')) ?>" alt="">
                <strong><?= e($appName ?? 'CY REST API') ?></strong>
            </div>

            <h1 class="cy-title mb-1">Giriş yapın</h1>
            <p class="cy-subtitle mb-4">Devam etmek için hesap bilgilerinizi girin.</p>

            <form method="post" action="<?= e(url('login')) ?>" novalidate id="cy_login_form">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="email">E-posta</label>
                    <div class="cy-input-icon">
                        <?= icon('mail', 'cy-icon cy-icon--sm') ?>
                        <input type="email"
                               class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                               id="email" name="email"
                               value="<?= old($old, 'email') ?>"
                               placeholder="ornek@cilginyazilim.com"
                               autocomplete="username"
                               inputmode="email"
                               autofocus required>
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback d-block"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Parola</label>
                    <div class="cy-password">
                        <input type="password"
                               class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                               id="password" name="password"
                               placeholder="••••••••"
                               autocomplete="current-password" required>

                        <!-- Parolayı geçici olarak görünür yapar; yazım
                             hatalarını azaltır, mobilde çok işe yarar. -->
                        <button type="button" class="cy-password__toggle js-toggle-password"
                                aria-label="Parolayı göster">
                            <?= icon('eye', 'cy-icon cy-icon--sm') ?>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <!--
                    BENİ HATIRLA
                    ------------------------------------------------
                    İşaretlenirse tarayıcıya, oturum çerezinden ayrı,
                    30 gün ömürlü bir anahtar yazılır. Anahtar TEK
                    KULLANIMLIKTIR ve her otomatik girişte yenilenir;
                    çalınan bir çerez ikinci kez işe yaramaz.

                    Çerezde parola YOKTUR ve JavaScript onu okuyamaz
                    (httponly). Ayrıntı: app/Core/Auth.php
                -->
                <div class="cy-remember">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               id="remember" name="remember" value="1">
                        <label class="form-check-label" for="remember">
                            Beni hatırla
                        </label>
                    </div>

                    <span class="cy-remember__hint">30 gün açık kalır</span>
                </div>

                <button type="submit" class="btn cy-btn cy-btn--primary cy-btn--block">
                    <?= icon('logout', 'cy-icon cy-icon--sm') ?> Giriş Yap
                </button>
            </form>

            <!-- ---- Demo hesaplar (gerçek projede kaldırın) ---- -->
            <div class="cy-demo">
                <span class="cy-eyebrow">Demo hesaplar · tıklayarak doldurun</span>

                <div class="cy-demo__grid">
                    <?php foreach ($demoAccounts as $account): ?>
                        <button type="button" class="cy-demo__item js-demo-fill"
                                data-email="<?= e($account['email']) ?>"
                                data-password="<?= e($account['pass']) ?>">
                            <span class="cy-badge cy-badge--brand"><?= e($account['role']) ?></span>
                            <span class="flex-grow-1">
                                <strong class="d-block"><?= e($account['email']) ?></strong>
                                <code><?= e($account['pass']) ?></code>
                            </span>
                            <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <p class="cy-muted mt-3 mb-0" style="font-size:.75rem">
                    Bu hesaplar yalnızca örnek veridir. Kendi sunucunuza kurarken
                    parolaları mutlaka değiştirin.
                </p>
            </div>
        </div>
    </main>
</div>
