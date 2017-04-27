<?php

namespace App\Providers;
Use Log;
use Illuminate\Support\ServiceProvider;
//use App\Http\Controllers\PuertoController;
class PuertoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    private static $_instance = null;
    private static $cadena;
    static  $moviles_activos = null;
    /*private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    private function __construct() { 
                
    }*/
    public function register(){  
       /* $this->app->singleton('Puerto', function(){
            return new PuertoServiceProvider();
        });*/
    }
   //public function register(){ } 
    private static function setCadena($paquete){
        self::$cadena=$paquete;
    }
    public static function setMovilesActivos(   ){
        self::$moviles_activos="movil1,movil3,movil-ero";
    }
    public function GreetMe(){
        Log::error('<br />Hello, this method is called by using a singleton object..');
    }
    public static function getImei($paquete){
        self::setCadena($paquete);
        $imei="";
        if(self::$cadena!=""){
            $arrCadena = explode(";",self::$cadena); 
            $imei = $arrCadena[0];
            Log::info("imei en getImei:".$imei);
        }
        return $imei;
    }
    /*public function register(){  
        $this->app->singleton('Puerto', function(){
            return new PueroController();
        });

        // Shortcut so developers don't need to add an Alias in app/config/app.php
        $this->app->booting(function()
        {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Puerto', 'CLG\Facility\Facades\FacilityFacade');
        });
    }*/
}

    
