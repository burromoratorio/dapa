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
            return $this->app['Puerto']=new PuertoController($GLOBALS['moviles_activos']);
        });
        
    }
    
    public static function setMovilesActivos(   ){

        if(count(config(['moviles_activos'])>0){
           Log::info("moviles activos>0, no se consulta de nuevo");
        }else{
           self::$moviles_activos=Movil::instalados();
           config(['moviles_activos' => self::$moviles_activos]);
        }
        
    }
   
}

    
