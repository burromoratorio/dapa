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
Use App\Movil;
Use App\PosicionesHistoricas;
/*Helpers*/
use App\Helpers\MemVar;
use GuzzleHttp\Client;
use App\Helpers\HelpMen;

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
    /*Verifica existencia de segmento de memoria de posiciones
    verifica la existencia de informacion de la ultima velocidad y fecha para el $imei dado
    Verifica si el movil pasó de det->mov y de mov->det
    actualiza estados
    */
    public static function validezReporte($imei,$fecha,$velocidad,$fr,$movil){
        $shmidPos       = MemVar::OpenToRead('posiciones.dat');
        $posicionesMC   = [];
        $frArr          = explode(',',$fr); 
        $update         = 0;
        if($shmidPos == '0'){
            //Log::info("creando segmento de memoria posicionesMC");
            $posicionesMC[$imei]=$fecha."|".$velocidad."|0";
            self::CargarMemoria('posiciones.dat',$posicionesMC);
            $shmid      = MemVar::OpenToRead('posiciones.dat');
            MemVar::initIdentifier($shmid);
            $memoPos    = MemVar::GetValue();
        }else{
            MemVar::initIdentifier($shmidPos);
            $memoPos    = MemVar::GetValue();
            $posArr     = json_decode($memoPos);
            //Log::error(print_r($posArr, true));
            $index      = "-1";
            $encontrado = 0;
            if(!is_null($posArr)){
                if(property_exists($posArr, $imei)){//Log::info("el movil:".$imei." tiene datos en el array de posiciones");
                    $internalInfo   = $posArr->$imei;
                    $arrInternalInfo= explode("|", $internalInfo);
                    //Log::info(print_r($arrInternalInfo,true));
                    Log::info("los datos, velocAnterior:".$arrInternalInfo[1]." velocActual:".$velocidad." FR:".$frArr[0]);
                    //evaluo frecuencia de repo
    /*Movimiento*/  if($frArr[0]<=120){
                        if($arrInternalInfo[1]<=5){//detenido a movimiento
                            Log::info($imei."=>detenido a movimiento");
                            $posArr->$imei  = $fecha."|".$velocidad."|0";
                        }else{//continua en movimiento
                            Log::info($imei."..continua en movimiento");
                        }
    /*detenido*/    }else{
                        if($arrInternalInfo[1]>=8){//movimiento a detenido
                            Log::info($imei."=>movimiento a detenido");
                            $posArr->$imei  = $fecha."|".$velocidad."|0";
                        }else{//continua detenido
                            Log::info($imei."..continua detenido");
                            if($arrInternalInfo[2]=="0"){
                                $posArr->$imei  = $fecha."|".$velocidad."|1";
                                //Log::info("es el segundo reg con veloc 0-->lo inserto");
                            }else{
                                $posArr->$imei  = $fecha."|".$velocidad."|2";
                                Log::info("actualizo fechas, esta detenido hace mas de 2 posiciones");
                                $lastPosition = PosicionesHistoricas::where('movil_id',intval($movil->movilOldId))
                                            ->orderBy('fecha', 'DESC')->first();
                                $posicionAux    = $lastPosition;
                                if(DB::connection()->getDatabaseName()=='moviles'){
                                    config()->set('database.default', 'siac');
                                }
                                PosicionesHistoricas::where('posicion_id',$lastPosition->posicion_id)->delete();
                                
                                DB::table('POSICIONES_HISTORICAS')->insert(['posicion_id'=>$lastPosition->posicion_id,
                                            'movil_id'=>intval($movil->movilOldId),'tipo'=>$lastPosition->tipo,
                                            'rumbo_id'=>$lastPosition->rumbo_id,'fecha'=>$fecha,'velocidad'=>$lastPosition->velocidad,
                                            'latitud'=>$lastPosition->latitud,'longitud'=>$lastPosition->longitud,
                                            'valida'=>1,'km_recorridos'=>$lastPosition->km_recorridos,
                                            'referencia'=>$lastPosition->referencia,'cmd_id'=>$lastPosition->cmd_id,
                                            'estado_u' =>$lastPosition->estado_u,'estado_v' =>$lastPosition->estado_v,
                                            'estado_w' =>$lastPosition->estado_w, 'km_recorridos' =>$lastPosition->km_recorridos,
                                            'ltrs_consumidos' =>$lastPosition->ltrs_consumidos,'ltrs_100' =>$lastPosition->ltrs_100
                                            ]); 
                                config()->set('database.default', 'moviles');
                                $update         = $lastPosition->posicion_id;
                            }
                            
                        }
                    }
                    $posicionesMC   = $posArr;
                }else{//el movil no tiene datos de posiciones->almaceno la info
                    $logcadena ="movil:".$imei." - equipo:".$movil->equipo_id." - no tiene datos de posiciones-->almaceno \r\n";
                    HelpMen::report($movil->equipo_id,$logcadena);
                    $posArr->$imei   = $fecha."|".$velocidad."|0";
                    $posicionesMC   = $posArr;
                }
            }else{
                $posicionesMC[$imei]=$fecha."|".$velocidad."|0";
                //Log::info("le pije del mone::".$imei." fecha:".$fecha."- velocidad:".$velocidad);
            }
            MemVar::Eliminar( 'posiciones.dat' );
            self::CargarMemoria('posiciones.dat',$posicionesMC);
        }
        return $update;
    }
    /*maximo 15 caracteres numericos*/
    public static function validateImei($imei){
        if(preg_match("/^[0-9]{15,15}$/", $imei)) {
            return true;
        }else{
            Log::error("IMEI invalido:".$imei);
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
    public static function ddmmyy2yyyymmdd($fecha,$hora,$movil){
        $formatFecha = date("Y-m-d H:i:s", mktime(substr($hora, 0,2), substr($hora, 2,2), substr($hora, 4,2), substr($fecha, 2,2), substr($fecha, 0,2), substr($fecha, -2,2)));
        $nuevafecha = strtotime ( '-3 hours' , strtotime ( $formatFecha ) ) ;
        //-----aviso a seba de reporte viejo----//
        $fechacompara   = date('Y-m-j H:i:s'); 
        $newDateCompa   = strtotime ( '-10 minute' , strtotime ($fechacompara) ) ; 
        if($nuevafecha<=$newDateCompa){
            $logeo  = "^^^<Reporte Historico>^^^";  
            HelpMen::report($movil->equipo_id,$logeo);
        }
        $nuevafecha = date ( 'Y-m-d H:i:s' , $nuevafecha );
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
        $estadoMovilidad    = 7;//estado normal
        //Log::info("Validando GPRMC...".$report['GPRMC']);
        Log::error($report['GPRMC']);
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
                $fecha      = self::ddmmyy2yyyymmdd($gprmcData[8],$gprmcData[0],$movil);
                if($perField['PER']=='NULL'){
                    $info       = self::ModPrecencia($ioData['IO']);
                }else{
                    $info        = self::AnalPerifericos($perField['PER']); 
                }
                $arrInfoGprmc   = self::Gprmc2Data($gprmcData);
                $validezReporte = self::validezReporte($report['IMEI'],$fecha,$gprmcData[6],$frData['FR'],$movil);
                if($validezReporte>0){
                    Log::info("actualiza hora de posicion en detenido");
                    $posicion               = new Posiciones;
                    $posicion->posicion_id  = $validezReporte;
                    $respuesta              = $validezReporte;
                }else{
                    //Log::info("se inserta nueva posicion");
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
                    //cmd_id=65/50 si es pos, cmd_id=49 si es evento o alarma
                    DB::beginTransaction();
                    try {
                        $odp    = number_format(($odpField['ODP']/1000),2, '.', '');
                        $posicion = Posiciones::create(['movil_id'=>intval($movil->movilOldId),'cmd_id'=>65,
                                        'tipo'=>$frData[1],'fecha'=>$fecha,'rumbo_id'=>$arrInfoGprmc['rumbo'],
                                        'latitud'=>$arrInfoGprmc['latitud'],'longitud'=>$arrInfoGprmc['longitud'],
                                        'velocidad'=>$arrInfoGprmc['velocidad'],
                                        'valida'=>1,'estado_u'=>$movil->estado_u,'estado_v'=>$info['mod_presencia'],'estado_w'=>0,
                                        'km_recorridos'=>$odp,
                                        'ltrs_consumidos'=>$info['ltrs']]);
                        $posicion->save();

                        if($alaField["ALA"]=="V"){
                            $alarmaVelocidad    = Alarmas::create(['posicion_id'=>$posicion->posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>7,'fecha_alarma'=>$fecha,'falsa'=>0]);
                            $estadoMovilidad    = 11;
                        }
                        DB::commit();
                        
                    }catch(\Exception $ex) {
                        DB::rollBack();
                        $errorSolo  = explode("Stack trace", $ex);
                        $logcadena = "Error al procesar posicion en puerto controller".$errorSolo[0]."\r\n";
                        HelpMen::report($movil->equipo_id,$logcadena);
                    }
                }
                //Log::info(print_r($posicion,true));
                if(isset($posicion)){
                    //inserto alarma de panico!!
                    $estadoMovilidad = self::tratarAlarmasIO($ioData,$perField['PER'],$report['IMEI'],$posicion->posicion_id,
                                            $movil,$fecha,$estadoMovilidad);

                    if( $estadoMovilidad==7 ){
                        if($arrInfoGprmc['velocidad']>12){
                            $estadoMovilidad=($movil->estado_u==0)?3:4;//movimiento vacio estado_u=0, otro..movimiento cargado
                        }else{
                            $estadoMovilidad=($movil->estado_u==0)?1:2;//detenido vacio estado_u=0, otro..detenido cargado2
                        }
                    }
                    if(DB::connection()->getDatabaseName()=='moviles'){
                        config()->set('database.default', 'siac');
                        $movilModel = new Movil;
                        $movilModel->setConnection('siac');
                        $updateMovil= $movilModel->where('movil_id','=',intval($movil->movilOldId))
                                    ->update(['latitud'=>$arrInfoGprmc['latitud'],'longitud'=>$arrInfoGprmc['longitud'],
                                            'rumbo_id'=>$arrInfoGprmc['rumbo'],'estado'=>$estadoMovilidad,
                                            'velocidad'=>$arrInfoGprmc['velocidad'],'fecha_ult_posicion'=>$fecha]);
                                       
                    }
                    //DB::commit();
                    config()->set('database.default', 'moviles');
                }

                
                /*Cambios de estados FUNCIONA descomentar cuando se use*/
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
                }
                
                /*********para comunicacion con API NAcho********/
                      
                $movTestings = array(10004, 10006, 10033);

                if (in_array($movil->equipo_id, $movTestings)){
                    Log::info("por enviar al api de nacho el movil:".$movil->equipo_id);
                    $json=  ["movil"=>intval($movil->movilOldId),
                        "point"=> ["type"=>"Point","coordinates"=> [$arrInfoGprmc['longitud'],$arrInfoGprmc['latitud'] ] ],
                        "received"=>$fecha, "speed"=> $arrInfoGprmc['velocidad'], "direction"=>$arrInfoGprmc['rumbo']
                        ];
                        HelpMen::posteaPosicion("operativo/positions",$json);
                }
                /*********para comunicacion con API NAcho********/
            }else{
                $respuesta  = "0";
            }
        }else{
            $respuesta  = "0";
        }
        try{
            DB::disconnect();
        }catch(\Exception $e){
            
        }
        return $respuesta;
    }
    public static function tratarAlarmasIO($ioData,$perField,$imei,$posicion_id,$movil,$fecha,$estadoMovilidad){
        $movilOldId = intval($movil->movilOldId);
        $movil_id   = intval($movil->movil_id);
        //si no tiene posicion_id y es una alarma de panico , informar mail?¡
        if($ioData[0]=="I00"){//ingreso de alarma de panico bit en 0
            $logcadena = "Panico presionado Equipo:".$imei." - Movil:".$movilOldId."\r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
            DB::beginTransaction();
            try {
                $alarmaPanico   = Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>$movilOldId,'tipo_alarma_id'=>1,'fecha_alarma'=>$fecha,'falsa'=>0]);
                $alarmaPanico->save();
                DB::commit();
                }catch (\Exception $ex) {
                    DB::rollBack();
                    $logcadena = "Error al tratar alarmas IO..".$ex;
                    HelpMen::report($movil->equipo_id,$logcadena);
                }
        }
        /*cambios de estado IO alarmas de bateria*/
        $arrPeriferico  = $ioData[1];
        $io             = str_replace("I", "",$ioData[1] );
        $sensorEstado   = self::getSensores($imei);
        if($io=='10'){ 
            $logcadena = "Movil: ".$imei." - Equipo: ".$movil->equipo_id." - funcionando con bateria auxiliar\r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
            $tipo_alarma_id=50;
            $estado_movil_id=13;
            $estadoMovilidad=$estado_movil_id;
        }
        if($io=='11'){//alimentacion ppal 
            $tipo_alarma_id=49;
            $estado_movil_id=14;
        }
        if($sensorEstado){
            if($io!=$sensorEstado->io){ //evaluo cambio de bits de sensor IO
               DB::beginTransaction();
                try {
                    EstadosSensores::where('imei', '=', $imei)->update(array('io' => $io));
                    self::persistSensor($ioData,$imei,$posicion_id,$movil,$fecha,$tipo_alarma_id,$estado_movil_id);
                    DB::commit();
                }catch (\Exception $ex) {
                    DB::rollBack();
                    $logcadena = "Error al tratar alarmas IO..".$ex."\r\n";
                    HelpMen::report($movil->equipo_id,$logcadena);
                }
            }
        }else{    //no se encontró el imei en la tabla de sensores,inserto e informo alarmas 
            if($perField!='NULL'){
                $arrIOM     = explode(',',$perField);
                $perField   = ($arrIOM[0]=='IOM')?$arrIOM[1]:'NULL';
            }
            DB::beginTransaction();
            try {
                EstadosSensores::create(['imei'=>$imei,'movil_id'=>$movil_id,'iom'=>$perField,'io'=>$io]);
                self::persistSensor($ioData,$imei,$posicion_id,$movil,$fecha,$tipo_alarma_id,$estado_movil_id);
                DB::commit();
            }catch (\Exception $ex) {
                DB::rollBack();
                $logcadena = "Error al tratar alarmas IO..".$ex."\r\n";
                HelpMen::report($movil->equipo_id,$logcadena);
            }
        }
        return $estadoMovilidad;
        /*cambios de bateria*/
        //Log::info(print_r($sensorEstado,true));
    }
    public static function persistSensor($ioData,$imei,$posicion_id,$movil,$fecha,$tipo_alarma_id,$estado_movil_id){
        $movilOldId = intval($movil->movilOldId);
        $movil_id   = intval($movil->movil_id);
        DB::beginTransaction();
        try {
            if($tipo_alarma_id!=49){//solo si es cualquier alarma distinta de alimentacion ppal
                Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>$movilOldId,
                            'tipo_alarma_id'=>$tipo_alarma_id,'fecha_alarma'=>$fecha,'falsa'=>0]);
                Movil::where('movil_id', '=', $movil_id)->update(array('estado_movil_id' => $estado_movil_id));
            }
            
            DB::commit();
            self::startupSensores();
        }catch (\Exception $ex) {
            DB::rollBack();
            $logcadena = "Error al tratar alarmas IO persistSensor \r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
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
        //Log::info("ingresa por AnalPerifericos porque Per!=NULL");
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
    /*0 = RESET
    1 = NORMAL
    2 = CORTE
    3 = BLOQUEO DE INHIBICIÓN
    4 = ALARMA*/
    public static function ModPrecencia($arrPrescense){
        //Log::info("ingresa por modPresencia porque PER==NULL");
        $IOEstados       = array("ltrs"=>0,"mod_presencia"=>1,"tmg"=>0,"panico"=>0,"desenganche"=>0);
        if($arrPrescense[2]=='O01' || $arrPrescense[2]=='O11'){
            $IOEstados['mod_presencia']   =   2;
        }
        if($arrPrescense[1]=='I00'){
            $IOEstados['panico']   =   1;
        }
        if($arrPrescense[1]=='I10'){
            $IOEstados['bat']   =   1;
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
        $velocidad  = ((int)($velocidad*1.852));
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
    /*Sensores IOM*/
    public static function getSensores($imei) {
       //MemVar::VaciaMemoria();
        Log::error("obteniendo sensores");
        $shmid    = MemVar::OpenToRead('sensores.dat');
        if($shmid=='0'){
            $memoEstados    = self::startupSensores();
        }else{
            MemVar::initIdentifier($shmid);
            $memoEstados    = MemVar::GetValue();
            $memoEstados    = json_decode($memoEstados);
        }
        /*si encuentro el movil veo el sensor, si difiere al enviado por parametro
        genero un nuevo elemento y lo cargo en el array y en la ddbb
        elimino el elemento anterior del array, limpio y vuelvo a cargar la memoria
        */
        //ver si aca puedo buscar asi $memoEstados[$imei]
        $encontrado     = self::binarySearch($memoEstados, 0, count($memoEstados) - 1, $imei);
        return $encontrado;
        
    }
    public static function startupSensores(){
       // MemVar::VaciaMemoria();
        $shmidPos       = MemVar::OpenToRead('sensores.dat');
        if($shmidPos == '0'){
            Log::info("no hay segmento de memoria");  
        }else{
            MemVar::initIdentifier($shmidPos);
            MemVar::Eliminar( 'sensores.dat' );
        }
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
    /*Fin sensores IOM*/
    public static function CargarMemoria($archivo,$dataArray){
        $memvar     = MemVar::Instance($archivo);
        $enstring   = json_encode($dataArray);
        $largo      = (int)strlen($enstring);
        $memvar->init($archivo,$largo);
        $memvar->setValue( $enstring );
    }

}
