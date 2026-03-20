<?php
/**
 * Plugin Name: Fixture Commented PHP 8.4
 */

class FixtureCommentedPhp84Plugin
{
    //public private(set) string $slug;

    public function slug($slug)
    {
        return strtolower(trim($slug));
    }
}
