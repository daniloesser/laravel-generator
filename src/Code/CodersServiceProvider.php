<?php

namespace CliGenerator\Code;

use CliGenerator\Support\Classify;
use CliGenerator\Code\Model\Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use CliGenerator\Code\Console\CodeModelsCommand;
use CliGenerator\Code\Model\Factory as ModelFactory;

class CodeServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $viewPath = __DIR__ . '/../resources/views';
        $this->loadViewsFrom($viewPath, 'cli-generator');

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__ . '/../../config/cli-generator.php' => config_path('cli-generator.php'),
                ],
                'cli-generator'
            );


            $this->commands(
                [
                    CodeModelsCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerModelFactory();
    }

    /**
     * Register Model Factory.
     *
     * @return void
     */
    protected function registerModelFactory()
    {
        $viewPath = __DIR__ . '/../resources/views';
        $this->loadViewsFrom($viewPath, 'cli-generator');


        $this->app->singleton(
            ModelFactory::class,
            function ($app) {
                return new ModelFactory(
                    $app->make('db'),
                    $app->make(Filesystem::class),
                    new Classify(),
                    new Config($app->make('config')->get('cli-generator')),
                    $app['view']
                );
            }
        );
    }

    /**
     * @return array
     */
    public function provides()
    {
        return [ModelFactory::class];
    }
}
