<?php
/**
 * Plugin Name: Fixture Min PHP 7.3
 */

function fixture_min_php_73_join(...$parts)
{
    return implode('-', $parts);
}

$fixtureMinPhp73Value = fixture_min_php_73_join('plugin', 'slug',);

