<?php

class ReadmeTxtMatchPlugin
{
    private string $slug;

    public function __construct(string $slug)
    {
        $this->slug = $slug;
    }
}
