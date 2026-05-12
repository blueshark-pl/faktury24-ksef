<?php
/**
 * Email: welcome / e-mail powitalny po założeniu konta przez admina.
 *
 * @var \Cake\View\View $this
 * @var \Cake\Datasource\EntityInterface $user
 * @var string $firstName
 * @var string $role        np. admin/user/client/asystent_spedytora/...
 * @var string $resetUrl
 * @var string $lang        pl|en
 */

use Cake\Core\Configure;

$appName = trim((string)(Configure::read('App.name') ?? ''));
if ($appName === '') {
    $appName = 'Booklio TMS';
}
$brand = '#1b5998';
$greeting = $firstName !== '' ? __('Cześć {0},', $firstName) : __('Cześć,');

// Treść per rola — co user może w systemie
$roleBlocks = [
    'admin' => [
        'label' => __('Administrator'),
        'desc'  => __('Masz pełny dostęp do systemu — zarządzanie użytkownikami, rolami, uprawnieniami, kontami i wszystkimi modułami operacyjnymi.'),
        'items' => [
            __('Zarządzanie użytkownikami i rolami'),
            __('Wszystkie moduły: faktury, zlecenia, kontrahenci, finanse, raporty'),
            __('Konfiguracja systemu i integracji (KSeF, GUS, banki)'),
        ],
    ],
    'user' => [
        'label' => __('Pracownik (spedytor)'),
        'desc'  => __('Otrzymujesz pełen zakres operacyjny — zlecenia transportowe, kontrahenci, faktury, rozliczenia.'),
        'items' => [
            __('Tworzenie i edycja zleceń transportowych'),
            __('Wystawianie faktur (VAT, walutowe, korekty)'),
            __('Zarządzanie kontrahentami i ich akceptacja'),
            __('Rozliczenia, wyciągi bankowe, raporty'),
        ],
    ],
    'client' => [
        'label' => __('Klient portalu'),
        'desc'  => __('Dostajesz dostęp do portalu klienta — możesz na bieżąco śledzić swoje zlecenia transportowe i pobierać dokumenty.'),
        'items' => [
            __('Podgląd zleceń transportowych powiązanych z Twoim NIP'),
            __('Pobieranie dokumentów CMR i faktur PDF'),
            __('Filtrowanie po statusie, dacie, walucie'),
        ],
    ],
    'asystent_spedytora' => [
        'label' => __('Asystent spedytora'),
        'desc'  => __('Pomagasz w obsłudze zleceń transportowych — wprowadzasz kontrahentów (wymagana akceptacja kierownika) oraz prowadzisz dokumentację POL/POD.'),
        'items' => [
            __('Dodawanie nowych kontrahentów (do akceptacji)'),
            __('Tworzenie i edycja zleceń transportowych'),
            __('Wgrywanie dokumentów POL/POD'),
            __('Rejestrowanie statusów operacyjnych'),
        ],
    ],
    'mlodszy_spedytor' => [
        'label' => __('Młodszy spedytor'),
        'desc'  => __('Pełna obsługa zleceń — od tworzenia, przez przewoźników i koszty, po wynik finansowy.'),
        'items' => [
            __('Tworzenie zleceń i wystawianie zleceń przewoźnikom'),
            __('Przypisywanie pojazdów i kierowców'),
            __('Dodawanie kosztów i faktur kosztowych'),
            __('Podgląd marży i wyniku finansowego zlecenia'),
        ],
    ],
    'spedycja_manager' => [
        'label' => __('Kierownik Spedycji'),
        'desc'  => __('Nadzorujesz dział spedycji — akceptujesz nowych kontrahentów i masz wgląd w pełną operacyjną i finansową stronę zleceń.'),
        'items' => [
            __('Akceptacja kontrahentów dodanych przez asystentów'),
            __('Pełen wgląd w operacyjną stronę zleceń'),
            __('Koszty, marże, wyniki finansowe'),
            __('Raporty zarządcze'),
        ],
    ],
    'sales_manager' => [
        'label' => __('Kierownik Działu Handlowego'),
        'desc'  => __('Akceptujesz nowych kontrahentów oraz masz dostęp do raportów zarządczych dla działu handlowego.'),
        'items' => [
            __('Akceptacja kontrahentów'),
            __('Raporty zarządcze i wyniki sprzedaży'),
            __('Podgląd zleceń transportowych'),
        ],
    ],
];

$block = $roleBlocks[$role] ?? $roleBlocks['user'];

$this->assign('preheader', __('Twoje konto w {0} zostało utworzone. Ustaw hasło aby się zalogować.', $appName));
?>

