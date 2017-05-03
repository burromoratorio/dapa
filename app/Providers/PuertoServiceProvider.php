<?php

namespace App\Providers;
Use Log;
use Illuminate\Support\ServiceProvider;
use stdClass;
use Storage;
use DB;
Use App\Movil;
//use App\Http\Controllers\PuertoController;
class PuertoServiceProvider extends ServiceProvider
{
   
    private static $_instance = null;
    private static $cadena;
    static  $moviles_activos = null;
    
    public function register(){
        self::setMovilesActivos();
    } 
    private static function setCadena($paquete){
        self::$cadena=$paquete;
    }
    public static function setMovilesActivos(   ){
        self::$moviles_activos=Movil::where('activo',1)->get();
        //self::$moviles_activos="movil1,movil3,movil-ero";
    }
    public function GreetMe(){
        Log::error('<br />Hello, this method is called by using a singleton object..');
    }
    public static function getImei($paquete){
        self::setCadena($paquete);
        $imei="";
        if(self::$cadena!=""){
            $jsonCadena= json_encode(self::$cadena);
            $arrCadena = explode(";",self::$cadena); 
            $imei = $arrCadena[0];
            Log::info("cadena pasada a json:".$jsonCadena);
            Log::info("imei en getImei:".$imei);
            /*foreach (self::$moviles_activos as $movil) {
               Log::info("moviles activos::".$movil->alias);
            }*/
        }
        return $imei;
    }
    
}

    
