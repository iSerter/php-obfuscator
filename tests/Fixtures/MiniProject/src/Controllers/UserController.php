<?php
namespace MiniProject\Controllers;

use MiniProject\Services\ServiceInterface;

class UserController implements ControllerInterface {
    private ServiceInterface $service;

    public function __construct(ServiceInterface $service) {
        $this->service = $service;
    }

    public function execute(): void {
        echo "Executing " . $this->service->getName() . "\n";
    }
}
