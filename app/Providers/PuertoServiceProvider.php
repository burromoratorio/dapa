<?php

namespace App\Providers;
Use Log;
use Illuminate\Support\ServiceProvider;
use stdClass;
use Storage;
//use DB;
Use App\Movil;
use App\Http\Controllers\PuertoController;
class PuertoServiceProvider extends ServiceProvider
{
   
    protected static $moviles_activos = null;
    protected $puerto=null;
    public function register()
    {
        
        $this->app->singleton('moviles', function ($app) {
            return self::$moviles_activos;
        });
         $this->app->singleton('Puerto', function ($app) {
            self::setMovilesActivos();
            return $this->app['Puerto']=new PuertoController(config(['app.moviles_activos']));
        });
        
    }
    
    public static function setMovilesActivos(   ){

        if(count(config(['app.moviles_activos'])>0)) {
           Log::info("moviles activos>0, no se consulta de nuevo");
           Log::info("lo que tiene el config:".config(['app.moviles_activos']));
        }else{
           self::$moviles_activos=Movil::instalados();
           config(['app.moviles_activos' => self::$moviles_activos]);
        }
        
    }
   
}

    
