<?php

function intime_test_verification_active(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if (in_array($host, ['localhost', '127.0.0.1'], true) || str_starts_with($host, 'localhost:')) {
        return true;
    }

    return (getenv('INTIME_TEST_VERIFICATION') ?: '') === '1';
}
