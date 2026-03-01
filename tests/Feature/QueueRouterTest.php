<?php

declare(strict_types=1);

use App\Supports\Queue\Abstracts\AbstractQueueHandler;
use App\Supports\Queue\QueueMessage;
use App\Supports\Queue\QueueRouter;

it('registers and resolves a handler for a queue', function () {
    $router = new QueueRouter();
    $router->register('orders', FakeRouterHandler::class);

    expect($router->resolve('orders'))->toBe(FakeRouterHandler::class);
});

it('throws exception when resolving an unregistered queue', function () {
    $router = new QueueRouter();

    $router->resolve('unregistered');
})->throws(InvalidArgumentException::class, 'No handler registered for queue [unregistered].');

it('overwrites a previously registered handler for the same queue', function () {
    $router = new QueueRouter();
    $router->register('orders', FakeRouterHandler::class);
    $router->register('orders', AnotherFakeRouterHandler::class);

    expect($router->resolve('orders'))->toBe(AnotherFakeRouterHandler::class);
});

class FakeRouterHandler extends AbstractQueueHandler
{
    protected function process(QueueMessage $message): void {}
}

class AnotherFakeRouterHandler extends AbstractQueueHandler
{
    protected function process(QueueMessage $message): void {}
}
