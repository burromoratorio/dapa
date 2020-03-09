<?php
namespace App\Http\Controllers;

use App\Alarmas;
use App\EstadosSensores;
use App\Movil;
use App\Helpers\HelpMen;
use App\Helpers\RedisHelp;
use App\Helpers\PerifericoHelp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\PerifericoController;

use Laravel\Lumen\Routing\Controller as BaseController;
/**
 * Encargado de realizar el analisis de cambios en 
 * bits IO y IOM
 * @author Alan Ramirez Moratorio
 */
class SensorController extends BaseController {
    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    public function __construct() {}
    
    public static function sensorAnalisis($ioData,$perField,$posicion_id,$movil,$fecha,$estadoMovilidad){
        $cambioBits = array("rta"=>0,"estado_movil_id"=>$estadoMovilidad,"tipo_alarma_id"=>7); //alarma_id=7 (Normal)
        $perifData      = explode(',',$perField);
        log::error(print_r($perField,true));
        if($movil->perif_io_id && $movil->perif_io_id!=''){//tiene instalado IOM/BIO
            HelpMen::report($movil->equipo_id,"movil con iom");
            switch ($perifData[0]) {
                case 'IOM':
                    $cambioBits = self::analisisIOM($perField,$posicion_id,$movil,$fecha,$estadoMovilidad);
                    break;
                case 'BIO':
                    $cambioBits = self::analisisBIO($perField,$posicion_id,$movil,$fecha,$estadoMovilidad);
                    break;
                case 'NULL':
                    //evaluo bateria del IO por mas que tenga IOM el equipo
                    HelpMen::report($movil->equipo_id,"reporte solo de posicion evaluo bateria y demas porque tiene iom");
                    $cambioBits = self::analisisIO($ioData,$posicion_id,$movil,$fecha,$estadoMovilidad);
                    break;
                default:
                    break;
            }
        }else{//no tiene iom, le doy bola al equipo//analisis en bits IO
            $cambioBits = self::analisisIO($ioData,$posicion_id,$movil,$fecha,$estadoMovilidad);
        }
        return $cambioBits["estado_movil_id"];
    }
     /*@param: string , cadena de periferico
     * IOM,10111000011110,000000XXXX,4,1,ALA,XX1XXXXXXXXXXX   
     * BIO,100001,00,1,ALA,1xxxxx
     */
    public static function perifericos($perField){
        $func   = 'iom';
        $arr    = explode(',',$perField);
        if($arr[0]=='BIO'){
            $func='bio';
        }
        return $func;
        //string del tipo:IOM,10111000011110,000000XXXX,4,1,ALA,XX1XXXXXXXXXXX   
    }
    public static function analisisIO($ioData,$posicion_id,$movil,$fecha,$estadoMovilidad){
        $movilOldId = intval($movil->movilOldId);//alarma_id=7 (Normal)//estado_movil_id=10(si alarma)
        $rta        = array("rta"=>0,"estado_movil_id"=>$estadoMovilidad,"tipo_alarma_id"=>0); 
        $estado_movil_id=$estadoMovilidad;
        $tipo_alarma_id=0;
//si no tiene posicion_id y es una alarma de panico , informar mail?¡
        //$ioData[0]=="I0X"=>eso es panico inibido
        if($ioData[0]=="I0X"){
            HelpMen::report($movil->equipo_id,"Panico - Inhibido \r\n");
        }else{
            if($ioData[0]=="I00"){//ingreso de alarma de panico bit en 0
                $logcadena = "Panico presionado Equipo:".$movil->imei." - Movil:".$movilOldId."\r\n";
                HelpMen::report($movil->equipo_id,$logcadena);
                DB::beginTransaction();
                try {
                    $alarmaPanico   = Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>$movilOldId,'tipo_alarma_id'=>1,
                                        'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0']);
                    $alarmaPanico->save();
                    DB::commit();
                }catch (\Exception $ex) {
                        DB::rollBack();
                        $logcadena = "Error al tratar alarmas IO..".$ex;
                        HelpMen::report($movil->equipo_id,$logcadena);
                }
            }
        }
        /*cambios de estado IO alarmas de bateria*/
        $io             = str_replace("I", "",$ioData[1] );
        $sensorEstado   = $movil->io;
        if($io=='10'){ 
            $logcadena = "Movil: ".$movil->imei." - Equipo: ".$movil->equipo_id." - funcionando con bateria auxiliar \r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
            $tipo_alarma_id=50;
            $estado_movil_id=13;
        }
        if($io=='11'){//alimentacion ppal 
            $tipo_alarma_id=49;
            $estado_movil_id=14;
        }
        if($io=='1X'){
            HelpMen::report($movil->equipo_id,"Alimentacion PPal Inhibida \r\n");
        }
        $rta["estado_movil_id"]=$estado_movil_id;
        $rta["tipo_alarma_id"] =$tipo_alarma_id;
        self::generaSensoresIo($posicion_id,$movil,$io,$fecha,$tipo_alarma_id,$estado_movil_id,$sensorEstado);
        
        return $rta;
    }
    /************Analisis de cadena IOM*********/
    /*$arrPeriferico[0]=IOM,  $arrPeriferico[1]=I1..I14, $arrPeriferico[2]=O1..O14, $arrPeriferico[3]=E(modo de trabajo del equipo)
    $arrPeriferico[4]=PR(método de restablecimiento Manual),  $arrPeriferico[5]=NB(Normal o backgrond),  $arrPeriferico[6]=P (Panico) 
    si algun sendor trae el caracter X entonces no lo tengo en cuenta */
    //$perField -->campo PER completo que viene en cadena de posicion
    public static function analisisIOM($perField,$posicion_id,$movil,$fecha,$estado_movil_id){
        $arrIOM      = explode(',',$perField);
        //string del tipo:IOM,10111000011110,000000XXXX,4,1,ALA,XX1XXXXXXXXXXX    
        //$sensorEstado= $movil->iom;
        //estado_movil_id=7(normal), 10(Alarma)
        $rta         = array("rta"=>0,"estado_movil_id"=>$estado_movil_id,"tipo_alarma_id"=>0); 
        if(!is_null($perField) && $perField!='' && $arrIOM[0]=='IOM'){
            $perFieldInput   = $arrIOM[1];//entradas
            $perFieldOutput  = $arrIOM[2];//salidas
            $perFieldWorkMode= $arrIOM[3];//modo de trabajo
            $manualRestartMethod= $arrIOM[4];//modo reseteo
            $iomArr = str_split($perFieldInput);
            $keyAlarma=array_search('ALA', $arrIOM);
            /*****si $perFieldWorkMode= 0 =>RESET no informo alertas de nada solo actualizo estado de movil****/
            if($perFieldWorkMode!= 0 ){
                //se usa el campo input de la cadena salvo en estado de Panico "P" "ALA" y NB
                $estadoArr= null;
                if( $keyAlarma ){//Evaluo campo ALA
                    $estadoArr = str_split($arrIOM[$keyAlarma+1]);//genero un array con el vector de alarma(ALA,0XXXXXXXXXXXXX)del perif
                    log::error(print_r($estadoArr,true));
                    $rta       = PerifericoHelp::evaluaCampoAlaIOM($estadoArr,$movil);
                    if($rta["tipo_alarma_id"]>0){
                        Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta["tipo_alarma_id"],
                                        'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0']);
                        $rta["estado_movil_id"]=10;
                        $rta["rta"]            = 1;
                    }
                }
                $rta = PerifericoHelp::evaluaCampoAlaIOM($arrIOM,$perFieldWorkMode,$posicion_id,$movil,$fecha,$estadoArr);
                $rta = PerifericoHelp::evaluaNb($arrIOM,$posicion_id,$movil,$fecha);               
                //luego del analisis actualizo los datos de sensores, primero analizo e informo alarmas, y estado del movil
                $rta = self::generaSensoresPerifericos($posicion_id,$movil,$perFieldInput,$perFieldOutput,$manualRestartMethod,$fecha,$rta["tipo_alarma_id"],$rta["estado_movil_id"],"IOM");
                
            }else{
                /*Dicen que cuando se pone en este modo ahora hay que actualizar los datos...*/
                HelpMen::report($movil->equipo_id,"\r\n **EQUIPO EN MODO RESET...NO INFORMO ALARMA DE NINGUN TIPO*** \r\n ");
                self::actualizarPerifericos($movil,$iomArr,$perFieldOutput,$manualRestartMethod);
            }
            if( $perField!='' && !is_null($perField)){
                RedisHelp::setIOM ($movil, $perField);
            }
        }
        return $rta;    
    }
    //string del tipo: BIO,100001,00,1,ALA,1xxxxx
    //estado_movil_id=7(normal), 10(Alarma)
    public static function analisisBIO($perField,$posicion_id,$movil,$fecha,$estado_movil_id){
        $arrBIO      = explode(',',$perField);
        $sensorEstado= $movil->bio;
        $rta         = array("rta"=>0,"estado_movil_id"=>$estado_movil_id,"tipo_alarma_id"=>0); 
        if(!is_null($perField) && $perField!='' && $arrBIO[0]=='BIO'){
            $perFieldInput   = $arrBIO[1];//entradas
            $perFieldOutput  = $arrBIO[2];//salidas
            $perFieldWorkMode= $arrBIO[3];//modo de trabajo
            $bioArr          = str_split($perFieldInput);
            $keyAlarma       = array_search('ALA', $arrBIO);
            /*****si $perFieldWorkMode= 0 =>RESET no informo alertas de nada solo actualizo estado de movil****/
            if($perFieldWorkMode!= 0 ){
                //se usa el campo input de la cadena salvo en estado de Panico "P" "ALA" y NB
                $estadoArr= null;
                if( $keyAlarma ){//Evaluo campo ALA
                    $estadoArr  = str_split($arrBIO[$keyAlarma+1]);//genero un array con el vector de alarma(ALA,0XXXXXXXXXXXXX)del perif
                    $rta        = PerifericoHelp::evaluaCampoAlaBIO($estadoArr,$movil,$fecha,$posicion_id);
                }
                $rta = PerifericoHelp::evaluaPanicoBIO($arrBIO,$posicion_id,$movil,$fecha);
                $rta = PerifericoHelp::cambiosBitBIO($bioArr,$sensorEstado,$movil,$estado_movil_id,$perFieldOutput);
                $rta = self::generaSensoresPerifericos($posicion_id,$movil,$perFieldInput,$perFieldOutput,0,$fecha,$rta,"BIO");
            }
        }
        if( $perField!='' && !is_null($perField)){
            RedisHelp::setBIO ($movil, $perField);
        }
        return $rta;
    }
    public static function generaSensoresIo($posicion_id,$movil,$io,$fecha,$tipo_alarma_id,$estado_movil_id,$sensorEstado){
        $rta  = array("rta"=>0,"estado_movil_id"=>$estado_movil_id,"tipo_alarma_id"=>$tipo_alarma_id); 
        if( (!$movil->perif_io_id || $movil->perif_io_id=='')  && $sensorEstado==''){
            HelpMen::report($movil->equipo_id,"Datos de sensores IO vacios en memoria, generando...".$io." \r\n");
            DB::beginTransaction();
            try {
                EstadosSensores::create(['imei'=>$movil->imei,'movil_id'=>intval($movil->movil_id),'io'=>$io]);
                self::persistSensor($posicion_id,$movil,$fecha,$tipo_alarma_id,$estado_movil_id);
                DB::commit();
                $rta["rta"]=0;
            }catch (\Exception $ex) {
                DB::rollBack();
                $logcadena = "Error al dar de alta sensor IO..".$ex."\r\n";
                HelpMen::report($movil->equipo_id,$logcadena);
            }
        }else{
            if($io!=$sensorEstado){ //evaluo cambio de bits de sensor IO
                $rta["rta"]=1;
                self::updateSensores($movil,"io","",$io,$rta,$posicion_id,$fecha);
            }
        }
        if($io!='NULL' && !is_null($io)){
            RedisHelp::setIO($movil, $io);
        }
    }
    public static function generaSensoresPerifericos($posicion_id,$movil,$perFieldInput,$perFieldOutput,$manualRestartMethod,$fecha,$rta,$tipoPeriferico){
        //luego del analisis actualizo los datos de sensores, primero analizo e informo alarmas, y estado del movil
        $arrPer = str_split($perFieldInput);
        $sensorNuevo  = EstadosSensores::where('movil_id', '=',$movil->movil_id)->orderBy('updated_at','DESC')->first();
        switch ($tipoPeriferico) {
            case 'iom':
                if(is_null($movil->iom) || $movil->iom=='' ){
                    if(!$sensorNuevo){//si no tenia en la ddbb data
                        self::createSensores($movil, $perFieldInput, "IOM");
                        self::persistSensor($posicion_id,$movil,$fecha,$rta["tipo_alarma_id"],$rta["estado_movil_id"]);
                    }else{//si tenía pero posiblemente solo de IO, genero el IOM
                        HelpMen::report($movil->equipo_id,"Actualizando datos de IOM en instalacion que antes tenia IO \r\n");
                        self::updateSensores($movil,"iom",$perFieldInput,"",$rta,$posicion_id,$fecha);
                        self::actualizarPerifericos($movil,$arrPer,$perFieldOutput,$manualRestartMethod);
                    }
                }else{
                    HelpMen::report($movil->equipo_id,"Actualizando datos de IOM en instalacion que antes tenia IO \r\n");
                    self::actualizarPerifericos($movil,$arrPer,$perFieldOutput,$manualRestartMethod);
                    $rta = PerifericoHelp::cambiosInputIOM($arrPer,$movil->iom,$movil,$rta["estado_movil_id"],$perFieldOutput,$manualRestartMethod);
                    if($rta["rta"]==1){
                        self::updateSensores($movil,"iom",$perFieldInput,"",$rta,$posicion_id,$fecha);
                    }
                }
                break;
            case 'bio':
                if(is_null($movil->bio) || $movil->bio=='' ){
                    if(!$sensorNuevo){//si no tenia en la ddbb data
                        self::createSensores($movil, $perFieldInput, "BIO");
                        self::persistSensor($posicion_id,$movil,$fecha,$rta["tipo_alarma_id"],$rta["estado_movil_id"]);
                    }else{//si tenía pero posiblemente solo de IO, genero el IOM
                        HelpMen::report($movil->equipo_id,"Actualizando datos de BIO en instalacion que antes tenia IO \r\n");
                        self::updateSensores($movil,"bio",$perFieldInput,"",$rta,$posicion_id,$fecha);
                        self::actualizarPerifericosBIO($movil,$arrPer,$perFieldOutput,$manualRestartMethod);
                    }
                }else{
                    HelpMen::report($movil->equipo_id,"Actualizando datos de IOM en instalacion que antes tenia IO \r\n");
                    self::actualizarPerifericos($movil,$arrPer,$perFieldOutput,$manualRestartMethod);
                    $rta = PerifericoHelp::cambiosBitBIO($arrPer,$movil->bio,$movil,$rta["estado_movil_id"],$perFieldOutput,$manualRestartMethod);
                    if($rta["rta"]==1){
                        self::updateSensores($movil,"bio",$perFieldInput,"",$rta,$posicion_id,$fecha);
                    }
                }
                break;
            case 'NULL':
                //evaluo bateria del IO por mas que tenga IOM el equipo
                HelpMen::report($movil->equipo_id,"GENERA SENSORES PERIFERICOS CON TIPO NULL---NO DEBE ENTRAR ACA");
                //$rta    = self::analisisIO($ioData,$movil->imei,$posicion_id,$movil,$fecha,$estadoMovilidad);
                break;
            default:
                break;
        }
    }
    public static function createSensores($movil,$perFieldInput,$tipo){
        HelpMen::report($movil->equipo_id,"Datos de sensores vacios en DDBB, generando...\r\n");
        DB::beginTransaction();
        try {
            EstadosSensores::where('imei',$movil->imei)->delete();
            if($tipo=='IOM'){
                EstadosSensores::create(['imei'=>$movil->imei,'movil_id'=>intval($movil->movil_id),'iom'=>$perFieldInput]);
            }else{
                EstadosSensores::create(['imei'=>$movil->imei,'movil_id'=>intval($movil->movil_id),'bio'=>$perFieldInput]);
            }
            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            $logcadena = "Error al dar de alta sensor IOM..".$ex."\r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
        }
    }
    public static function updateSensores($movil,$tipo,$perField,$io,$rta,$posicion_id,$fecha){
        HelpMen::report($movil->equipo_id,"update de sensores :".$tipo." en base de datos tipo:".$io);
        HelpMen::report($movil->equipo_id,"update de sensores :".$tipo." en base de datos tipo:".$perField);
        DB::beginTransaction();
        try {
            switch ($tipo) {
            case 'iom':
                EstadosSensores::where('movil_id', '=',$movil->movil_id)->update(array('iom' => $perField));
                break;
            case 'bio':
                EstadosSensores::where('movil_id', '=',$movil->movil_id)->update(array('bio' => $perField));
                break;
            case 'io':
                EstadosSensores::where('movil_id', '=', $movil->movil_id)->update(array('io' => $io));
                break;
            }
            self::persistSensor($posicion_id,$movil,$fecha,$rta['tipo_alarma_id'],$rta['estado_movil_id']);
            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            $logcadena = "Error al tratar alarmas IO..".$ex."\r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
        }
    }
    public static function persistSensor($posicion_id,$movil,$fecha,$tipo_alarma_id,$estado_movil_id){
        $movilOldId = $movil->movilOldId;
        $movil_id   = $movil->movil_id;
        DB::beginTransaction();
        try {
            if($tipo_alarma_id!=49 && $tipo_alarma_id!=0 ){//solo si es cualquier alarma distinta de alimentacion ppal
                Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>$movilOldId,'tipo_alarma_id'=>$tipo_alarma_id,
                            'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0']);
            }
            Movil::where('movil_id', '=', $movil_id)->update(array('estado_movil_id' => $estado_movil_id));
            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            $logcadena = "Error al tratar alarmas persistSensor \r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
        }
    }
    
    public static function actualizarPerifericos($movil,$estadoArr,$perFieldOutput,$manualRestartMethod){
        Log::info(":::::::Actualizando datos de Perifericos:::");
        PerifericoController::setSensores("IOM",$movil->equipo_id,$estadoArr,$perFieldOutput,$manualRestartMethod);
    }
    public static function actualizarPerifericosBIO($movil,$estadoArr,$perFieldOutput){
        Log::info(":::::::Actualizando datos de Perifericos BIO:::");
        PerifericoController::setSensores("BIO",$movil->equipo_id,$estadoArr,$perFieldOutput,0);
    }
}
