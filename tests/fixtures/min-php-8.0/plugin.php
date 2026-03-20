<?php
/**
 * Plugin Name: Fixture Min PHP 8.0
 */

class FixtureMinPhp80Plugin
{
    public function __construct(
        private string $slug
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }
}

