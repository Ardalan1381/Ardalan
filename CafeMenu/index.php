<?php

/*
 * Shared-hosting entry point.
 *
 * Use this only when the host forces the document root to be the project
 * root (typical cPanel/DirectAdmin subdomain setups) instead of public/.
 * It simply hands the request to the real front controller.
 *
 * Do NOT add declare(strict_types=1) to this file below any other line: PHP
 * requires a declare() to be the very first statement, and placing it after
 * a `use`, `define()` or `require` is a fatal error ("strict_types
 * declaration must be the very first statement in the script"). It is not
 * needed here at all.
 */

require __DIR__.'/public/index.php';
