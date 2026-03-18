<?php

use Cake\Cache\Engine\FileEngine;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Log\Engine\FileLog;
use Cake\Mailer\Transport\MailTransport;
use function Cake\Core\env;

return [
    /*
     * Debug Level:
     *
     * Production Mode:
     * false: No error messages, errors, or warnings shown.
     *
     * Development Mode:
     * true: Errors and warnings shown.
     */
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * Configure basic information about the application.
     *
     * - namespace - The namespace to find app classes under.
     * - defaultLocale - The default locale for translation, formatting currencies and numbers, date and time.
     * - encoding - The encoding used for HTML + database connections.
     * - base - The base directory the app resides in. If false this
     *   will be auto-detected.
     * - dir - Name of app directory.
     * - webroot - The webroot directory.
     * - wwwRoot - The file path to webroot.
     * - baseUrl - To configure CakePHP to *not* use mod_rewrite and to
     *   use CakePHP pretty URLs, remove these .htaccess
     *   files:
     *      /.htaccess
     *      /webroot/.htaccess
     *   And uncomment the baseUrl key below.
     * - fullBaseUrl - A base URL to use for absolute links. When set to false (default)
     *   CakePHP generates required value based on `HTTP_HOST` environment variable.
     *   However, you can define it manually to optimize performance or if you
     *   are concerned about people manipulating the `Host` header.
     * - imageBaseUrl - Web path to the public images/ directory under webroot.
     * - cssBaseUrl - Web path to the public css/ directory under webroot.
     * - jsBaseUrl - Web path to the public js/ directory under webroot.
     * - paths - Configure paths for non class-based resources. Supports the
     *   `plugins`, `templates`, `locales` subkeys, which allow the definition of
     *   paths for plugins, view templates and locale files respectively.
     */
    'App' => [
        'namespace' => 'App',
        'encoding' => env('APP_ENCODING', 'UTF-8'),
        'defaultLocale' => env('APP_DEFAULT_LOCALE', 'pl'),
        'defaultTimezone' => env('APP_DEFAULT_TIMEZONE', 'Europe/Warsaw'),
        'base' => false,
        'dir' => 'src',
        'webroot' => 'webroot',
        'wwwRoot' => WWW_ROOT,
        //'baseUrl' => env('SCRIPT_NAME'),
        'fullBaseUrl' => false,
        'imageBaseUrl' => 'img/',
        'cssBaseUrl' => 'css/',
        'jsBaseUrl' => 'js/',
        'version' => '1.2.23 (12)',
        'ksefSchedulerKey' => env('APP_KSEF_SCHEDULER_KEY', ''),
        // Prefer URL/CID for best client compatibility; data URIs may be blocked by some mail clients.
        'emailLogoUrl' => env('APP_EMAIL_LOGO_URL', ''),
        'emailLogoDataUri' => env('APP_EMAIL_LOGO_DATA_URI', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAPQAAABCCAYAAABgvPuWAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAEtWlUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSfvu78nIGlkPSdXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQnPz4KPHg6eG1wbWV0YSB4bWxuczp4PSdhZG9iZTpuczptZXRhLyc+CjxyZGY6UkRGIHhtbG5zOnJkZj0naHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyc+CgogPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9JycKICB4bWxuczpBdHRyaWI9J2h0dHA6Ly9ucy5hdHRyaWJ1dGlvbi5jb20vYWRzLzEuMC8nPgogIDxBdHRyaWI6QWRzPgogICA8cmRmOlNlcT4KICAgIDxyZGY6bGkgcmRmOnBhcnNlVHlwZT0nUmVzb3VyY2UnPgogICAgIDxBdHRyaWI6Q3JlYXRlZD4yMDI1LTEyLTAxPC9BdHRyaWI6Q3JlYXRlZD4KICAgICA8QXR0cmliOkV4dElkPmViMjIxNDJhLTcyYjgtNDJkNS04ODIxLTIwNDU1NzA2YWU3NzwvQXR0cmliOkV4dElkPgogICAgIDxBdHRyaWI6RmJJZD41MjUyNjU5MTQxNzk1ODA8L0F0dHJpYjpGYklkPgogICAgIDxBdHRyaWI6VG91Y2hUeXBlPjI8L0F0dHJpYjpUb3VjaFR5cGU+CiAgICA8L3JkZjpsaT4KICAgPC9yZGY6U2VxPgogIDwvQXR0cmliOkFkcz4KIDwvcmRmOkRlc2NyaXB0aW9uPgoKIDxyZGY6RGVzY3JpcHRpb24gcmRmOmFib3V0PScnCiAgeG1sbnM6ZGM9J2h0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvJz4KICA8ZGM6dGl0bGU+CiAgIDxyZGY6QWx0PgogICAgPHJkZjpsaSB4bWw6bGFuZz0neC1kZWZhdWx0Jz5Qcm9qZWt0IGJleiBuYXp3eSAtIDE8L3JkZjpsaT4KICAgPC9yZGY6QWx0PgogIDwvZGM6dGl0bGU+CiA8L3JkZjpEZXNjcmlwdGlvbj4KCiA8cmRmOkRlc2NyaXB0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'),
        'paths' => [
            'plugins' => [ROOT . DS . 'plugins' . DS],
            'templates' => [ROOT . DS . 'templates' . DS],
            'locales' => [RESOURCES . 'locales' . DS],
        ],
    ],

    /*
     * Publiczne API Latarni KSeF (bez autoryzacji)
     * - /status: aktualny status + komunikaty (jeśli dotyczy)
     * - /messages: lista komunikatów (opcjonalnie)
     */
    'LatarniaKsef' => [
        'baseUrl' => env('LATARNIA_KSEF_BASE_URL', 'https://api-latarnia.ksef.mf.gov.pl'),
        'timeout' => (int)env('LATARNIA_KSEF_HTTP_TIMEOUT', 4),
        'cacheConfig' => 'latarniaKsef',
    ],

    /*
     * Security and encryption configuration
     *
     * - salt - A random string used in security hashing methods.
     *   The salt value is also used as the encryption key.
     *   You should treat it as extremely sensitive data.
     */
    'Security' => [
        'salt' => env('SECURITY_SALT'),
    ],

    /*
     * Apply timestamps with the last modified time to static assets (js, css, images).
     * Will append a querystring parameter containing the time the file was modified.
     * This is useful for busting browser caches.
     *
     * Set to true to apply timestamps when debug is true. Set to 'force' to always
     * enable timestamping regardless of debug value.
     */
    'Asset' => [
        //'timestamp' => true,
        // 'cacheTime' => '+1 year'
    ],

    /*
     * Configure the cache adapters.
     */
    'Cache' => [
        'default' => [
            'className' => FileEngine::class,
            'path' => CACHE,
            'url' => env('CACHE_DEFAULT_URL', null),
        ],

        /*
         * Configure the cache used for general framework caching.
         * Translation cache files are stored with this configuration.
         * Duration will be set to '+2 minutes' in bootstrap.php when debug = true
         * If you set 'className' => 'Null' core cache will be disabled.
         */
        '_cake_translations_' => [
            'className' => FileEngine::class,
            'prefix' => 'myapp_cake_translations_',
            // Keep translations cache separate from other persistent cache files.
            // This prevents issues if a single old cache file becomes inaccessible.
            'path' => CACHE . 'translations' . DS,
            'serialize' => true,
            'duration' => '+1 years',
            'url' => env('CACHE_CAKECORE_URL', null),
        ],

        /*
         * Configure the cache for model and datasource caches. This cache
         * configuration is used to store schema descriptions, and table listings
         * in connections.
         * Duration will be set to '+2 minutes' in bootstrap.php when debug = true
         */
        '_cake_model_' => [
            'className' => FileEngine::class,
            'prefix' => 'myapp_cake_model_',
            'path' => CACHE . 'models' . DS,
            'serialize' => true,
            'duration' => '+1 years',
            'url' => env('CACHE_CAKEMODEL_URL', null),
        ],

        // Cache for public Latarnia KSeF status/messages.
        'latarniaKsef' => [
            'className' => FileEngine::class,
            'prefix' => 'latarnia_ksef_',
            'path' => CACHE,
            'serialize' => true,
            'duration' => '+2 minutes',
        ],

        // Cache for internal KSeF status checks (InvoiceWrite via personal grants).
        // Shared per company/env/NIP to reduce load on KSeF.
        'ksefStatus' => [
            'className' => FileEngine::class,
            'prefix' => 'ksef_status_',
            'path' => CACHE,
            'serialize' => true,
            'duration' => env('KSEF_STATUS_CACHE_DURATION', '+3 minutes'),
        ],
    ],

    /*
     * Configure the Error and Exception handlers used by your application.
     *
     * By default errors are displayed using Debugger, when debug is true and logged
     * by Cake\Log\Log when debug is false.
     *
     * In CLI environments exceptions will be printed to stderr with a backtrace.
     * In web environments an HTML page will be displayed for the exception.
     * With debug true, framework errors like Missing Controller will be displayed.
     * When debug is false, framework errors will be coerced into generic HTTP errors.
     *
     * Options:
     *
     * - `errorLevel` - int - The level of errors you are interested in capturing.
     * - `trace` - boolean - Whether backtraces should be included in
     *   logged errors/exceptions.
     * - `log` - boolean - Whether you want exceptions logged.
     * - `exceptionRenderer` - string - The class responsible for rendering uncaught exceptions.
     *   The chosen class will be used for both CLI and web environments. If you want different
     *   classes used in CLI and web environments you'll need to write that conditional logic as well.
     *   The conventional location for custom renderers is in `src/Error`. Your exception renderer needs to
     *   implement the `render()` method and return either a string or Http\Response.
     *   `errorRenderer` - string - The class responsible for rendering PHP errors. The selected
     *   class will be used for both web and CLI contexts. If you want different classes for each environment
     *   you'll need to write that conditional logic as well. Error renderers need to
     *   to implement the `Cake\Error\ErrorRendererInterface`.
     * - `skipLog` - array - List of exceptions to skip for logging. Exceptions that
     *   extend one of the listed exceptions will also be skipped for logging.
     *   E.g.:
     *   `'skipLog' => ['Cake\Http\Exception\NotFoundException', 'Cake\Http\Exception\UnauthorizedException']`
     * - `extraFatalErrorMemory` - int - The number of megabytes to increase the memory limit by
     *   when a fatal error is encountered. This allows
     *   breathing room to complete logging or error handling.
     * - `ignoredDeprecationPaths` - array - A list of glob-compatible file paths that deprecations
     *   should be ignored in. Use this to ignore deprecations for plugins or parts of
     *   your application that still emit deprecations.
     */
    'Error' => [
        'errorLevel' => E_ALL & ~E_USER_DEPRECATED, // ukryj same deprecations
        'skipLog' => [],
        'log' => true,
        'trace' => true,
        'ignoredDeprecationPaths' => [],
    ],

    /*
     * Debugger configuration
     *
     * Define development error values for Cake\Error\Debugger
     *
     * - `editor` Set the editor URL format you want to use.
     *   By default atom, emacs, macvim, phpstorm, sublime, textmate, and vscode are
     *   available. You can add additional editor link formats using
     *   `Debugger::addEditor()` during your application bootstrap.
     * - `outputMask` A mapping of `key` to `replacement` values that
     *   `Debugger` should replace in dumped data and logs generated by `Debugger`.
     */
    'Debugger' => [
        'editor' => 'phpstorm',
    ],

    /*
     * Email configuration.
     *
     * By defining transports separately from delivery profiles you can easily
     * re-use transport configuration across multiple profiles.
     *
     * You can specify multiple configurations for production, development and
     * testing.
     *
     * Each transport needs a `className`. Valid options are as follows:
     *
     *  Mail   - Send using PHP mail function
     *  Smtp   - Send using SMTP
     *  Debug  - Do not send the email, just return the result
     *
     * You can add custom transports (or override existing transports) by adding the
     * appropriate file to src/Mailer/Transport. Transports should be named
     * 'YourTransport.php', where 'Your' is the name of the transport.
     */
    'EmailTransport' => [
        'default' => [
            'className' => MailTransport::class,
            /*
             * The keys host, port, timeout, username, password, client and tls
             * are used in SMTP transports
             */
            'host' => 'localhost',
            'port' => 25,
            'timeout' => 30,
            /*
             * It is recommended to set these options through your environment or app_local.php
             */
            //'username' => null,
            //'password' => null,
            'client' => null,
            'tls' => false,
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],

    /*
     * Email delivery profiles
     *
     * Delivery profiles allow you to predefine various properties about email
     * messages from your application and give the settings a name. This saves
     * duplication across your application and makes maintenance and development
     * easier. Each profile accepts a number of keys. See `Cake\Mailer\Mailer`
     * for more information.
     */
    'Email' => [
        'default' => [
            'transport' => 'default',
            'from' => 'you@localhost',
            /*
             * Will by default be set to config value of App.encoding, if that exists otherwise to UTF-8.
             */
            //'charset' => 'utf-8',
            //'headerCharset' => 'utf-8',
        ],
    ],

    /*
     * Connection information used by the ORM to connect
     * to your application's datastores.
     *
     * ### Notes
     * - Drivers include Mysql Postgres Sqlite Sqlserver
     *   See vendor\cakephp\cakephp\src\Database\Driver for the complete list
     * - Do not use periods in database name - it may lead to errors.
     *   See https://github.com/cakephp/cakephp/issues/6471 for details.
     * - 'encoding' is recommended to be set to full UTF-8 4-Byte support.
     *   E.g set it to 'utf8mb4' in MariaDB and MySQL and 'utf8' for any
     *   other RDBMS.
     */
    'Datasources' => [
        /*
         * These configurations should contain permanent settings used
         * by all environments.
         *
         * The values in app_local.php will override any values set here
         * and should be used for local and per-environment configurations.
         *
         * Environment variable-based configurations can be loaded here or
         * in app_local.php depending on the application's needs.
         */
        'default' => [
            'className' => Connection::class,
            'driver' => Mysql::class,
            'persistent' => false,
            'timezone' => 'UTC',

            /*
             * For MariaDB/MySQL the internal default changed from utf8 to utf8mb4, aka full utf-8 support
             */
            'encoding' => 'utf8mb4',

            /*
             * If your MySQL server is configured with `skip-character-set-client-handshake`
             * then you MUST use the `flags` config to set your charset encoding.
             * For e.g. `'flags' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']`
             */
            'flags' => [],
            'cacheMetadata' => true,
            'log' => false,

            /*
             * Set identifier quoting to true if you are using reserved words or
             * special characters in your table or column names. Enabling this
             * setting will result in queries built using the Query Builder having
             * identifiers quoted when creating SQL. It should be noted that this
             * decreases performance because each query needs to be traversed and
             * manipulated before being executed.
             */
            'quoteIdentifiers' => false,

            /*
             * During development, if using MySQL < 5.6, uncommenting the
             * following line could boost the speed at which schema metadata is
             * fetched from the database. It can also be set directly with the
             * mysql configuration directive 'innodb_stats_on_metadata = 0'
             * which is the recommended value in production environments
             */
            //'init' => ['SET GLOBAL innodb_stats_on_metadata = 0'],
        ],

        /*
         * The test connection is used during the test suite.
         */
        'test' => [
            'className' => Connection::class,
            'driver' => Mysql::class,
            'persistent' => false,
            'timezone' => 'UTC',
            'encoding' => 'utf8mb4',
            'flags' => [],
            'cacheMetadata' => true,
            'quoteIdentifiers' => false,
            'log' => false,
            //'init' => ['SET GLOBAL innodb_stats_on_metadata = 0'],
        ],
    ],

    /*
     * Configures logging options
     */
    'Log' => [
        'debug' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'debug',
            'url' => env('LOG_DEBUG_URL', null),
            'scopes' => null,
            'levels' => ['notice', 'info', 'debug'],
        ],
        'error' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'error',
            'url' => env('LOG_ERROR_URL', null),
            'scopes' => null,
            'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
        ],
        // To enable this dedicated query log, you need to set your datasource's log flag to true
        'queries' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'queries',
            'url' => env('LOG_QUERIES_URL', null),
            'scopes' => ['cake.database.queries'],
        ],
    ],

    /*
     * Session configuration.
     *
     * Contains an array of settings to use for session configuration. The
     * `defaults` key is used to define a default preset to use for sessions, any
     * settings declared here will override the settings of the default config.
     *
     * ## Options
     *
     * - `cookie` - The name of the cookie to use. Defaults to value set for `session.name` php.ini config.
     *    Avoid using `.` in cookie names, as PHP will drop sessions from cookies with `.` in the name.
     * - `cookiePath` - The url path for which session cookie is set. Maps to the
     *   `session.cookie_path` php.ini config. Defaults to base path of app.
     * - `timeout` - The time in minutes a session can be 'idle'. If no request is received in
     *    this duration, the session will be expired and rotated. Pass 0 to disable idle timeout checks.
     * - `defaults` - The default configuration set to use as a basis for your session.
     *    There are four built-in options: php, cake, cache, database.
     * - `handler` - Can be used to enable a custom session handler. Expects an
     *    array with at least the `engine` key, being the name of the Session engine
     *    class to use for managing the session. CakePHP bundles the `CacheSession`
     *    and `DatabaseSession` engines.
     * - `ini` - An associative array of additional 'session.*` ini values to set.
     *
     * Within the `ini` key, you will likely want to define:
     *
     * - `session.cookie_lifetime` - The number of seconds that cookies are valid for. This
     *    should be longer than `Session.timeout`.
     * - `session.gc_maxlifetime` - The number of seconds after which a session is considered 'garbage'
     *    that can be deleted by PHP's session cleanup behavior. This value should be greater than both
     *    `Sesssion.timeout` and `session.cookie_lifetime`.
     *
     * The built-in `defaults` options are:
     *
     * - 'php' - Uses settings defined in your php.ini.
     * - 'cake' - Saves session files in CakePHP's /tmp directory.
     * - 'database' - Uses CakePHP's database sessions.
     * - 'cache' - Use the Cache class to save sessions.
     *
     * To define a custom session handler, save it at src/Http/Session/<name>.php.
     * Make sure the class implements PHP's `SessionHandlerInterface` and set
     * Session.handler to <name>
     *
     * To use database sessions, load the SQL file located at config/schema/sessions.sql
     */
    'Session' => [
        'defaults' => 'php',
    ],

    /**
     * DebugKit configuration.
     *
     * Contains an array of configurations to apply to the DebugKit plugin, if loaded.
     * Documentation: https://book.cakephp.org/debugkit/5/en/index.html#configuration
     *
     * ## Options
     *
     *  - `panels` - Enable or disable panels. The key is the panel name, and the value is true to enable,
     *     or false to disable.
     *  - `includeSchemaReflection` - Set to true to enable logging of schema reflection queries. Disabled by default.
     *  - `safeTld` - Set an array of whitelisted TLDs for local development.
     *  - `forceEnable` - Force DebugKit to display. Careful with this, it is usually safer to simply whitelist
     *     your local TLDs.
     *  - `ignorePathsPattern` - Regex pattern (including delimiter) to ignore paths.
     *     DebugKit won’t save data for request URLs that match this regex.
     *  - `ignoreAuthorization` - Set to true to ignore Cake Authorization plugin for DebugKit requests.
     *     Disabled by default.
     *  - `maxDepth` - Defines how many levels of nested data should be shown in general for debug output.
     *     Default is 5. WARNING: Increasing the max depth level can lead to an out of memory error.
     *  - `variablesPanelMaxDepth` - Defines how many levels of nested data should be shown in the variables tab.
     *     Default is 5. WARNING: Increasing the max depth level can lead to an out of memory error.
     */
    'DebugKit' => [
        'forceEnable' => filter_var(env('DEBUG_KIT_FORCE_ENABLE', false), FILTER_VALIDATE_BOOLEAN),
        'safeTld' => env('DEBUG_KIT_SAFE_TLD', null),
        'ignoreAuthorization' => env('DEBUG_KIT_IGNORE_AUTHORIZATION', false),
    ],

    /**
     * TestSuite configuration.
     *
     * ## Options
     *
     *  - `errorLevel` - Defaults to `E_ALL`. Can be set to `false` to disable overwrite error level.
     *  - `fixtureStrategy` - Defaults to TruncateStrategy. Can be set to any class implementing FixtureStrategyInterface.
     */
    'TestSuite' => [
        'errorLevel' => null,
        'fixtureStrategy' => null,
    ],
];
