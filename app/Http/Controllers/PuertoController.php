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
use App\GprmcEntrada;
class PuertoController extends BaseController
{

    private static $cadena;
    protected static $moviles_activos = null;
    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    public function __construct($moviles) { 
        self::$moviles_activos=$moviles;   
        Log::info("new de puertoController");     
    } 
    private static function setCadena($paquete){
        self::$cadena=$paquete;
    }
    
    public function analizeReport($paquete){
        self::setCadena($paquete);
        $imei="";
        if(self::$cadena!=""){
            $arrCampos = self::cadenaString2array(self::$cadena);
            if(self::validateImei($arrCampos['GPRMC'])){
                Log::info("imei valido");
            }else{
                Log::info("imei invalido");
            }
            if(count(self::$moviles_activos)>0){
               foreach (self::$moviles_activos as $movil) {
                  //Log::info("moviles activos::".$movil->alias);
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
    public static function validateImei($imei){
        if(preg_match("/^[0-9]+$/", $imei)) {
            return true;
        }else{
            return false;
        } 
    }
    public function store(Request $request) {
        /*$evento = GprmcEntrada::create([
            'imei'=>,'gprmc'=>,'fecha_mensaje'=>,'latitud'=>,'longitud'=>,'velocidad'=>,'rumbo'=>,
            'io'=>,'panico'=>,'desenganche'=>,'encendido'=>,'corte'=>,'dcx'=>,'senial'=>,'tasa_error'=>
            'pre'=>,'sim_activa'=>,'sim_roaming'=>,'vba'=>,'voltaje_bateria'=>,'dad'=>,'fecha_desconexion'=>,
            'cant_desconexiones'=>,'senial_desconexion'=>,'sim_desconexion'=>,'roaming_desconexion'=>,
            'tasa_error_desconexion'=>,'motivo_desconexion'=>,'fr'=>,'frecuencia_reporte'=>,'tipo_reporte'=>
            'lac'=>,'cod_area'=>,'id_celda'=>,'kmt'=>,'km_totales'=>,'odp'=>,'mts_parciales'=>,
            'ala'=>,'mcp'=>,'cfg_principal'=>,'cfg_auxiliar'=>,'per'=>,'log'=>,'gprmc_error_id'=>
        ]);*/
        return "OK\n";
    }

}
