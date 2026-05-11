<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Tabele do dynamicznych ról i uprawnień:
 *   - roles                — katalog ról systemowych i własnych
 *   - permissions          — katalog kodów uprawnień (np. invoices.add)
 *   - roles_permissions    — pivot many-to-many
 *
 * Po migracji uruchom seed: RolesPermissionsSeed.
 * users.role pozostaje stringiem (kodem) — łączymy przez roles.code.
 */
class CreateRolesAndPermissions extends BaseMigration
{
    public function up(): void
    {
        // ── roles ────────────────────────────────────────────────────────────
        $roles = $this->table('roles', ['id' => false, 'primary_key' => ['id']]);
        $roles
            ->addColumn('id',          'integer', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('code',        'string',  ['limit' => 60,  'null' => false])
            ->addColumn('name',        'string',  ['limit' => 150, 'null' => false])
            ->addColumn('description', 'text',    ['null' => true, 'default' => null])
            ->addColumn('is_system',   'boolean', ['null' => false, 'default' => false])
            ->addColumn('is_active',   'boolean', ['null' => false, 'default' => true])
            ->addColumn('created',     'datetime',['null' => true])
            ->addColumn('modified',    'datetime',['null' => true])
            ->addIndex(['code'], ['unique' => true, 'name' => 'UNIQ_ROLE_CODE'])
            ->create();

        // ── permissions ──────────────────────────────────────────────────────
        $perms = $this->table('permissions', ['id' => false, 'primary_key' => ['id']]);
        $perms
            ->addColumn('id',          'integer', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('code',        'string',  ['limit' => 120, 'null' => false])
            ->addColumn('name',        'string',  ['limit' => 200, 'null' => false])
            ->addColumn('category',    'string',  ['limit' => 60,  'null' => false, 'default' => 'general'])
            ->addColumn('description', 'text',    ['null' => true, 'default' => null])
            ->addColumn('created',     'datetime',['null' => true])
            ->addColumn('modified',    'datetime',['null' => true])
            ->addIndex(['code'],     ['unique' => true, 'name' => 'UNIQ_PERM_CODE'])
            ->addIndex(['category'], ['name' => 'BY_CATEGORY'])
            ->create();

        // ── roles_permissions ────────────────────────────────────────────────
        $rp = $this->table('roles_permissions', ['id' => false, 'primary_key' => ['id']]);
        $rp
            ->addColumn('id',            'integer', ['signed' => false, 'identity' => true, 'null' => false])
            ->addColumn('role_id',       'integer', ['signed' => false, 'null' => false])
            ->addColumn('permission_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('created',       'datetime',['null' => true])
            ->addIndex(['role_id', 'permission_id'], ['unique' => true, 'name' => 'UNIQ_ROLE_PERM'])
            ->addIndex(['role_id'],       ['name' => 'BY_ROLE'])
            ->addIndex(['permission_id'], ['name' => 'BY_PERM'])
            ->addForeignKey('role_id',       'roles',       'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('permission_id', 'permissions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('roles_permissions')->drop()->save();
        $this->table('permissions')->drop()->save();
        $this->table('roles')->drop()->save();
    }
}
