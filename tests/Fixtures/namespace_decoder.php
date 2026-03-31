<?php

namespace DecoderTest;

class Greeter
{
    public function greet(string $name): string
    {
        return "Hello, " . $name . "!";
    }
}

$g = new Greeter();
echo $g->greet("World") . "\n";
