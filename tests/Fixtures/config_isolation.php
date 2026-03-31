<?php

namespace TestApp;

class UserService
{
    public const MAX_USERS = 100;
    private string $name;
    private int $count = 0;

    public function getName(): string
    {
        return $this->name;
    }

    public static function create(string $name): self
    {
        $instance = new self();
        $instance->name = $name;
        $instance->count = 1;
        return $instance;
    }
}

function helper(): int
{
    return 42;
}

$svc = UserService::create("test");
echo $svc->getName() . "\n";
echo UserService::MAX_USERS . "\n";
echo helper() . "\n";
