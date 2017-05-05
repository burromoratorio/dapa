<?php
namespace App\Http\Controllers;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
Use Log;
use stdClass;
use Storage;
use DB;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\MovilController;

class PuertoController extends BaseController
{

    private static $_instance = null;
    private static $cadena;
    static  $moviles_activos = null;
    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    public function construct() { 
        self::setMovilesActivos();   
        Log::info("new de puertoController");     
    } 
    private static function setCadena($paquete){
        self::$cadena=$paquete;
    }
    public static function setMovilesActivos(   ){
        Log::info("entrando a moviles activos");
        //self::$moviles_activos=Movil::with('instalacion')->where('activo',1)->first();
        self::$moviles_activos=Movil::instalados();
    }
    public static function getImei($paquete){
        self::setCadena($paquete);
        //self::$moviles_activos= null;
        //self::setMovilesActivos();
        $imei="";
        if(self::$cadena!=""){
            $arrCampos = self::cadenaString2array(self::$cadena);
            if(count(self::$moviles_activos)>0){
               foreach (self::$moviles_activos as $movil) {
                  Log::info("moviles activos::".$movil->alias);
                }
                Log::info("total de moviles:".count(self::$moviles_activos));
            }else{
                Log::info("no values");
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
/*private static $_instance = null;
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
        Log::info("entrando a moviles activos");
        //self::$moviles_activos=Movil::with('instalacion')->where('activo',1)->first();
        self::$moviles_activos=Movil::instalados();
    }
    public static function getImei($paquete){
        self::setCadena($paquete);
        //self::$moviles_activos= null;
        //self::setMovilesActivos();
        $imei="";
        if(self::$cadena!=""){
            $arrCampos = self::cadenaString2array(self::$cadena);
            if(count(self::$moviles_activos)>0){
               foreach (self::$moviles_activos as $movil) {
                  Log::info("moviles activos::".$movil->alias);
                }
                Log::info("total de moviles:".count(self::$moviles_activos));
            }else{
                Log::info("no values");
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
*/