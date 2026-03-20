<?php
/**
 * Plugin Name: Fixture Min PHP 7.1
 */

function fixture_min_php_71_slug(?string $slug): ?string
{
    return $slug === null ? null : strtolower(trim($slug));
}

