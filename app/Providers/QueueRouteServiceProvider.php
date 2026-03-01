<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Order\Handlers\SendOrderNotificationHandler;
use App\Supports\Queue\QueueRouter;
use Illuminate\Support\ServiceProvider;

final class QueueRouteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueueRouter::class);
    }

    public function boot(): void
    {
        $router = $this->app->make(QueueRouter::class);

        $router->register('notifications', SendOrderNotificationHandler::class);
    }
}
