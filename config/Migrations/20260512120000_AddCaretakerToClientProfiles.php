<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Klient (client_profiles) → opiekun (pracownik) + opcjonalne zastępstwo w okresie.
 *
 *   caretaker_user_id     — główny opiekun (zawsze ustalony jeśli klient ma kogoś)
 *   substitute_user_id    — zastępca (np. na czas urlopu opiekuna)
 *   substitute_from / _to — okres aktywności zastępcy (NULL = od/do bez limitu)
 *
 * Funkcjonalnie: jeśli dziś mieści się w [substitute_from, substitute_to] →
 * faktyczny opiekun to substitute_user_id, inaczej caretaker_user_id.
 * Logika kalkulacji aktualnego opiekuna jest w ClientProfile entity.
 */
class AddCaretakerToClientProfiles extends BaseMigration
{
    public function change(): void
    {
        $this->table('client_profiles')
            ->addColumn('caretaker_user_id', 'uuid', [
                'null'    => true,
                'default' => null,
                'after'   => 'contractor_id',
                'comment' => 'Główny opiekun klienta (FK do users.id)',
            ])
            ->addColumn('substitute_user_id', 'uuid', [
                'null'    => true,
                'default' => null,
                'after'   => 'caretaker_user_id',
                'comment' => 'Zastępca opiekuna (FK do users.id)',
            ])
            ->addColumn('substitute_from', 'date', [
                'null'    => true,
                'default' => null,
                'after'   => 'substitute_user_id',
            ])
            ->addColumn('substitute_to', 'date', [
                'null'    => true,
                'default' => null,
                'after'   => 'substitute_from',
            ])
            ->addIndex(['caretaker_user_id'],  ['name' => 'BY_CARETAKER'])
            ->addIndex(['substitute_user_id'], ['name' => 'BY_SUBSTITUTE'])
            ->update();
    }
}
