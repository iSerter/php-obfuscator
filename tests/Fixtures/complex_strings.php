<?php

namespace ComplexApp;

class StatusChecker
{
    private string $status;
    private array $data;

    public function __construct(string $status, array $data)
    {
        $this->status = $status;
        $this->data = $data;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function check(): string
    {
        // String comparison — the string value 'active' must survive obfuscation
        if ($this->status === 'active') {
            return 'Status is active';
        } elseif ($this->status === 'pending') {
            return 'Status is pending';
        }
        return 'Status is unknown';
    }

    public function matchStatus(): string
    {
        return match ($this->status) {
            'active' => 'ON',
            'inactive' => 'OFF',
            'pending' => 'WAIT',
            default => 'UNKNOWN',
        };
    }

    public function summarize(): string
    {
        $count = count($this->data);
        $label = $this->status;
        // Interpolated string with variable + property access
        return "Status: {$label}, Items: {$count}";
    }
}

// Array with string keys
$config = [
    'mode' => 'production',
    'debug' => false,
    'name' => 'MyApp',
    'version' => '1.0.0',
];

$checker = new StatusChecker('active', [1, 2, 3]);
echo $checker->check() . "\n";
echo $checker->matchStatus() . "\n";
echo $checker->summarize() . "\n";
echo $config['mode'] . "\n";
echo $config['name'] . "\n";
