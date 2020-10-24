<?php namespace FreedomCore\Swagger;

use Illuminate\Support\ServiceProvider;
use FreedomCore\Swagger\Commands\GenerateSwaggerDocumentation;

/**
 * Class SwaggerServiceProvider
 * @package FreedomCore\Swagger
 */
class SwaggerServiceProvider extends ServiceProvider {

    /**
     * @inheritDoc
     * @return void
     */
    public function boot():void {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSwaggerDocumentation::class
            ]);
        }
        $source = __DIR__ . '/../config/swagger.php';

        $this->publishes([
            $source     =>  config_path('swagger.php')
        ]);

        $viewsPath = __DIR__ . '/../resources/views';
        $this->loadViewsFrom($viewsPath, 'swagger');

        $this->publishes([
            $viewsPath => config('swagger.views'),
        ], 'views');

        $this->loadRoutesFrom(__DIR__ . DIRECTORY_SEPARATOR . 'routes.php');

        $this->mergeConfigFrom(
            $source, 'swagger'
        );
    }

}
