<?php

class Item
{
    public string $item = 'prop';

    public function item(): string
    {
        return 'method';
    }
}

$item = new Item();
echo $item->item() . "\n";
echo $item->item . "\n";
