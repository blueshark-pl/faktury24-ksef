<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Dodaje opcjonalny avatar do tabeli users — ścieżka do pliku zdjęcia profilowego.
 * Plik fizycznie w webroot/files/avatars/. W DB przechowujemy URL względny ('/files/avatars/xxx.jpg').
 */
class AddAvatarToUsers extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('avatar', 'string', [
                'limit'   => 500,
                'null'    => true,
                'default' => null,
                'after'   => 'pin_hash',
                'comment' => 'Ścieżka do zdjęcia profilowego (/files/avatars/...)',
            ])
            ->update();
    }
}
