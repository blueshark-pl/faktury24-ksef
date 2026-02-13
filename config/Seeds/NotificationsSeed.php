<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

class NotificationsSeed extends AbstractSeed
{
    public function run(): void
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')))->format('Y-m-d H:i:s');

        $rows = [
            [
                'id' => Cake\Utility\Text::uuid(),
                'user_id' => null,
                'channel' => 'push',
                'type' => 'invoice_received',
                'severity' => 'info',
                'title' => 'Nowe faktury odebrane z KSeF (3)',
                'message' => 'FV/10/2025 – ACME Sp. z o.o.; FV/11/2025 – PixelPro S.A.; FV/12/2025 – Procarte.',
                'action_url' => '/invoices/received',
                'action_label' => 'Pokaż',
                'is_read' => 0,
                'read_at' => null,
                'created' => $now,
                'modified' => $now,
            ],
            [
                'id' => Cake\Utility\Text::uuid(),
                'user_id' => null,
                'channel' => 'push',
                'type' => 'payment_matched',
                'severity' => 'success',
                'title' => 'Dopasowano płatność',
                'message' => 'Przelew 1 230,00 zł dopasowany do FV/09/2025 (JJ Labs).',
                'action_url' => '/payments/matches',
                'action_label' => 'Szczegóły',
                'is_read' => 0,
                'read_at' => null,
                'created' => $now,
                'modified' => $now,
            ],
            [
                'id' => Cake\Utility\Text::uuid(),
                'user_id' => null,
                'channel' => 'email',
                'type' => 'due_soon',
                'severity' => 'warning',
                'title' => 'Zbliża się termin płatności',
                'message' => 'FV/08/2025 – PartnersC: pozostało 2 dni (kwota: 4 920,00 zł).',
                'action_url' => '/invoices/overdue',
                'action_label' => 'Zapłać',
                'is_read' => 0,
                'read_at' => null,
                'created' => $now,
                'modified' => $now,
            ],
            [
                'id' => Cake\Utility\Text::uuid(),
                'user_id' => null,
                'channel' => 'push',
                'type' => 'ksef_auth_error',
                'severity' => 'danger',
                'title' => 'Błąd autoryzacji KSeF',
                'message' => 'Token wygasł lub nieprawidłowy. Odśwież uprawnienia w Ustawienia KSeF.',
                'action_url' => '/ksef-authorizations',
                'action_label' => 'Ustawienia KSeF',
                'is_read' => 1,
                'read_at' => $now,
                'created' => $now,
                'modified' => $now,
            ],
            [
                'id' => Cake\Utility\Text::uuid(),
                'user_id' => null,
                'channel' => 'sms',
                'type' => 'maintenance',
                'severity' => 'info',
                'title' => 'KSeF – prace serwisowe',
                'message' => 'Przerwa: 2025-10-23, 22:00–23:59. Synchronizacja może być opóźniona.',
                'action_url' => null,
                'action_label' => null,
                'is_read' => 0,
                'read_at' => null,
                'created' => $now,
                'modified' => $now,
            ],
            [
                'id' => Cake\Utility\Text::uuid(),
                'user_id' => null,
                'channel' => 'email',
                'type' => 'jpk_reminder',
                'severity' => 'info',
                'title' => 'Przypomnienie: JPK_V7',
                'message' => 'Termin wysyłki JPK_V7 za wrzesień 2025 zbliża się (do 25.10.2025).',
                'action_url' => '/reports/jpk',
                'action_label' => 'Przygotuj JPK',
                'is_read' => 0,
                'read_at' => null,
                'created' => $now,
                'modified' => $now,
            ],
        ];

        $this->table('notifications')->insert($rows)->save();
    }
}
