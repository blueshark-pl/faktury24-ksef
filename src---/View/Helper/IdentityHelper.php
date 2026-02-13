<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;

class IdentityHelper extends Helper
{
    /**
     * Zwraca aktualnie zalogowanego użytkownika (jeśli istnieje)
     */
    public function user(): ?\Authentication\IdentityInterface
    {
        return $this->getView()->getRequest()->getAttribute('identity');
    }

    /**
     * Skrót: $this->Identity->id() -> zwraca ID usera
     */
    public function id(): ?string
    {
        return $this->user()?->get('id');
    }

    /**
     * Skrót: $this->Identity->companyId() -> zwraca firmę przypisaną do usera
     */
    public function companyId(): ?string
    {
        return $this->user()?->get('company_id');
    }

    /**
     * Pobierz dowolne pole
     */
    public function get(string $field): mixed
    {
        return $this->user()?->get($field);
    }

    /**
     * Sprawdź rolę
     */
    public function hasRole(string $role): bool
    {
        return $this->user()?->get('role') === $role;
    }
}
