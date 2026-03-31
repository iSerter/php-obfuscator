<?php

interface Printable
{
    public function __toString(): string;
}

trait Describable
{
    public function describe(): string
    {
        return "I am describable";
    }
}

enum Color: string
{
    case Red = 'red';
    case Blue = 'blue';
}

class Widget implements Printable
{
    use Describable;

    private string $label;
    private Color $color;

    public function __construct(string $label, Color $color)
    {
        $this->label = $label;
        $this->color = $color;
    }

    public function __toString(): string
    {
        return $this->label . ":" . $this->color->value;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}

$widget = new Widget("btn", Color::Red);
echo $widget . "\n";
echo $widget->describe() . "\n";
echo $widget->getLabel() . "\n";
