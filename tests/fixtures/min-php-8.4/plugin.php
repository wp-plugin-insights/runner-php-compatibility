<?php
/**
 * Plugin Name: Fixture Min PHP 8.4
 */

class FixtureMinPhp84Plugin
{
    public private(set) string $slug;

    public function __construct(string $slug)
    {
        $this->slug = $slug;
    }
}

