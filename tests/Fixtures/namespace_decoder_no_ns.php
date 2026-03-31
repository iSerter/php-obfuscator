<?php

declare(strict_types=1);

class SimpleGreeter
{
    public function greet(string $name): string
    {
        return "Hi, " . $name . "!";
    }
}

$g = new SimpleGreeter();
echo $g->greet("World") . "\n";
