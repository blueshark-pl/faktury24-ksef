<?php
return [
    'Users.Registration.active' => true, //enable or disable password meter. Defaults to true
    'OneTimePasswordAuthenticator.login' => false,
    'Users.Email.mailerClass' => \App\Mailer\MyUsersMailer::class,
    'Users.passwordMeter.enabled' => true, //enable or disable password meter. Defaults to true
    'Users.passwordMeter.requiredScore' => 1, //int value from 1 to 4 (25%,50%,75%,100%). Defaults to 1
    'Users.passwordMeter.messagesList' => ['Puste hasło', 'Zbyt proste', 'Proste', 'W porządku', 'Świetne hasło!'], //Messages for each password level (0%,25%,50%,75%,100%)
    'Users.passwordMeter.pswMinLength' => 8, //Password min length, defaults to 8. It won't affect users validation in backend
    'Users.passwordMeter.showMessage' => true, //shows password message
];