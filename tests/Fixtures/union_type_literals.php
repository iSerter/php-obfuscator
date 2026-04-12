<?php

namespace SG_Lead_Manager;

class Result
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

class FeatureManager
{
    // Variables named after PHP reserved words — these must not
    // cause the corresponding keywords/constants to be scrambled.
    private $true = 'yes';
    private $false = 'no';
    private $null = 'empty';
    private $self = 'me';

    public function check(string $feature): Result|true
    {
        if ($feature === 'enabled') {
            return true;
        }
        return new Result('disabled');
    }

    public function validate(string $input): false|string
    {
        if ($input === '') {
            return false;
        }
        return $input;
    }

    public function find(int $id): null|Result
    {
        if ($id <= 0) {
            return null;
        }
        return new Result("found-$id");
    }

    public function getInstance(): static
    {
        return new static();
    }

    public function getSelf(): self
    {
        return $this;
    }

    public function combo(string $input): Result|true|null
    {
        if ($input === 'ok') {
            return true;
        }
        if ($input === '') {
            return null;
        }
        return new Result($input);
    }
}

// Exercise the code
$fm = new FeatureManager();

$r1 = $fm->check('enabled');
echo ($r1 === true ? 'true' : 'Result') . "\n";

$r2 = $fm->check('disabled');
echo ($r2 instanceof Result ? $r2->getMessage() : 'unexpected') . "\n";

$r3 = $fm->validate('hello');
echo ($r3 === false ? 'false' : $r3) . "\n";

$r4 = $fm->validate('');
echo ($r4 === false ? 'false' : 'unexpected') . "\n";

$r5 = $fm->find(0);
echo ($r5 === null ? 'null' : 'unexpected') . "\n";

$r6 = $fm->find(42);
echo ($r6 instanceof Result ? $r6->getMessage() : 'unexpected') . "\n";

echo ($fm->getSelf() instanceof FeatureManager ? 'self-ok' : 'unexpected') . "\n";
echo ($fm->getInstance() instanceof FeatureManager ? 'static-ok' : 'unexpected') . "\n";

$r7 = $fm->combo('ok');
echo ($r7 === true ? 'true' : 'unexpected') . "\n";

$r8 = $fm->combo('');
echo ($r8 === null ? 'null' : 'unexpected') . "\n";

$r9 = $fm->combo('test');
echo ($r9 instanceof Result ? $r9->getMessage() : 'unexpected') . "\n";
