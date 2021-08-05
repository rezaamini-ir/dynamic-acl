<?php

namespace Iya30n\DynamicAcl\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Router;
use Iya30n\DynamicAcl\Models\Role;
use Illuminate\Support\ServiceProvider;
use Iya30n\DynamicAcl\Http\Middleware\Admin;
use Iya30n\DynamicAcl\Console\Commands\MakeAdmin;
use \Javoscript\MacroableModels\Facades\MacroableModels;

class DynamicAclServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'dynamicACL');

        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $this->publishes([
            __DIR__ . '/../../config/dynamicACL.php' => config_path('dynamicACL.php'),
            __DIR__ . '/../../database/migrations' => database_path('migrations')
        ]);

        $this->registerMacros();

        $this->registerMiddlewares();

        $this->registerCommands();
    }

    private function registerMacros()
    {
        $authModel = config('auth.providers.users.model');

         MacroableModels::addMacro($authModel, 'roles', function () {
             return $this->belongsToMany(Role::class);
        });

        MacroableModels::addMacro($authModel, 'hasPermission', function ($access) {
            // TODO: handle acl types (uri, controller)
            if (in_array($access, config('dynamicACL.ignore_list'))) return true;

            foreach ($this->roles()->get() as $role) {
                $userPermissions = (strpos($access, '.') != false) ?
                    \Arr::dot($role->permissions) :
                    $role->permissions;

                return isset($userPermissions[$access]) || isset($userPermissions['fullAccess']);
            }

            return false;
        });
    }

    private function registerMiddlewares()
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('dynamicAcl', Admin::class);
    }

    private function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeAdmin::class,
            ]);
        }
    }
}
