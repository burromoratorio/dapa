<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PuertoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(){  
        $this->app->singleton('Puerto', function(){
            return new PueroController();
        });

        // Shortcut so developers don't need to add an Alias in app/config/app.php
        /*$this->app->booting(function()
        {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Puerto', 'CLG\Facility\Facades\FacilityFacade');
        });*/
    }
}

    
