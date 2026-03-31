<?php

namespace MyPlugin;

class Schema
{
    public const DB_VERSION = 5;

    public static function getVersion(): int
    {
        return self::DB_VERSION;
    }
}

class Queue
{
    private const HOOK = 'my_cron_hook';
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getHook(): string
    {
        return self::HOOK;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

$version = Schema::getVersion();
$queue = new Queue("main");
echo $version . "\n";
echo $queue->getHook() . "\n";
echo $queue->getName() . "\n";
