<?php

namespace Verso\Plugins\NotAProvider;

// Test class that is not a service provider.
class Greeter
{
    public function greet(): string
    {
        return 'Hello from Lisbon';
    }
}
