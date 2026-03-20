<?php
/**
 * Plugin Name: Fixture Min PHP 8.1
 */

class FixtureMinPhp81Plugin
{
    public readonly string $slug;

    public function __construct(string $slug)
    {
        $this->slug = $slug;
    }
}

