<?php
/**
 * Plugin Name: Fixture Docblock PHP 8.4
 *
 * Example:
 * public private(set) string $slug;
 */

class FixtureDocblockPhp84Plugin
{
    public function slug($slug)
    {
        return strtolower(trim($slug));
    }
}

