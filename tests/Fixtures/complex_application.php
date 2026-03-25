<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Tests\Fixtures;

interface LoggerInterface
{
    public function log(string $message): void;
}

trait Loggable
{
    public function log(string $message): void
    {
        echo "[LOG] $message\n";
    }
}

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

abstract class BaseService
{
    abstract public function execute(): bool;
}

class ComplexService extends BaseService implements LoggerInterface
{
    use Loggable;

    private readonly string $name;
    private Status $status = Status::Inactive;

    public function __construct(string $name = "Default")
    {
        $this->name = $name;
    }

    public function setStatus(Status $status): void
    {
        $this->status = $status;
    }

    public function execute(): bool
    {
        $this->log("Executing service: {$this->name}");

        $data = [1, 2, 3, 4, 5];
        $filtered = array_filter($data, fn ($n) => $n % 2 === 0);

        foreach ($filtered as $val) {
            echo "Processed: $val\n";
        }

        $result = match($this->status) {
            Status::Active => true,
            Status::Inactive => false,
        };

        echo "Result: " . ($result ? 'Success' : 'Failure') . "\n";

        return $result;
    }

    public function testNamedArgs(int $a, string $b): void
    {
        echo "Args: $a, $b\n";
    }
}

$service = new ComplexService(name: "AppService");
$service->setStatus(Status::Active);
$service->execute();
$service->testNamedArgs(b: "hello", a: 42);

// Closure and arrow functions
$anon = function ($x) {
    return $x * 2;
};
echo "Anon: " . $anon(10) . "\n";

$arrow = fn ($y) => $y + 5;
echo "Arrow: " . $arrow(5) . "\n";
