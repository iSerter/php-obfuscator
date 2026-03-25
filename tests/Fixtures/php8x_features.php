<?php

namespace Test8x;

#[\Attribute]
class MyAttribute {
    public function __construct(public string $value) {}
}

enum MyEnum: string {
    case CaseA = 'a';
    case CaseB = 'b';
    
    public function label(): string {
        return match($this) {
            self::CaseA => 'Label A',
            self::CaseB => 'Label B',
        };
    }
}

interface MyInterface {
    public function doSomething(): void;
}

trait MyTrait {
    public function traitMethod(): void {
        echo "Trait method\n";
    }
}

class MyClass implements MyInterface {
    use MyTrait;

    public const string MY_CONST = 'const_value';
    public static string $staticProp = 'static';

    public readonly string $readOnlyProp;

    public function __construct(
        public string $promotedProp,
        private string $asymmetricProp = 'default'
    ) {
        $this->readOnlyProp = 'readonly';
    }

    #[MyAttribute(value: 'attr_val')]
    public function doSomething(): void {
        echo "Doing something with " . $this->promotedProp . "\n";
    }

    public function testEnums(MyEnum $e): string {
        return $e->label();
    }
}

$obj = new MyClass(promotedProp: 'hello');
$obj->doSomething();
echo MyClass::MY_CONST . "\n";
echo MyClass::$staticProp . "\n";
echo $obj->testEnums(MyEnum::CaseA) . "\n";
