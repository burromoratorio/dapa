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
    public static function getImei($paquete){
        self::setCadena($paquete);
        $imei="";
        if(self::$cadena!=""){
            $arrCampos = self::cadenaString2array(self::$cadena);
            $jsonCadena= json_encode($arrCampos);
            Log::info("cadena pasada a json:".$jsonCadena);
            /*foreach (self::$moviles_activos as $movil) {
               Log::info("moviles activos::".$movil->alias);
            }*/
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
          /*foreach ($arrCampo as $dato) {
          
          }
           Log::info("deglose del campo:{".$key.":".$datos."}"); 
          */
         
        }
        return $campos;
    }
}

    
