<?php

declare(strict_types=1);

namespace Cortex\Pages\Providers;

use Cortex\Pages\Models\Page;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Rinvex\Support\Traits\ConsoleTools;
use Illuminate\Contracts\Events\Dispatcher;
use Cortex\Pages\Console\Commands\SeedCommand;
use Cortex\Pages\Console\Commands\InstallCommand;
use Cortex\Pages\Console\Commands\MigrateCommand;
use Cortex\Pages\Console\Commands\PublishCommand;
use Cortex\Pages\Console\Commands\RollbackCommand;
use Illuminate\Database\Eloquent\Relations\Relation;

class PagesServiceProvider extends ServiceProvider
{
    use ConsoleTools;

    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        SeedCommand::class => 'command.cortex.pages.seed',
        InstallCommand::class => 'command.cortex.pages.install',
        MigrateCommand::class => 'command.cortex.pages.migrate',
        PublishCommand::class => 'command.cortex.pages.publish',
        RollbackCommand::class => 'command.cortex.pages.rollback',
    ];

    /**
     * Register any application services.
     *
     * This service provider is a great spot to register your various container
     * bindings with the application. As you can see, we are registering our
     * "Registrar" implementation here. You can add your own bindings too!
     *
     * @return void
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(realpath(__DIR__.'/../../config/config.php'), 'cortex.pages');

        // Bind eloquent models to IoC container
        $this->app['config']['rinvex.pages.models.page'] === Page::class
        || $this->app->alias('rinvex.pages.page', Page::class);

        // Register console commands
        $this->registerCommands($this->commands);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Router $router, Dispatcher $dispatcher): void
    {
        // Bind route models and constrains
        $router->pattern('page', '[a-zA-Z0-9-_]+');
        $router->model('page', config('rinvex.pages.models.page'));

        // Map relations
        Relation::morphMap([
            'page' => config('rinvex.pages.models.page'),
        ]);

        // Load resources
        $this->loadRoutesFrom(__DIR__.'/../../routes/web/adminarea.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web/frontarea.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web/managerarea.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'cortex/pages');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'cortex/pages');
        ! $this->autoloadMigrations('cortex/pages') || $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->app->runningInConsole() || $dispatcher->listen('accessarea.ready', function ($accessarea) {
            ! file_exists($menus = __DIR__."/../../routes/menus/{$accessarea}.php") || require $menus;
            ! file_exists($breadcrumbs = __DIR__."/../../routes/breadcrumbs/{$accessarea}.php") || require $breadcrumbs;
        });

        // Publish Resources
        $this->publishesLang('cortex/pages', true);
        $this->publishesViews('cortex/pages', true);
        $this->publishesConfig('cortex/pages', true);
        $this->publishesMigrations('cortex/pages', true);
    }
}
