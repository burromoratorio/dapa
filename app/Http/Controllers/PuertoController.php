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
use App\GprmcDesconexion;
use App\GprmcAlarma;

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
                switch (self::positionOrDesconect($arrCampos)) {
                    case 'GPRMC':
                        Log::info("Reporte Normal GPRMC");
                        $posicionID=self::storeGprmc($arrCampos);
                        if($posicionID!='0'){
                            self::findAndStoreAlarm($arrCampos,$posicionID);
                        }else{
                            Log::error("Cadena GPRMC vacia");
                        }
                        break;
                    case 'DAD':
                        Log::info("Reporte Desconexion DAD");
                        self::storeDad($arrCampos);
                        break;
                    case 'NODAD':
                        Log::info("Reporte Desconexion Sin Fecha NODAD");
                        break;
                    default:
                        # code...
                        break;
                }
                
                
            }else{
                Log::info("imei invalido");
            }
            /*
            *****Por ahora no uso el listado de moviles********
            if(count(self::$moviles_activos)>0){
               foreach (self::$moviles_activos as $movil) {
                  Log::info("moviles activos::".$movil->alias);
                }
                Log::info("total de moviles:".count(self::$moviles_activos));
            }else{
                Log::info("no values");
            }*/
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
    public static function validateGprmc($gprmc){
        if(count($gprmc)<12){
           return "gprmc invalido, error de cadena";    
        }else{
            return "gprmc valido";
        }
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
    public static function positionOrDesconect($report){
        $typeReport = "";
        if(isset($report["GPRMC"])){
            $typeReport = "GPRMC";
        }elseif (isset($report["DAD"])) {
            $dadData  = explode(",",$report['DAD']);
            $typeReport=(count($dadData)<8)?"NODAD":"DAD";
        }
        return $typeReport;
    }
    public static function ddmmyy2yyyymmdd($fecha,$hora){
        $formatFecha = date("Y-m-d h:i:s", mktime(substr($hora, 0,2), substr($hora, 2,2), substr($hora, 4,2), substr($fecha, 2,2), substr($fecha, 0,2), substr($fecha, -2,2)));
         return $formatFecha;
    }
    /*
        devuelve un array con los datos del inidice buscado
        para generalizar el insert de la cadena
    */
    public static function validateIndexCadena($index,$arrCadena,$totalPieces=0){
        $directString = array("ALA","VBA","KMT","ODP","PER");
        $arrData = array();
        Log::info("indice a buscar:".$index);

        if(isset($arrCadena[$index])){
          if(in_array($index, $directString)){
            $arrData[$index] = $arrCadena[$index];
            Log::info("se formo el arrdata:".$arrData[$index]);
          }else{
            $arrData = explode(",",$arrCadena[$index]); 
            $arrData[$index] = $arrCadena[$index];
          }  
          Log::info("el indice:".$index. "se encontro en la cadena:");
        }else{
            //si el indice a buscar no viene en la cadena entonces preparo el array con null
            Log::info("el indice:".$index. "se encontro NOOOO en la cadena:");
            for($i=0;$i<$totalPieces;$i++){
            $arrData[$i]="NULL";
            }
          $arrData[$index]="NULL";
        }
        return $arrData;        
    }
    /*
        funcion para almacenar un reporte de desconexion
    */
    public static function storeDad($report){
        $dadData    = self::validateIndexCadena("DAD",$report,8);
        $frData     = self::validateIndexCadena("FR",$report,2);
        $lacData    = self::validateIndexCadena("LAC",$report,2);
        $kmtField   = self::validateIndexCadena("KMT",$report);
        $odpField   = self::validateIndexCadena("ODP",$report);
        $fechaDad   = ($dadData[0]!="NULL")?self::ddmmyy2yyyymmdd($dadData[0],"000000"):"NULL";
        $evento = GprmcDesconexion::create([
            'imei'=>$report['IMEI'],'dad'=>$dadData['DAD'],'fecha_desconexion'=>$fechaDad,
            'cant_desconexiones'=>$dadData[2],'senial_desconexion'=>$dadData[3],'sim_desconexion'=>$dadData[4],
            'roaming_desconexion'=>$dadData[5],'tasa_error_desconexion'=>$dadData[6],'motivo_desconexion'=>$dadData[7],
            'fr'=>$frData['FR'],'frecuencia_reporte'=>$frData[0],'tipo_reporte'=>$frData[1],'lac'=>$lacData['LAC'],
            'cod_area'=>$lacData[0],'id_celda'=>$lacData[1],'kmt'=>$kmtField['KMT'],'km_totales'=>$kmtField['KMT'],
            'odp'=>$odpField['ODP'],'mts_parciales'=>$odpField['ODP']
        ]);
        
    }
    /* 
        funcion para almacenar un reporte de posicion
    */
    public static function storeGprmc($report) {
        $errorLog   = "";
        Log::info("lo que trae el GPRMC:::".$report['GPRMC']);
        if($report['GPRMC']!=''){
            $gprmcData  = explode(",",$report['GPRMC']);
            $errorLog   = self::validateGprmc($gprmcData);
            $ioData     = self::validateIndexCadena("IO",$report,2);
            $panico     = str_replace("I0", "",$ioData[0] );
            $dcxData    = self::validateIndexCadena("DCX",$report,2);
            $preData    = self::validateIndexCadena("PRE",$report,2);
            $frData     = self::validateIndexCadena("FR",$report,2);
            $lacData    = self::validateIndexCadena("LAC",$report,2);
            $mcpData    = self::validateIndexCadena("MCP",$report,2);
            $alaField   = self::validateIndexCadena("ALA",$report);
            $perField   = self::validateIndexCadena("PER",$report);
            $kmtField   = self::validateIndexCadena("KMT",$report);
            $vbaField   = self::validateIndexCadena("VBA",$report);
            $odpField   = self::validateIndexCadena("ODP",$report);
            $fecha      = self::ddmmyy2yyyymmdd($gprmcData[8],$gprmcData[0]);
            
            $posicion = GprmcEntrada::create([
                'imei'=>$report['IMEI'],'gprmc'=>$report['GPRMC'],'fecha_mensaje'=>$fecha,'latitud'=>$gprmcData[2],
                'longitud'=>$gprmcData[4],'velocidad'=>$gprmcData[6],'rumbo'=>$gprmcData[7],'io'=>$ioData['IO'],
                'panico'=>$panico,'desenganche'=>'0','encendido'=>'0','corte'=>'0','dcx'=>$dcxData['DCX'],
                'senial'=>$dcxData[0],'tasa_error'=>$dcxData[1],'pre'=>$preData['PRE'],'sim_activa'=>$preData[0],
                'sim_roaming'=>$preData[1],'vba'=>$vbaField['VBA'],'voltaje_bateria'=>$vbaField['VBA'],
                'fr'=>$frData['FR'],'frecuencia_reporte'=>$frData[0],'tipo_reporte'=>$frData[1],'lac'=>$lacData['LAC'],
                'cod_area'=>$lacData[0],'id_celda'=>$lacData[1],'kmt'=>$kmtField['KMT'],'km_totales'=>$kmtField['KMT'],
                'odp'=>$odpField['ODP'],'mts_parciales'=>$odpField['ODP'],'ala'=>$alaField['ALA'],'mcp'=>$mcpData['MCP'],
                'cfg_principal'=>$mcpData[0],'cfg_auxiliar'=>$mcpData[1],
                'per'=>$perField['PER'],'log'=>$errorLog ]);
            return $posicion->id;
        }else{
            return "0";
        }
        
    }
    public static function findAndStoreAlarm($report,$posicionID){
        $alaField   = self::validateIndexCadena("ALA",$report);
        $perField   = self::validateIndexCadena("PER",$report);
        if($alaField['ALA']!="NULL"){
            //entonces vino el campo alarma con datos
            $posicion = GprmcAlarma::create([
            'imei'=>$report['IMEI'],'entrada_id'=>$posicionID,'ala'=>$alaField['ALA'],'per'=>$perField['PER'] ]);
        return $posicion->id;
            Log::info("el campo ala tiene:".$alaField['ALA']."-->Posicion:".$posicionID);
        }else{
            //vino el campo alarma pero vacio
            Log::info("el campo ala tiene:".$alaField['ALA']);
        }
        
        /*
        $dadData    = self::validateIndexCadena("DAD",$report,8);
        $frData     = self::validateIndexCadena("FR",$report,2);
        $lacData    = self::validateIndexCadena("LAC",$report,2);
        $kmtField   = self::validateIndexCadena("KMT",$report);
        $odpField   = self::validateIndexCadena("ODP",$report);
        $fechaDad   = ($dadData[0]!="NULL")?self::ddmmyy2yyyymmdd($dadData[0],"000000"):"NULL";
        $evento = GprmcDesconexion::create([
            'imei'=>$report['IMEI'],'dad'=>$dadData['DAD'],'fecha_desconexion'=>$fechaDad,
            'cant_desconexiones'=>$dadData[2],'senial_desconexion'=>$dadData[3],'sim_desconexion'=>$dadData[4],
            'roaming_desconexion'=>$dadData[5],'tasa_error_desconexion'=>$dadData[6],'motivo_desconexion'=>$dadData[7],
            'fr'=>$frData['FR'],'frecuencia_reporte'=>$frData[0],'tipo_reporte'=>$frData[1],'lac'=>$lacData['LAC'],
            'cod_area'=>$lacData[0],'id_celda'=>$lacData[1],'kmt'=>$kmtField['KMT'],'km_totales'=>$kmtField['KMT'],
            'odp'=>$odpField['ODP'],'mts_parciales'=>$odpField['ODP']
        ]);*/
        
    }
    

}
