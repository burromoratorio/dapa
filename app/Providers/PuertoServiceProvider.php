<?php

namespace App\Providers;
Use Log;
use Illuminate\Support\ServiceProvider;
//use App\Http\Controllers\PuertoController;
class PuertoServiceProvider extends ServiceProvider
{
   
    private static $_instance = null;
    private static $cadena;
    static  $moviles_activos = null;
    
    public function register(){ } 
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
    
}

    
