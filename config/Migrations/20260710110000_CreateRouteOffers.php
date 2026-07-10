<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Oferty cenowe wysylane klientowi z planera tras.
 *
 * Cykl zycia: draft → sent → (viewed | accepted | rejected | expired).
 * Klient akceptuje po kliknieciu w link z tokenem (bez logowania).
 * Akceptacja moze automatycznie stworzyc speed_orders z waypoints z planu.
 */
class CreateRouteOffers extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('route_offers', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);

        $table->addColumn('route_plan_id', 'char', ['limit' => 36, 'null' => false,
            'comment' => 'FK do route_plans.id — kazda oferta pochodzi z planu',
        ]);

        $table->addColumn('contractor_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'FK do contractors.id — do kogo oferta',
        ]);

        // Wiadomosc do klienta
        $table->addColumn('sent_to_email', 'string', ['limit' => 255, 'null' => true,
            'comment' => 'Adres email na ktory zostala wyslana',
        ]);
        $table->addColumn('sent_to_name', 'string', ['limit' => 200, 'null' => true]);
        $table->addColumn('subject', 'string', ['limit' => 200, 'null' => true,
            'comment' => 'Temat maila',
        ]);
        $table->addColumn('message_body', 'text', ['null' => true,
            'comment' => 'Wiadomosc do klienta (edytowana przed wyslaniem)',
        ]);

        // Warunki cenowe
        $table->addColumn('price', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false,
            'comment' => 'Kwota netto oferty',
        ]);
        $table->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'PLN']);
        $table->addColumn('vat_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true,
            'comment' => 'Stawka VAT % (np. 23 lub 0 dla eksportu/UE)',
        ]);
        $table->addColumn('payment_days', 'integer', ['null' => true, 'default' => 30,
            'comment' => 'Termin platnosci (dni)',
        ]);

        // Token dla klienta do akceptacji bez logowania
        $table->addColumn('access_token', 'string', ['limit' => 64, 'null' => false,
            'comment' => 'Unikalny token do URL akceptacji (/oferty/wglad/{token})',
        ]);
        $table->addColumn('valid_until', 'date', ['null' => true,
            'comment' => 'Do kiedy oferta jest wazna',
        ]);

        // Status oferty
        $table->addColumn('status', 'string', ['limit' => 20, 'null' => false,
            'default' => 'draft',
            'comment' => 'draft|sent|viewed|accepted|rejected|expired',
        ]);
        $table->addColumn('sent_at', 'datetime', ['null' => true]);
        $table->addColumn('viewed_at', 'datetime', ['null' => true]);
        $table->addColumn('decided_at', 'datetime', ['null' => true,
            'comment' => 'Kiedy klient klilkna akceptuj / odrzuc',
        ]);
        $table->addColumn('decision_reason', 'text', ['null' => true,
            'comment' => 'Powod odrzucenia (opcjonalne od klienta)',
        ]);

        // Powiazane dokumenty wygenerowane przy akceptacji
        $table->addColumn('generated_speed_order_id', 'integer', ['null' => true,
            'comment' => 'FK do speed_orders utworzonego po akceptacji',
        ]);
        $table->addColumn('generated_invoice_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'FK do invoices gdy oferta -> proforma',
        ]);
        $table->addColumn('pdf_path', 'string', ['limit' => 500, 'null' => true,
            'comment' => 'Sciezka do wygenerowanego PDF-a oferty',
        ]);

        $table->addColumn('created_by_user_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('notes', 'text', ['null' => true]);

        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['company_id', 'status'], ['name' => 'BY_COMPANY_STATUS']);
        $table->addIndex(['route_plan_id'], ['name' => 'BY_PLAN']);
        $table->addIndex(['contractor_id'], ['name' => 'BY_CONTRACTOR']);
        $table->addIndex(['access_token'], ['unique' => true, 'name' => 'UQ_TOKEN']);
        $table->addIndex(['valid_until'], ['name' => 'BY_VALID_UNTIL']);

        $table->create();
    }
}
