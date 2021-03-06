<?php
namespace App\Http\Controllers;
use App\Alarmas;
use App\EstadosSensores;
use App\GprmcAlarma;
use App\GprmcDesconexion;
use App\GprmcEntrada;
use App\Movil;
use App\Posiciones;
use App\PosicionesHistoricas;
use App\Helpers\HelpMen;
use App\Helpers\MemVar;
use App\Helpers\RedisHelp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\SensorController;

use Laravel\Lumen\Routing\Controller as BaseController;

class PuertoController extends BaseController
{

    private static $cadena;
    protected static $moviles_activos = null;
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
                        if($posicionID=='99'){
                            $imei="OK";
                            Log::info("POSICION IDDDDD::::::::".$posicionID."::::::::::::+QHTTPPOST: 0,");
                            
                        }elseif ($posicionID!='0') {
                        /*no guardo las alarmas en dbPrimaria self::findAndStoreAlarm($arrCampos,$posicionID);*/
                            $imei="OK";
                        }else{
                            Log::error("Cadena GPRMC vacia");
                            self::findAndSendAlarm($arrCampos,$movil);
                            $imei="error"; 
                        }
                        break;
                    case 'DAD':
                        Log::info("Reporte Desconexion DAD...Movil:".$movil->movilOldId." - Equipo:".$movil->equipo_id);
                        //self::storeDad($arrCampos,$movil);
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
        $frArr          = explode(',',$fr); 
        $update         = 0;
        HelpMen::report($movil->equipo_id,"=>fecha:".$fecha." \r\n");
        $posicion= array("imei"=>$imei,"fecha"=>$fecha,"velocidad"=>$velocidad,"indice"=>0);
        if($movil->fecha_posicion==''){
            RedisHelp::setPosicionMovil($posicion);
            HelpMen::report($movil->equipo_id,"Almacenando posicion:".$fecha." velocidad:".$velocidad);
        }else{
            if(HelpMen::fechaHistorica($fecha)==1){
                HelpMen::report($movil->equipo_id,"Reporte de fecha anterior no evaluo movimiento/detencion:".$fecha." \r\n");
            }else{
                if(self::seMueve($frArr[0], $velocidad, $movil,$posicion)==0){//no se mueve
                    if(self::seDetuvo($frArr[0], $movil,$posicion)==1){//se detuvo?
                        $update=0;
                    }else{//sigue detenido
                        self::continuaDetenido($frArr[0], $velocidad, $fecha, $movil,$posicion);
                        $update=1;
                    }
                }else{//esta en movimiento
                    $update=0;
                }
            }
        }
        return $update;
    }
    /*Indice compenente de frecuencua FR:120,<indice> 0=ALARMA,1=RESPUESTA,2=NORMAL,3=RECTO,4=CURVA,5=IO,6=DAD,7:PERIFERICO*/
    public static function seMueve($frecuencia,$velocidad,$movil,$posicion){
        $mueve=1;
        if($frecuencia<=120){
            if($movil->velocidad<=5){//velocidad posicion anterior en redis
                $posicion['indice']=0;
                RedisHelp::setPosicionMovil($posicion);
                HelpMen::report($movil->equipo_id,"=>detenido a movimiento \r\n");
            }else{//continua en movimiento
                HelpMen::report($movil->equipo_id,"=>continua en movimiento velocidad anterior:".$movil->velocidad." \r\n");
            }
            RedisHelp::setPosicionMovil($posicion);
        }else{
            $mueve=0;
        }
        return $mueve;
    }
    public static function seDetuvo($frecuencia,$movil,$posicion){
        $detuvo=0;
        if($frecuencia>120 ){
            if($movil->velocidad>=8){
                HelpMen::report($movil->equipo_id,"=>Movil se detuvo \r\n");
                HelpMen::report($movil->equipo_id,"=>velocidad anterior:".$movil->velocidad." \r\n");
                $posicion['indice']=1;
                RedisHelp::setPosicionMovil($posicion);
                $detuvo=1;
            }
        }
        return $detuvo;
    }
    public static function continuaDetenido($frecuencia,$velocidad,$fecha,$movil,$posicion){
        if($frecuencia>120 && $movil->velocidad<5 && $velocidad<5){
            HelpMen::report($movil->equipo_id,"=>Movil continua Detenido \r\n");
            if(DB::connection()->getDatabaseName()=='moviles')config()->set('database.default', 'siac');
            $lastPosition = PosicionesHistoricas::where('movil_id',intval($movil->movilOldId))->orderBy('fecha', 'DESC')->first();
            if($movil->indice == 2){
                if($lastPosition){
                    DB::beginTransaction();
                    try {
                        PosicionesHistoricas::where('posicion_id',$lastPosition->posicion_id)->delete();
                        DB::commit();
                    }catch (\Exception $ex) {
                        DB::rollBack();
                        $errorSolo  = explode("Stack trace", $ex);
                        $logcadena ="Error al borrar posicion anterior en detenido ".$errorSolo[0]." \r\n";
                        HelpMen::report($movil->equipo_id,$logcadena);
                    }
                }
            }else{
                $posicion['indice']=2;
                RedisHelp::setPosicionMovil($posicion);
            }
            if($lastPosition){
                DB::beginTransaction();
                try {
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
                    DB::commit();
                }catch (\Exception $ex) {
                    DB::rollBack();
                    $errorSolo  = explode("Stack trace", $ex);
                    $logcadena ="Error en alta de POSICIONES ".$errorSolo[0]." \r\n";
                    HelpMen::report($movil->equipo_id,$logcadena);

                }
            }
            config()->set('database.default', 'moviles');
        }
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
        /*Parche para error 6 de abril en s3000, fecha=200300*/
        $anio = substr($fecha, -2,2);
        if($anio=='00'){
            $formatFecha= date("Y-m-d H:i:s", mktime(substr($hora, 0,2), substr($hora, 2,2), substr($hora, 4,2), substr(date('mdy'), 0,2), substr(date('mdy'), 2,2), substr(date('mdy'), -2,2)));
            $nuevafecha = strtotime ( '-3 hours' , strtotime ( $formatFecha ) ) ;
            $nuevafecha = date ( 'Y-m-d H:i:s' , $nuevafecha );
            $logeo  = "^^^<APLICANDO PARCHE 6A>^^^ Nueva fecha:".$nuevafecha." \r\n"; 
            HelpMen::report($movil->equipo_id,$logeo);
            return $nuevafecha;
        }else{
            $formatFecha = date("Y-m-d H:i:s", mktime(substr($hora, 0,2), substr($hora, 2,2), substr($hora, 4,2), substr($fecha, 2,2), substr($fecha, 0,2), substr($fecha, -2,2)));
            $nuevafecha = strtotime ( '-3 hours' , strtotime ( $formatFecha ) ) ;
            //-----aviso a seba de reporte viejo----//
            $fechacompara   = date('Y-m-j H:i:s'); 
            $newDateCompa   = strtotime ( '-10 minute' , strtotime ($fechacompara) ) ; 
            if($nuevafecha<=$newDateCompa){
                $logeo  = "^^^<Reporte Historico>^^^ \r\n";  
                HelpMen::report($movil->equipo_id,$logeo);
            }
            $nuevafecha = date ( 'Y-m-d H:i:s' , $nuevafecha );
            return $nuevafecha;
        }
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
    public static function storeDad($report,$movil){
        $dadData    = self::validateIndexCadena("DAD",$report,8);
        $frData     = self::validateIndexCadena("FR",$report,2);
        $lacData    = self::validateIndexCadena("LAC",$report,2);
        $kmtField   = self::validateIndexCadena("KMT",$report);
        $odpField   = self::validateIndexCadena("ODP",$report);
        $fechaDad   = ($dadData[0]!="NULL")?self::ddmmyy2yyyymmdd($dadData[0],"000000",$movil):"NULL";
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
        if($report['GPRMC']!=''){
            $gprmcData  = explode(",",$report['GPRMC']);
            $gprmcVal   = self::validateGprmc($gprmcData);
            if($gprmcVal){
                $ioData     = self::validateIndexCadena("IO",$report,2);
                $panico     = str_replace("I0", "",$ioData[0] );
                $dcxData    = self::validateIndexCadena("DCX",$report,2);
                $preData    = self::validateIndexCadena("PRE",$report,2);
                $frData     = self::validateIndexCadena("FR",$report,2);
                if($frData['FR']=='NULL'){
                    $frData['FR']='-';
                    $frData[0]=60;
                    $frData[1]=4;
                    HelpMen::report($movil->equipo_id,"Frecuencia vacia...posible curva");
                }
                $lacData    = self::validateIndexCadena("LAC",$report,2);
                $mcpData    = self::validateIndexCadena("MCP",$report,2);
                $alaField   = self::validateIndexCadena("ALA",$report);
                $perField   = self::validateIndexCadena("PER",$report);
                $kmtField   = self::validateIndexCadena("KMT",$report);
                $vbaField   = self::validateIndexCadena("VBA",$report);
                $odpField   = self::validateIndexCadena("ODP",$report);
                $fecha      = self::ddmmyy2yyyymmdd($gprmcData[8],$gprmcData[0],$movil);
                //-------si viene el campo PER con IOM ya no le doy bola al IO///////-------
                /*en esta parte solo busco el modo de presencia*/
                $info       = array("ltrs"=>0,"mod_presencia"=>$movil->estado_v,"tmg"=>0,"panico"=>0,"desenganche"=>0);
                if($movil->perif_io_id){//tiene instalado IOM/BIO/TERMO
                    if($perField['PER']!='NULL'){//reportó el iom
                    $info       = self::ModPrecencia($perField['PER'],"IOM");
                    Log::error("El info IOM:".$info['mod_presencia']);
                    }
                }else{//no tiene iom, le doy bola al equipo
                    $info       = self::ModPrecencia($ioData['IO'],"IO");
                }
                $arrInfoGprmc   = HelpMen::Gprmc2Data($gprmcData);
                $validezReporte = self::validezReporte($report['IMEI'],$fecha,$gprmcData[6],$frData['FR'],$movil);
                
                //voy a almacenar el ultimo reporte del movil
                $cadenaRedis    = implode(";", $report);
                RedisHelp::setLastReport($report['IMEI'],$cadenaRedis);
                //si validez reporte da 1, quiere decir que está detenido, traer la ultima posicion_id y meter acá
                if($validezReporte>0){
                    HelpMen::report($movil->equipo_id,"Actualizo hora de posicion en detenido \r\n");
                    //traigo la ultima posicion para despues insertar con los eventos
                    $posicion = PosicionesHistoricas::where('movil_id','=',$movil->movilOldId)->orderBy('fecha','DESC')->get()->first(); 
                    $respuesta= $validezReporte;
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
                    $rumbo_id       = HelpMen::Rumbo2String( $gprmcData[7] );
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
                            $alarmaVelocidad    = Alarmas::create(['posicion_id'=>$posicion->posicion_id,'movil_id'=>intval($movil->movilOldId),
                                                'tipo_alarma_id'=>7,'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0']);
                            $estadoMovilidad    = 11;
                        }
                        DB::commit();
                        
                    }catch(\Exception $ex) {
                        DB::rollBack();
                        $errorSolo  = explode("Stack trace", $ex);
                        $logcadena = "Error al procesar posicion en puerto controller".$errorSolo[0]."\r\n";
                        //si es error de unique key devuelvo 1, para que el equipo no vuelva a enviar la posicion, sino 0
                        if (strpos($errorSolo[0], 'AK_POSICION') !== false) {
                            $respuesta='99';
                            HelpMen::report($movil->equipo_id,"ERROR DE CLAVE PRIMARIA::::::AK_POSICION UNIQUE KEY:::: puerto controller insert position");
                        }else{
                            HelpMen::report($movil->equipo_id,$logcadena);
                        }
                        //$respuesta=(strpos($errorSolo[0], 'AK_POSICION') !== false)?"1":"0";
                    }
                }
                //Log::info(print_r($posicion,true));
                if(isset($posicion)){
                    //inserto alarma de panico!!
                    $estadoMovilidad = SensorController::sensorAnalisis($ioData,$perField['PER'],$posicion->posicion_id,$movil,$fecha,$estadoMovilidad);
                    if( $estadoMovilidad==7 ){
                        if($arrInfoGprmc['velocidad']>12){
                            $estadoMovilidad=($movil->estado_u==0)?3:4;//movimiento vacio estado_u=0, otro..movimiento cargado
                        }else{
                            $estadoMovilidad=($movil->estado_u==0)?1:2;//detenido vacio estado_u=0, otro..detenido cargado2
                        }
                    }
                    try{
                        $movilModel = new Movil;
                        $movilModel->setConnection('siac');
                        $updateMovil= $movilModel->where('movil_id','=',intval($movil->movilOldId))
                                    ->update(['latitud'=>$arrInfoGprmc['latitud'],'longitud'=>$arrInfoGprmc['longitud'],
                                            'rumbo_id'=>$arrInfoGprmc['rumbo'],'estado'=>$estadoMovilidad,
                                            'velocidad'=>$arrInfoGprmc['velocidad'],'fecha_ult_posicion'=>$fecha,
                                        'estado_v'=>$info['mod_presencia']]);
                        
                    }catch(\Exception $ex){
                        $errorSolo  = explode("Stack trace", $ex);
                        $logcadena = "PUERTO CONTROLLER AL ACTUAIZAR ESTADO MOVIL::::::::::::".$errorSolo[0]."\r\n";
                        HelpMen::report($movil->equipo_id,$logcadena);
                    }
                    
                    //DB::commit();
                    /*if(DB::connection()->getDatabaseName()=='moviles'){
                        config()->set('database.default', 'siac');
                        
                    }*/
                    config()->set('database.default', 'moviles');
                }
                /*********para comunicacion con API NAcho*******/
                $movTestings = array(10035, 10036,772,1054);//posteo a molinos api
                if (in_array($movil->equipo_id, $movTestings)){
                Log::info("por enviar al api de nacho el movil:".$movil->equipo_id);
                    $json=  ["movil"=>intval($movil->movilOldId),
                    "point"=> ["type"=>"Point","coordinates"=> [$arrInfoGprmc['longitud'],$arrInfoGprmc['latitud'] ] ],
                    "received"=>$fecha];
                    //Log::info(print_r($json,true));
                    HelpMen::posteaPosicion("positions","molinos",$json);      
                }
                /*$json=  ["movil"=>intval($movil->movilOldId),
                "point"=> ["type"=>"Point","coordinates"=> [$arrInfoGprmc['longitud'],$arrInfoGprmc['latitud'] ] ],
                "received"=>$fecha, "speed"=> $arrInfoGprmc['velocidad'], "direction"=>$arrInfoGprmc['rumbo']
                ];
                HelpMen::posteaPosicion("operativo/positions","api",$json); */     
                /*********para comunicacion con API NAcho********/
            }else{
                $respuesta  = "0";
                Log::error("cadena sin posicion");
            }
        }else{
            //evaluar si en la cadena vino un panico, en 
            $respuesta  = "0";
        }
        try{
            DB::disconnect();
        }catch(\Exception $e){
            
        }
        return $respuesta;
    }
    public static function findAndSendAlarm($report,$movil){
        $destinatarios  = "amoratorio@siacseguridad.com";
        $cuerpo = "Panico Presionado Equipo:".$movil->equipo_id;
        $asunto = "Panico Sin Posicion";
        $perField   = self::validateIndexCadena("PER",$report);
        $ioData     = self::validateIndexCadena("IO",$report,2);
        $panico     = str_replace("I0", "",$ioData[0] );
        $logcadena  = "PANICO PRESIONADO EN CADENA SI POSICION";
        if($panico==0){
            HelpMen::report($movil->equipo_id,$logcadena);
            //self::enviarMail($asunto,$cuerpo,$destinatarios);
        }
        if($perField!='NULL'){
            $perField=implode(",", $perField);
            if (strpos($perField, 'P') !== false){ 
                HelpMen::report($movil->equipo_id,$logcadena);
                //self::enviarMail($asunto,$cuerpo,$destinatarios);
            }
        }
    } 
    public static function enviarMail($asunto,$cuerpo,$destinatarios){
        $sock = stream_socket_client('tcp://192.168.0.247:2022', $errno, $errstr);
        fwrite($sock, $asunto.";".$cuerpo.";".$destinatarios);
        echo fread($sock, 4096)."\n";
        fclose($sock);
        
    }  
    /*0 = RESET,  1 = NORMAL,  2 = CORTE,  3 = BLOQUEO DE INHIBICIÓN,  4 = ALARMA*/
    public static function ModPrecencia($arrPrescense,$entradaSalida){
        //Log::info("ingresa por modPresencia porque PER==NULL");
        $IOEstados       = array("ltrs"=>0,"mod_presencia"=>1,"tmg"=>0,"panico"=>0,"desenganche"=>0);
        if($entradaSalida=="IO"){
            if($arrPrescense[2]=='O01' || $arrPrescense[2]=='O11'){
            $IOEstados['mod_presencia']   =   2;
            }
            if($arrPrescense[1]=='I00'){
                $IOEstados['panico']   =   1;
            }
            if($arrPrescense[1]=='I10'){
                $IOEstados['bat']   =   1;
            }
        }else{ //parte de iom
            $arrPeriferico     = explode(',', $arrPrescense);
            $valorPeriferico   = '';
            switch ($arrPeriferico[0]) {
                case 'CAU':
                    $valorPeriferico    = $arrPeriferico[1];
                    $valorPeriferico    = intval(($valorPeriferico)*10);
                    $IOEstados["ltrs"]= $valorPeriferico;
                     break;
                case 'TMG':
                    array_shift($arrPeriferico);
                    $valorPeriferico    = implode(',',$arrPeriferico);
                    $IOEstados["tmg"] = $valorPeriferico;
                    break;
                case 'IOM':
                    $IOEstados["mod_presencia"]= (isset($arrPeriferico[3]))?$arrPeriferico[3]:1;
                    break;
                case 'BIO':
                //falta ejemplo de sebas para armar cadena
                    $IOEstados["mod_presencia"]= (isset($arrPeriferico[3]))?$arrPeriferico[3]:1;
                    break;
                default:
                    $IOEstados["mod_presencia"]=1;
                    break;
            }
        }
        return $IOEstados;
    }
    public static function findAndStoreAlarGPRMC($report,$posicionID){
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
        
    } 
    
}
