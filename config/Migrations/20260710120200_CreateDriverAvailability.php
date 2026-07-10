<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Wzorce dostepnosci kierowcy — dlugoterminowe reguly (nie jednorazowe wpisy grafiku).
 *
 * Przyklad: "Kowalski jezdzi PN-PT 6:00-18:00, nie robi weekendow, nie akceptuje ADR".
 * Pomaga planerowi automatycznie eliminowac nie-fit kierowcow zanim sprawdzi grafik.
 *
 * Rekomendacja: 7 wpisow per kierowca (1 na dzien tygodnia).
 */
class CreateDriverAvailability extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('driver_availability', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('driver_id', 'char', ['limit' => 36, 'null' => false]);

        $table->addColumn('day_of_week', 'integer', ['null' => false,
            'comment' => '1=poniedzialek, 7=niedziela (ISO 8601)',
        ]);
        $table->addColumn('shift_start', 'time', ['null' => true,
            'comment' => 'Godzina rozpoczecia — null = nie pracuje w ten dzien',
        ]);
        $table->addColumn('shift_end', 'time', ['null' => true]);

        $table->addColumn('max_hours_this_day', 'integer', ['null' => true,
            'comment' => 'Miekki limit godzin na dzien (poza limitami 561/2006)',
        ]);

        // Preferencje pracy — filtry planera
        $table->addColumn('accepts_international', 'boolean', ['null' => false, 'default' => true,
            'comment' => 'Zgoda na jazdy zagraniczne',
        ]);
        $table->addColumn('accepts_adr', 'boolean', ['null' => false, 'default' => false,
            'comment' => 'Ma certyfikat ADR (i chce jezdzic z ADR)',
        ]);
        $table->addColumn('accepts_night', 'boolean', ['null' => false, 'default' => true,
            'comment' => 'Jezdzi nocami',
        ]);
        $table->addColumn('accepts_weekend', 'boolean', ['null' => false, 'default' => false,
            'comment' => 'Jezdzi w weekendy',
        ]);

        $table->addColumn('notes', 'text', ['null' => true]);
        $table->addTimestamps('created', 'modified');

        $table->addIndex(['driver_id', 'day_of_week'], ['unique' => true, 'name' => 'UQ_DRIVER_DOW']);
        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);

        $table->create();
    }
}
