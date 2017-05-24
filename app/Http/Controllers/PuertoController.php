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
            $arrCampos = self::changeString2array(self::$cadena);
            if(self::validateImei($arrCampos['IMEI'])){
                Log::info("imei valido");
                self::store($arrCampos);
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
    public static function changeString2array($cadena){
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
        //maximo 15 caracteres numericos
        Log::info("lega este imei a comprobacion:".$imei);
        if(preg_match("/^[0-9]{15,15}$/", $imei)) {
            return true;
        }else{
            return false;
        } 
    }
    public static function store($report) {
        $gprmcData  = explode(",",$report['GPRMC']);
        $ioData     = explode(",",$report['IO']);
        $panico     = str_replace("I0", "",$ioData[0] );
        $dcxData    = explode(",",$report['DCX']);
        $preData    = explode(",",$report['PRE']);
        $dadData    = explode(",",$report['DAD']);
        $frData     = explode(",",$report['FR']);
        $lacData    = explode(",",$report['LAC']);
        $mcpData    = explode(",",$report['MCP']);
        $fecha      = self::ddmmyy2yyyymmdd($gprmcData[8],$gprmcData[0]);
        $evento = GprmcEntrada::create([
            'imei'=>$report['IMEI'],'gprmc'=>$report['GPRMC'],'fecha_mensaje'=>'{$fecha}','latitud'=>$gprmcData[2],
            'longitud'=>$gprmcData[4],'velocidad'=>$gprmcData[6],'rumbo'=>$gprmcData[7],'io'=>$report['IO'],
            'panico'=>$panico,'desenganche'=>'0','encendido'=>'0','corte'=>'0','dcx'=>$report['DCX'],
            'senial'=>$dcxData[0],'tasa_error'=>$dcxData[1],'pre'=>$report['PRE'],'sim_activa'=>$preData[0],
            'sim_roaming'=>$preData[1],'vba'=>$report['VBA'],'voltaje_bateria'=>$report['VBA'],
            'dad'=>$report['DAD'],'fecha_desconexion'=>$dadData[0],
            'cant_desconexiones'=>$dadData[2],'senial_desconexion'=>$dadData[3],'sim_desconexion'=>$dadData[4],
            'roaming_desconexion'=>$dadData[5],'tasa_error_desconexion'=>$dadData[6],'motivo_desconexion'=>$dadData[7],
            'fr'=>$report['FR'],'frecuencia_reporte'=>$frData[0],'tipo_reporte'=>$frData[1],'lac'=>$report['LAC'],
            'cod_area'=>$lacData[0],'id_celda'=>$lacData[1],'kmt'=>$report['KMT'],'km_totales'=>$report['KMT'],
            'odp'=>$report['ODP'],'mts_parciales'=>$report['ODP'],'ala'=>$report['ALA'],'mcp'=>$report['MCP'],
            'cfg_principal'=>$mcpData[0],'cfg_auxiliar'=>$mcpData[1],
            'per'=>$report['PER'] ]);
        return "OK\n";
    }
    public static function ddmmyy2yyyymmdd($fecha,$hora){
        $formatFecha = date("Ymd H:i:s", mktime(substr($hora, 0,2), substr($hora, 2,2), substr($hora, 4,2), substr($fecha, 2,2), substr($fecha, 0,2), substr($fecha, -2,2)));
         Log::info("fecha:".$formatFecha);
         return $formatFecha;
    }

}
