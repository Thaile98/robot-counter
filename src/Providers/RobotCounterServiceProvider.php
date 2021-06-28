<?php
/**
 * Created by PhpStorm.
 * User: Thaile
 * Date: 25/06/21
 * Time: 10:40 AM
 */

namespace Workable\RobotCounter\Providers;

use Illuminate\Support\ServiceProvider;
use Workable\RobotCounter\Console\Commands\RobotCounterReportCommand;
use Workable\RobotCounter\Http\Middleware\RobotCounterMiddleware;

class RobotCounterServiceProvider extends ServiceProvider
{
    public function boot(\Illuminate\Routing\Router $router)
    {
        $router->aliasMiddleware('robot.counter', RobotCounterMiddleware::class);
        $this->registerConfig();
        $this->registerCommand();
    }

    public function register()
    {
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__ . '/../config/robot_counter.php' => config_path('robot_counter.php')
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../config/robot_counter.php', 'robot_counter'
        );
    }

    protected function registerCommand()
    {
        $this->commands([
            RobotCounterReportCommand::class
        ]);
    }

}