<div style="font-size:15px; line-height:1.65; color:#0f172a;">

  <!-- Ikona powitania -->
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 18px;">
    <tr>
      <td style="width:48px; height:48px; background:rgba(27,89,152,.10); border-radius:14px; text-align:center; vertical-align:middle;">
        <span style="display:inline-block; font-size:22px; line-height:48px; color:<?= h($brand) ?>;">&#128075;</span>
      </td>
    </tr>
  </table>

  <h1 style="margin:0 0 8px; font-size:22px; font-weight:700; color:#0f172a; line-height:1.3;">
    <?= __('Witamy w {0}', $appName) ?>
  </h1>
  <p style="margin:0 0 16px; font-size:14px; color:#475569;"><?= h($greeting) ?></p>
  <p style="margin:0 0 16px; color:#334155;">
    <?= __('Administrator założył dla Ciebie konto w {0} — systemie zarządzania transportem.', '<strong>' . h($appName) . '</strong>') ?>
  </p>

  <!-- Rola usera -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:8px 0 20px;">
    <tr>
      <td style="background:rgba(27,89,152,.06); border:1px solid rgba(27,89,152,.18); border-radius:12px; padding:16px 18px;">
        <div style="font-weight:700; font-size:12px; color:<?= h($brand) ?>; margin:0 0 6px; text-transform:uppercase; letter-spacing:.4px;">
          <?= __('Twoja rola w systemie') ?>
        </div>
        <div style="font-weight:700; font-size:16px; color:#0f172a; margin:0 0 8px;">
          <?= h($block['label']) ?>
        </div>
        <div style="font-size:13px; color:#334155; margin:0 0 12px;">
          <?= h($block['desc']) ?>
        </div>
        <div style="font-size:12px; font-weight:700; color:#0f172a; margin:0 0 6px; text-transform:uppercase; letter-spacing:.4px;">
          <?= __('Co możesz robić') ?>
        </div>
        <ul style="margin:0; padding-left:18px; color:#334155; font-size:13px;">
          <?php foreach ($block['items'] as $item): ?>
            <li style="margin-bottom:4px;"><?= h($item) ?></li>
          <?php endforeach; ?>
        </ul>
      </td>
    </tr>
  </table>

  <!-- Polityka bezpieczeństwa — must reset password -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 20px;">
    <tr>
      <td style="background:#fff7ed; border:1px solid #fed7aa; border-radius:12px; padding:14px 18px;">
        <div style="font-weight:700; font-size:13px; color:#c2410c; margin:0 0 6px; text-transform:uppercase; letter-spacing:.4px;">
          &#128274; <?= __('Polityka bezpieczeństwa') ?>
        </div>
        <div style="font-size:13px; color:#7c2d12;">
          <?= __('Przed pierwszym logowaniem musisz ustawić własne hasło. Hasło utworzone przez administratora jest tymczasowe i wygaśnie po ustanowieniu nowego.') ?>
        </div>
      </td>
    </tr>
  </table>

  <!-- CTA -->
  <div style="margin: 24px 0;">
    <?= $this->element('email/cta_button', [
      'url'       => $resetUrl,
      'label'     => __('Ustaw moje hasło'),
      'bg'        => $brand,
      'textColor' => '#ffffff',
      'radius'    => 8,
    ]) ?>
  </div>

  <!-- Login info -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:8px 0 20px;">
    <tr>
      <td style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px 18px;">
        <div style="font-weight:700; font-size:13px; color:#0f172a; margin:0 0 6px; text-transform:uppercase; letter-spacing:.4px;">
          <?= __('Dane logowania') ?>
        </div>
        <div style="font-size:13px; color:#334155;">
          <?= __('Login (e-mail)') ?>: <strong><?= h($user->get('email')) ?></strong>
        </div>
      </td>
    </tr>
  </table>

  <!-- Fallback link -->
  <p style="margin:18px 0 6px; font-size:12px; color:#64748b;">
    <?= __('Jeśli przycisk nie działa, skopiuj poniższy link do przeglądarki:') ?>
  </p>
  <p style="margin:0 0 0; word-break:break-all; font-size:12px;">
    <a href="<?= h($resetUrl) ?>" style="color:<?= h($brand) ?>; text-decoration:underline;"><?= h($resetUrl) ?></a>
  </p>

  <hr style="border:none; border-top:1px solid #e2e8f0; margin:24px 0 16px;">

  <p style="margin:0; font-size:12px; color:#64748b;">
    <?= __('Link do ustawienia hasła wygaśnie po 7 dniach. Jeśli to nie Ty powinieneś otrzymać tę wiadomość, zignoruj ją lub poinformuj administratora.') ?>
  </p>
</div>
