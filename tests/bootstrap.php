<?php declare(strict_types=1);

/**
 * Bootstrap file for module tests.
 *
 * Use Common module Bootstrap helper for test setup.
 */

require dirname(__DIR__, 3) . '/modules/Common/tests/Bootstrap.php';

// The module works without SpamGuard. The optional SpamGuard is installed when
// present, so run the suite without it too:
// TEST_SKIP_OPTIONAL_MODULES=1 vendor/bin/phpunit -c modules/ContactUs/phpunit.xml
\CommonTest\Bootstrap::bootstrap(
    [
        'Common',
        '?SpamGuard',
        'ContactUs',
    ],
    'ContactUsTest',
    __DIR__ . '/ContactUsTest'
);
