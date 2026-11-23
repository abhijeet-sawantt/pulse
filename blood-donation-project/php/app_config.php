<?php
declare(strict_types=1);

function setting(string $name, string $default): string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

define('PULSE_DB_HOST', setting('PULSE_DB_HOST', 'localhost'));
define('PULSE_DB_PORT', setting('PULSE_DB_PORT', '3307'));
define('PULSE_DB_NAME', setting('PULSE_DB_NAME', 'pulse_db'));
define('PULSE_DB_USER', setting('PULSE_DB_USER', 'root'));
define('PULSE_DB_PASSWORD', setting('PULSE_DB_PASSWORD', ''));
define('PULSE_BLOOD_WAIT_DAYS', (int) setting('PULSE_BLOOD_WAIT_DAYS', '56'));
define('PULSE_PLASMA_WAIT_DAYS', (int) setting('PULSE_PLASMA_WAIT_DAYS', '14'));
define('PULSE_DONATION_POINTS', (int) setting('PULSE_DONATION_POINTS', '50'));
