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
        Log::info("registrando");
        self::setMovilesActivos();
    } 
    private static function setCadena($paquete){
        self::$cadena=$paquete;
    }
    public static function setMovilesActivos(   ){
        //self::$moviles_activos=Movil::with('instalacion')->where('activo',1)->first();
        self::$moviles_activos=Movil::instalados();
    }
    public static function getImei($paquete){
        self::setCadena($paquete);
        self::$moviles_activos= null;
        //self::setMovilesActivos();
        $imei="";
        if(self::$cadena!=""){
            $arrCampos = self::cadenaString2array(self::$cadena);
            if(count(self::$moviles_activos)){
               Log::info("no values");
            }else{
                foreach (self::$moviles_activos as $movil) {
                  Log::info("moviles activos::".$movil->alias);
                }
            }
        }
        return $imei;
    }
    public static function cadenaString2array($cadena){
        $campos    = array();
        $arrCadena = explode(";",self::$cadena); 
        foreach($arrCadena as $campo){
          $arrCampo = explode(",",$campo); 
          $key      = array_shift($arrCampo);
          $datos    = implode(",", $arrCampo);
          $campos[trim($key)]=trim($datos);
        }
        return $campos;
    }
}

    
