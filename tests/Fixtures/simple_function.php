<?php

/**
 * Simple function fixture
 */
function hello(string $name): string
{
    // Return greeting
    $greeting = "Hello, " . $name . "!";
    return $greeting;
}

echo hello("World");
