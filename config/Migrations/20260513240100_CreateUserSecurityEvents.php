<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Wydarzenia bezpieczeństwa per user.
 *
 * event_type:
 *  - failed_login       — błędne dane logowania (z IP)
 *  - login_locked       — przekroczono limit prób z IP
 *  - 2fa_enabled        — user włączył 2FA
 *  - 2fa_disabled       — user wyłączył 2FA
 *  - 2fa_failed         — błędny kod 2FA
 *  - webauthn_added     — zarejestrowano klucz WebAuthn
 *  - webauthn_removed   — usunięto klucz
 *  - password_changed   — user zmienił hasło
 *  - password_reset_sent— wysłano link resetu
 */
class CreateUserSecurityEvents extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user_security_events', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('user_id',    'uuid',     ['null' => true, 'default' => null, 'comment' => 'NULL gdy login attempt na nieistniejący email'])
            ->addColumn('username',   'string',   ['limit' => 200, 'null' => true, 'default' => null, 'comment' => 'Email/login z próby (snapshot, useful gdy user_id null)'])
            ->addColumn('event_type', 'string',   ['limit' => 50,  'null' => false])
            ->addColumn('ip',         'string',   ['limit' => 45,  'null' => true, 'default' => null])
            ->addColumn('user_agent', 'string',   ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('details',    'text',     ['null' => true, 'default' => null, 'comment' => 'Opcjonalny JSON z dodatkowymi danymi'])
            ->addColumn('created',    'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addIndex(['event_type'])
            ->addIndex(['created'])
            ->addIndex(['ip'])
            ->create();
    }
}
