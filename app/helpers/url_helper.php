<?php

function base_url(string $path = ''): string
{
    $base = 'http://localhost:8000';
    return $base . '/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    return base_url($path);
}

function asset(string $path = ''): string
{
    return base_url('web/assets/' . ltrim($path, '/'));
}