<?php

class Pipeline
{
    private array $stages = [];

    public function pipe(callable $stage): self
    {
        $this->stages[] = $stage;
        return $this;
    }

    public function process(mixed $payload): mixed
    {
        $result = $payload;
        foreach ($this->stages as $stage) {
            $result = $stage($result);
        }
        return $result;
    }
}

class Calculator
{
    private int $base;

    public function __construct(int $base)
    {
        $this->base = $base;
    }

    public function getMultiplier(int $factor): \Closure
    {
        // Closure capturing $this and parameter
        return function (int $value) use ($factor): int {
            return $value * $factor + $this->base;
        };
    }

    public function getChained(): \Closure
    {
        $base = $this->base;
        // Nested closure: outer captures $base, inner captures $outer's variable
        return function (int $input) use ($base): \Closure {
            $intermediate = $input + $base;
            return function (int $extra) use ($intermediate): int {
                return $intermediate * $extra;
            };
        };
    }
}

// Pipeline with closures
$pipeline = new Pipeline();
$pipeline
    ->pipe(fn(int $x): int => $x * 2)
    ->pipe(fn(int $x): int => $x + 10)
    ->pipe(function (int $x): int {
        return $x - 3;
    });

echo $pipeline->process(5) . "\n";

// Nested closures
$calc = new Calculator(100);
$multiplier = $calc->getMultiplier(3);
echo $multiplier(10) . "\n";

$chained = $calc->getChained();
$inner = $chained(5);
echo $inner(2) . "\n";

// Array operations with callbacks
$numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
$result = array_reduce(
    array_map(fn(int $n): int => $n * $n, array_filter($numbers, fn(int $n): bool => $n % 2 === 0)),
    fn(int $carry, int $item): int => $carry + $item,
    0
);
echo $result . "\n";
