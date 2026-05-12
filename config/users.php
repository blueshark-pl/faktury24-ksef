<?php
return [
    'Users.Registration.active' => false, //enable or disable self-registration. Defaults to true

    // ── Authorization unauthorized handler ────────────────────────────────────
    // Dla roli `client` przekierowuj na /portal (zamiast domyślnego /users/login,
    // co przy nieautoryzowanym `/` powoduje pętlę ERR_TOO_MANY_REDIRECTS).
    'Auth.AuthorizationMiddleware' => [
        'unauthorizedHandler' => [
            'className' => 'CakeDC/Users.DefaultRedirect',
            'url' => function (\Psr\Http\Message\ServerRequestInterface $request, array $options) {
                $identity = $request->getAttribute('identity');
                $role     = strtolower((string)($identity?->get('role') ?? ''));

                // Klient → /portal
                if ($role === 'client') {
                    return \Cake\Routing\Router::url([
                        'plugin'     => false,
                        'controller' => 'ClientPortal',
                        'action'     => 'index',
                    ]);
                }

                // Asystent spedytora → /zlecenia (jego domyślny landing,
                // bo nie ma dostępu do / czyli Invoices::index — pętla redirect)
                if ($role === 'asystent_spedytora') {
                    return \Cake\Routing\Router::url([
                        'plugin'     => false,
                        'controller' => 'SpeedOrders',
                        'action'     => 'index',
                    ]);
                }

                // Niezalogowany → ekran logowania
                if (!$identity) {
                    return \Cake\Routing\Router::url([
                        'plugin'     => 'CakeDC/Users',
                        'controller' => 'Users',
                        'action'     => 'login',
                    ]);
                }

                // Zalogowany ale brak uprawnień → /zlecenia (bezpieczny default
                // dla pracowniczych ról, nie powoduje pętli)
                return \Cake\Routing\Router::url([
                    'plugin'     => false,
                    'controller' => 'SpeedOrders',
                    'action'     => 'index',
                ]);
            },
        ],
    ],

    // CakeDC Users: password rehash
    // The plugin loads identifiers inside authenticators (Auth.Authenticators.*.identifier).
    // Using deprecated `Auth.PasswordRehash.identifiers` causes warnings because the global
    // identifier collection is empty in the service loader.
    'Auth.PasswordRehash.identifiers' => [],
    'Auth.PasswordRehash.authenticators' => [
        'Form' => 'Authentication.Password',
    ],

    // 2FA (Google Authenticator) – opt-in per user.
    // Global OTP processing must be enabled, while requirement is decided by checker.
    'OneTimePasswordAuthenticator.login' => true,
    'OneTimePasswordAuthenticator.checker' => \App\Authentication\OptInOneTimePasswordAuthenticationChecker::class,
    'OneTimePasswordAuthenticator.issuer' => 'Faktury24',
    'Users.Email.mailerClass' => \App\Mailer\MyUsersMailer::class,
    'Users.passwordMeter.enabled' => true, //enable or disable password meter. Defaults to true
    'Users.passwordMeter.requiredScore' => 1, //int value from 1 to 4 (25%,50%,75%,100%). Defaults to 1
    'Users.passwordMeter.messagesList' => ['Puste hasło', 'Zbyt proste', 'Proste', 'W porządku', 'Świetne hasło!'], //Messages for each password level (0%,25%,50%,75%,100%)
    'Users.passwordMeter.pswMinLength' => 8, //Password min length, defaults to 8. It won't affect users validation in backend
    'Users.passwordMeter.showMessage' => true, //shows password message
];