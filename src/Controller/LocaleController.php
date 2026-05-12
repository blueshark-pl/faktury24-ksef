<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\I18n\I18n;

/**
 * Przełączanie języka UI — dostępne także bez logowania (np. ekran loginu).
 *
 * Nie dziedziczy z AppController żeby ominąć logikę identity/redirectów
 * (które są wymagane tylko dla zalogowanych użytkowników).
 */
class LocaleController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Flash');
    }

    public function beforeFilter(EventInterface $event): void
    {
        // celowo nie wołamy parent::beforeFilter — działamy bez identity
    }

    /**
     * GET /lang/{lang} — ustawia locale w sesji + cookie i przekierowuje na referer (lub /).
     * Akcja nazywa się `change`, bo `set` koliduje z Controller::set(name, value).
     *
     * Cookie `bookliio_lang` jest zapisywane na rok — przeżyje wylogowanie/zamknięcie
     * przeglądarki. Sesja jest priorytetem w odczycie (zmienia się szybciej),
     * cookie to fallback gdy sesja świeża po wylogowaniu.
     */
    public function change(string $lang): Response
    {
        $lang = in_array($lang, ['pl', 'en'], true) ? $lang : 'pl';
        $this->request->getSession()->write('Config.locale', $lang);
        I18n::setLocale($lang);

        // Jeśli user jest zalogowany i ma profil klienta — zapisz na stałe (DB)
        $identity = $this->request->getAttribute('identity');
        if ($identity) {
            try {
                $profile = $this->fetchTable('ClientProfiles')->find()
                    ->where(['user_id' => $identity->getIdentifier()])
                    ->first();
                if ($profile) {
                    $profile->locale = $lang;
                    $this->fetchTable('ClientProfiles')->save($profile);
                }
            } catch (\Throwable) { /* best-effort */ }
        }

        $referer = $this->request->referer(true);
        $response = $this->redirect($referer ?: '/');

        // Cookie: rok ważności, ścieżka '/', samesite=Lax
        return $response->withCookie(
            (new \Cake\Http\Cookie\Cookie('bookliio_lang'))
                ->withValue($lang)
                ->withPath('/')
                ->withExpiry(new \DateTime('+1 year'))
                ->withSecure((bool)$this->request->is('https'))
                ->withHttpOnly(false)        // dostępne dla JS jeśli zechcemy
                ->withSameSite('Lax')
        );
    }
}
