<?php

function isActive(string $path): string
{
    return str_contains($_SERVER['REQUEST_URI'], $path) ? 'active' : '';
}