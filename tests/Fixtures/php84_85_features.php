<?php

namespace Test84_85;

class MyClass {
    public string $name {
        get {
            return $this->name;
        }
        set(string $value) {
            $this->name = trim($value);
        }
    }

    public function process(string $input): string {
        return $input
            |> trim(...)
            |> strtoupper(...)
            |> ($this->wrap(...));
    }

    private function wrap(string $str): string {
        return "[$str]";
    }

    public function testFiber(): void {
        $fiber = new \Fiber(function(): void {
            \Fiber::suspend('hello');
        });
        $val = $fiber->start();
    }
}

$obj = new MyClass();
$obj->name = "  John  ";
echo $obj->name . "\n";
echo $obj->process(" hello ") . "\n";

