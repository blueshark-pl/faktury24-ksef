<?php
return [
    'Users.Registration.active' => true, //enable or disable password meter. Defaults to true
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