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
/*DDBB Primitiva*/
use App\GprmcEntrada;
use App\GprmcDesconexion;
/*DDBB Principal*/
use App\Posiciones;
use App\Alarmas;
Use App\EstadosSensores;
/*Helpers*/
use App\Helpers\MemVar;
use GuzzleHttp\Client;

class PuertoController extends BaseController
{

    private static $cadena;
    protected static $moviles_activos = null;
    const OFFSET_LATITUD= 2;
    const OFFSET_NS     = 3;
    const OFFSET_LONGITUD= 4;
    const OFFSET_EW     = 5;
    const OFFSET_VELOCIDAD= 6;
    const OFFSET_RUMBO  = 7;
    private static $modoArr      = [0=>"RESET",1=>"NORMAL",2=>"CORTE",3=>"BLOQUEO DE INHIBICIÓN",4=>"ALARMA"];
    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    public function __construct($moviles) { 
        self::$moviles_activos=$moviles;   
        
    } 
    private static function setCadena($paquete){
        self::$cadena=$paquete;
    }
    
    public function analizeReport($paquete,$movil){
        self::setCadena($paquete);
        $imei="";
        if(self::$cadena!=""){
            $arrCampos = self::changeString2array(self::$cadena);
            if(self::validateImei($arrCampos['IMEI'])){
                switch (self::positionOrDesconect($arrCampos)) {
                    case 'GPRMC':
                        Log::info("Normal GPRMC");
                        $posicionID=self::storeGprmc($arrCampos,$movil);
                        if($posicionID!='0'){
                           /*no guardo las alarmas en dbPrimaria self::findAndStoreAlarm($arrCampos,$posicionID);*/
                            $imei="OK";
                        }else{
                            Log::error("Cadena GPRMC vacia");
                            $imei="error";
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
                Log::info("imei invalido:".$arrCampos['IMEI']);
            }
        }
        return $imei;
    }
    public static function changeString2array($cadena){
        $campos    = array();
        $arrCadena = explode(";",$cadena); 
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
            Log::error("GPRMC - Numero de parametros incorrecto:".implode(',',$gprmc) );
            return false;    
        }else{
            return true;
        }
    }
    public static function validezReporte($imei,$fecha,$velocidad,$fr){
        $shmidPos       = MemVar::OpenToRead('posiciones.dat');
        $posicionesMC   = [];
        $frArr          = explode(',',$fr); 
        Log::info(print_r($frArr, true));
        if($shmidPos == '0'){
            Log::info("entrando donde no existe memoria--debe entrar solo una vez");
            $posicion   = ["imei"=>$imei,"fecha"=>$fecha,"velocidad"=>$velocidad];
            array_push($posicionesMC, $posicion);
            $memvar     = MemVar::Instance('posiciones.dat');
            $enstring   = json_encode($posicionesMC);
            $largo      = (int)strlen($enstring);
            Log::info("Largo:::".$largo);
            $memvar->init('posiciones.dat',$largo);
            $memvar->setValue( $enstring );
            $shmid      = MemVar::OpenToRead('posiciones.dat');
            MemVar::initIdentifier($shmid);
            $memoPos    = MemVar::GetValue();
            Log::info($memoPos);
        }else{
            MemVar::initIdentifier($shmidPos);
            $memoPos    = MemVar::GetValue();
            Log::info("Posiciones en MC--".$memoPos);
            $posArr     = json_decode($memoPos);
            $index  = 0;
            foreach ($posArr as $key => $value) {
                //si es un reporte siguiente para el movil---
                if($value->imei==$imei && $fecha > $value->fecha){
                    Log::info("fecha anteior:".$value->fecha."fecha reporte:".$fecha);
                    Log::info("los datos, velocAnterior:".$value->velocidad." velocActual:".$velocidad." FR:".$frArr[0]);
                    //evaluo si paso de detenido a movimiento
                    if( $value->velocidad<5 && $velocidad>8 && $frArr[0]<=120 ){
                        Log::info("movil paso de detenido a movimiento");
                        $index  = $key;
                        break;
                    }
                    //movil pasó de movimiento a detenido
                    if( $value->velocidad>8 && $velocidad<5 && $frArr[0]>120 ){
                        Log::info("movil paso de movimiento a detenido");
                        $index  = $key;
                        break;
                    }
                    //con alguno de ess if voy a eliminar la posicion y agregar una nueva
                    /*$posicion   = ["imei"=>$value->imei,"fecha"=>$value->fecha,"velocidad"=>$value->velocidad];
                    array_push($posicionesMC, $posicion);   */
                }elseif($value->imei!=$imei){
                    //ver que pasa si la fecha no da en el if
                    Log::info("fecha de reporte anterior al guardado en memoria...no lo evaluo");
                    $posicion   = ["imei"=>$imei,"fecha"=>$fecha,"velocidad"=>$velocidad];
                    array_push($posicionesMC, $posicion);                    
                }
                
                //array_push($posicionesMC, $value);
            }
            //si encontre y complio con algun if-->elimino el registro
            unset($posicionesMC[$index]);
            Log::info(print_r($posicionesMC, true));
            MemVar::Eliminar( 'posiciones.dat' );
            /*$memvar     = MemVar::Instance('posiciones.dat');
            $enstring   = json_encode($posicionesMC);
            $largo      = (int)strlen($enstring);
            Log::info("Largo:::".$largo);
            $memvar->init('posiciones.dat',$largo);
            $memvar->setValue( $enstring );
            */
            Log::error("sha existe el segmento de memoria");
        }
        
    }
    /*maximo 15 caracteres numericos*/
    public static function validateImei($imei){
        if(preg_match("/^[0-9]{15,15}$/", $imei)) {
            return true;
        }else{
            Log::info("IMEI invalido:".$imei);
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
        $nuevafecha = strtotime ( '-3 hours' , strtotime ( $formatFecha ) ) ;
        $nuevafecha = date ( 'Y-m-d h:i:s' , $nuevafecha );
        return $nuevafecha;
    }
    /*
        devuelve un array con los datos del inidice buscado
        para generalizar el insert de la cadena
    */
    public static function validateIndexCadena($index,$arrCadena,$totalPieces=0){
        $directString = array("ALA","VBA","KMT","ODP","PER");
        $arrData = array();
        //Log::info("indice a buscar:".$index);
        if(isset($arrCadena[$index])){
          if(in_array($index, $directString)){
            $arrData[$index] = $arrCadena[$index];
            //Log::info("se formo el arrdata:".$arrData[$index]);
          }else{
            $arrData = explode(",",$arrCadena[$index]); 
            $arrData[$index] = $arrCadena[$index];
          }  
        }else{
            //si el indice a buscar no viene en la cadena entonces preparo el array con null
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
    public static function storeGprmc($report,$movil) {
        /*en db PRIMARIA agrego el pid y los caracteres GPRMC en el campo
        Ademas agrego los encabezados a cada campo ej: ALA,NSD..FR,60,0 =>>ALA y FR */
        $respuesta  = "0";
        $pid        = getmypid();
        $sec_pid    = rand(0,1000);
        $errorLog   = "";
        Log::info("Validando GPRMC...".$report['GPRMC']);
        if($report['GPRMC']!=''){
            $gprmcData  = explode(",",$report['GPRMC']);
            $gprmcVal   = self::validateGprmc($gprmcData);
            if($gprmcVal){
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
                $validezReporte = self::validezReporte($report['IMEI'],$fecha,$gprmcData[6],$frData['FR']);
                $posicionGP = GprmcEntrada::create([
                    'imei'=>$report['IMEI'],'gprmc'=>'GPRMC,'.$report['GPRMC'],'pid'=>$pid,'sec_pid'=>$sec_pid,
                    'fecha_mensaje'=>$fecha,'latitud'=>$gprmcData[2],'longitud'=>$gprmcData[4],'velocidad'=>$gprmcData[6],
                    'rumbo'=>$gprmcData[7],'io'=>'IO,'.$ioData['IO'],'panico'=>$panico,'desenganche'=>'0','encendido'=>'0',
                    'corte'=>'0','dcx'=>'DCX,'.$dcxData['DCX'],'senial'=>$dcxData[0],'tasa_error'=>$dcxData[1],
                    'pre'=>'PRE,'.$preData['PRE'],'sim_activa'=>$preData[0],'sim_roaming'=>$preData[1],'vba'=>$vbaField['VBA'],
                    'voltaje_bateria'=>$vbaField['VBA'],'fr'=>'FR,'.$frData['FR'],'frecuencia_reporte'=>$frData[0],
                    'tipo_reporte'=>$frData[1],'lac'=>'LAC,'.$lacData['LAC'],'cod_area'=>$lacData[0],'id_celda'=>$lacData[1],
                    'kmt'=>'KMT,'.$kmtField['KMT'],'km_totales'=>$kmtField['KMT'],'odp'=>'ODP,'.$odpField['ODP'],
                    'mts_parciales'=>$odpField['ODP'],'ala'=>'ALA,'.$alaField['ALA'],'mcp'=>$mcpData['MCP'],
                    'cfg_principal'=>$mcpData[0],'cfg_auxiliar'=>$mcpData[1],'per'=>$perField['PER'],'log'=>'cadena valida' ]);
                $respuesta      = $posicionGP->pid;
                $rumbo_id       = self::Rumbo2String( $gprmcData[7] );
                /*Cambios de estados FUNCIONA descomentar cuando se use
                if($perField['PER']=='NULL'){
                    $info       = self::ModPrecencia($ioData['IO']);
                    Log::error("El info:".$info['mod_presencia']);
                }else{
                    $info        = self::AnalPerifericos($perField['PER']); 
                    Log::error("El info IOM:".$info['mod_presencia']);
                    $sensorEstado   = self::getSensores(351687030222110);
                    if($sensorEstado){
                        $arrPeriferico  = explode(',',$perField['PER']);
                        $arrayMCIom     = str_split($sensorEstado->iom);
                        $arrayGPRMCIom  = str_split($arrPeriferico[1]);
                        if($arrayMCIom[3]!=$arrayGPRMCIom[3]){//desenganche
                            Log::info("informo cambio de estado en desenganche:".$arrayMCIom[3]."->".$arrayGPRMCIom[3]);
                        }
                        if($arrayMCIom[5]!=$arrayGPRMCIom[5]){//compuerta
                            Log::info("informo cambio de estado en desenganche:".$arrayMCIom[5]."->".$arrayGPRMCIom[5]);
                        }
                        Log::info("El sensor IOM:".$sensorEstado->iom."..estado del Periferico:".$arrPeriferico[1]);
                        
                    }
                }*/
                if($perField['PER']=='NULL'){
                    $info       = self::ModPrecencia($ioData['IO']);
                }else{
                    $info        = self::AnalPerifericos($perField['PER']); 
                }
                $arrInfoGprmc   = self::Gprmc2Data($gprmcData);
                //Log::error(print_r($movil_id, true));
                //cmd_id=65/50 si es pos, cmd_id=49 si es evento o alarma
                $posicion = Posiciones::create(['movil_id'=>intval($movil->movilOldId),'cmd_id'=>65,
                                'tipo'=>0,'fecha'=>$fecha,'rumbo_id'=>$arrInfoGprmc['rumbo'],
                                'latitud'=>$arrInfoGprmc['latitud'],'longitud'=>$arrInfoGprmc['longitud'],
                                'velocidad'=>$arrInfoGprmc['velocidad'],
                                'valida'=>1,'estado_u'=>$movil->estado_u,'estado_v'=>$info['mod_presencia'],'estado_w'=>0,
                                'km_recorridos'=>$kmtField['KMT'],
                                'ltrs_consumidos'=>$info['ltrs']]);
                if($alaField["ALA"]=="V"){
                    $alarmaVelocidad    = Alarmas::create(['posicion_id'=>$posicion->posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>7,'fecha_alarma'=>$fecha,'falsa'=>0]);
                }
                

            }else{
                $respuesta  = "0";
            }
        }else{
            $respuesta  = "0";
        }
        return $respuesta;
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
    public static function Rumbo2String( $rumbo ){
        $arrRumbo = array(1 =>'Norte',2=>'Noroeste',3=>'Oeste',4=>'Suroeste',5=>'Sur',6=>'Sureste',7=>'Este',8=>'Noreste');
        if (($rumbo > 337.5 && $rumbo <= 22.5) || ($rumbo == 0)){
            $intRumbo = 1;//North
        }else if ($rumbo > 22.5 && $rumbo <= 67.5){
            $intRumbo = 6;//NorthEast
        }else if ($rumbo > 67.5 && $rumbo <= 112.5){
            $intRumbo = 7;//East
        }else if ($rumbo > 112.5 && $rumbo <= 157.5){
            $intRumbo = 6;//SouthEast
        }else if ($rumbo > 157.5 && $rumbo <= 202.5){
            $intRumbo = 5;//South
        }else if ($rumbo > 202.5 && $rumbo <= 247.5){
            $intRumbo = 4;//SouthWest
        }else if ($rumbo > 247.5 && $rumbo <= 292.5){
            $intRumbo = 3;//West
        }else if ($rumbo > 292.5 && $rumbo <= 337.5){
            $intRumbo = 2;//NorthWest
        }else{
            $intRumbo = 1;
        }
        //return $arrRumbo[$intRumbo];
        return $intRumbo;
    }
    public static function AnalPerifericos($cadena){
        //Log::error(print_r($cadena, true));
        Log::info("ingresa por AnalPerifericos porque Per!=NULL");
        $arrPeriferico     = explode(',', $cadena);
        $valorPeriferico   = '';
        $perifericos       = array("ltrs"=>0,"mod_presencia"=>1,"tmg"=>0,"panico"=>0,"desenganche"=>0); 
        switch ($arrPeriferico[0]) {
            case 'CAU':
                $valorPeriferico    = $arrPeriferico[1];
                $valorPeriferico    = intval(($valorPeriferico)*10);
                $perifericos["ltrs"]= $valorPeriferico;
                 break;
            case 'TMG':
                array_shift($arrPeriferico);
                $valorPeriferico    = implode(',',$arrPeriferico);
                $perifericos["tmg"] = $valorPeriferico;
                break;
            case 'IOM':
                $perifericos["mod_presencia"]= $arrPeriferico[3];
                break;
            case 'BIO':
            //falta ejemplo de sebas para armar cadena
                $perifericos["mod_presencia"]= $arrPeriferico[3];
                break;
            default:
                # code...
                break;
        }
        return $perifericos;
    }
    public static function ModPrecencia($arrPrescense){
        Log::info("ingresa por modPresencia porque PER==NULL");
        $IOEstados       = array("ltrs"=>0,"mod_presencia"=>1,"tmg"=>0,"panico"=>0,"desenganche"=>0);
        if($arrPrescense[2]=='O01' || $arrPrescense[2]=='O11'){
            $IOEstados['mod_presencia']   =   2;
        }
        if($arrPrescense[1]=='I00'){
            $IOEstados['panico']   =   1;
        }
        return $IOEstados;
    }
    public static function Gprmc2Data( $arrCadena ){
        //latitud
        $latitud    = self::ConvertirCoordenada( $arrCadena[self::OFFSET_LATITUD], $arrCadena[self::OFFSET_NS] );
        //lingitud
        $longitud   = self::ConvertirCoordenada( $arrCadena[self::OFFSET_LONGITUD], $arrCadena[self::OFFSET_EW] );
        $velocidad  = $arrCadena[self::OFFSET_VELOCIDAD];
        $rumbo      = $arrCadena[self::OFFSET_RUMBO];
        return array(
            'latitud'   => $latitud,
            'longitud'  => $longitud,
            'velocidad' => $velocidad,
            'rumbo'     => self::Rumbo2String($rumbo)
        );
    }
    public static function ConvertirCoordenada( $coord, $hemisphere ) {
        if ($hemisphere == "N" || $hemisphere == "E") // North - East => Positivo
        {
            $signo = 1;
        }else{
            $signo = -1;
        }
        $coord /= 100.0; // Quedan los grados como enteros
        $grados = ((int)($coord)); // Resguarda los grados
        $coord -= $grados; // Le quita los grados
        $coord *= 100.0; // Lo lleva al formato inicial sin los grados
        $coord /= 60; // Lo lleva a decimales de grado
        $coord += $grados; // Le agrega los grados
        $coord *= $signo; // Le pone el signo segun norte o sur
        
        return $coord;
        
    }
    
    public static function binarySearch(Array $arr, $start, $end, $x){
        if ($end < $start)
            return false;
        $mid = floor(($end + $start)/2);
        if ($arr[$mid]->imei == $x) 
            return $arr[$mid];
        elseif ($arr[$mid]->imei > $x) {
            // call binarySearch on [start, mid - 1]
            return self::binarySearch($arr, $start, $mid - 1, $x);
        }else {
            // call binarySearch on [mid + 1, end]
            return self::binarySearch($arr, $mid + 1, $end, $x);
        }
    }
    public static function getSensores($imei) {
       //MemVar::VaciaMemoria();
        Log::error("obteniendo sensores");
        $shmid    = MemVar::OpenToRead('sensores.dat');
        if($shmid=='0'){
            $memoEstados    = self::startupSensores();
            //Log::error(print_r($memoEstados, true));
            //Log::info("crea una vez sensores");
        }else{
            MemVar::initIdentifier($shmid);
            $memoEstados    = MemVar::GetValue();
            $memoEstados    = json_decode($memoEstados);
        }
        /*si encuentro el movil veo el sensor, si difiere al enviado por parametro
        genero un nuevo elemento y lo cargo en el array y en la ddbb
        elimino el elemento anterior del array, limpio y vuelvo a cargar la memoria
        */
        $encontrado     = self::binarySearch($memoEstados, 0, count($memoEstados) - 1, $imei);
        return $encontrado;
        
    }
    public static function startupSensores(){
        $estados  = [];
        $estadosAll = EstadosSensores::orderBy('imei')->get();
        $imeisAll   = EstadosSensores::groupBy('imei')->pluck('imei');
        foreach ($imeisAll as $movilid=>$imei) {
            array_push($estados, $estadosAll->where('imei',$imei)->last() );
        }
        //Log::error(print_r($estados, true));
        $memvar     = MemVar::Instance('sensores.dat');
        $enstring   = json_encode($estados);
        $largo      = (int)strlen($enstring);
        $memvar->init('sensores.dat',$largo);
        $memvar->setValue( $enstring );
        $memoEstados= json_decode($enstring);
        return $memoEstados;
    
    }
}
