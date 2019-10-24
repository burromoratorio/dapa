<?php
namespace App\Http\Controllers;

use App\Alarmas;
use App\EstadosSensores;
use App\Movil;
use App\Helpers\HelpMen;
use App\Helpers\MemVar;
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
    
    public static function sensorAnalisis($ioData,$perField,$imei,$posicion_id,$movil,$fecha,$estadoMovilidad){
        $cambioBits = array("rta"=>0,"estado_movil_id"=>$estadoMovilidad,"tipo_alarma_id"=>7); //alarma_id=7 (Normal)
        if($movil->perif_io_id){//tiene instalado IOM
            if($perField!='NULL'){//analisis en bits sensores IOM y ALA
                $cambioBits = self::analisisIOM($perField,$imei,$posicion_id,$movil,$fecha,$estadoMovilidad);
            }else{//evaluo bateria del IO por mas que tenga IOM el equipo
                $cambioBits = self::analisisIO($ioData,$imei,$posicion_id,$movil,$fecha,$estadoMovilidad);
            }
        }else{//no tiene iom, le doy bola al equipo//analisis en bits IO
            $cambioBits = self::analisisIO($ioData,$imei,$posicion_id,$movil,$fecha,$estadoMovilidad);
        }
        return $cambioBits["estado_movil_id"];
    }
    public static function analisisIO($ioData,$imei,$posicion_id,$movil,$fecha,$estadoMovilidad){
        $movilOldId = intval($movil->movilOldId);//alarma_id=7 (Normal)//estado_movil_id=10(si alarma)
        $rta        = array("rta"=>0,"estado_movil_id"=>$estadoMovilidad,"tipo_alarma_id"=>7); 
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
        $io             = str_replace("I", "",$ioData[1] );
        $sensorEstado   = self::getSensores($imei);
        if($io=='10'){ 
            $logcadena = "Movil: ".$imei." - Equipo: ".$movil->equipo_id." - funcionando con bateria auxiliar\r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
            $tipo_alarma_id=50;
            $estado_movil_id=13;
        }
        if($io=='11'){//alimentacion ppal 
            $tipo_alarma_id=49;
            $estado_movil_id=14;
        }
        if($io=='1X'){
            HelpMen::report($movil->equipo_id,"Alimentacion PPal Inhibida");
        }
        $rta["estado_movil_id"]=$estado_movil_id;
        $rta["tipo_alarma_id"] =$tipo_alarma_id;
        if(!$sensorEstado){
            HelpMen::report($movil->equipo_id,"Datos de sensores IO vacios en memoria, generando...");
            DB::beginTransaction();
            try {
                EstadosSensores::create(['imei'=>$imei,'movil_id'=>intval($movil->movil_id),'io'=>$io]);
                self::persistSensor($imei,$posicion_id,$movil,$fecha,$tipo_alarma_id,$estado_movil_id);
                DB::commit();
                $rta["rta"]=0;
            }catch (\Exception $ex) {
                DB::rollBack();
                $logcadena = "Error al dar de alta sensor IO..".$ex."\r\n";
                HelpMen::report($movil->equipo_id,$logcadena);
            }
        }else{
            if($io!=$sensorEstado->io){ //evaluo cambio de bits de sensor IO
                $rta["rta"]=1;
                self::updateSensores($imei,$movil,"",$io,$tipo_alarma_id,$estado_movil_id,$posicion_id,$fecha);
            }
        }
        return $rta;
    }
    /************Analisis de cadena IOM*********/
    /*$arrPeriferico[0]=IOM,  $arrPeriferico[1]=I1..I14, $arrPeriferico[2]=O1..O14, $arrPeriferico[3]=E(modo de trabajo del equipo)
    $arrPeriferico[4]=PR(método de restablecimiento Manual),  $arrPeriferico[5]=NB(Normal o backgrond),  $arrPeriferico[6]=P (Panico) 
    si algun sendor trae el caracter X entonces no lo tengo en cuenta */
    public static function analisisIOM($perField,$imei,$posicion_id,$movil,$fecha,$estado_movil_id){
        $arrIOM      = explode(',',$perField);
        $sensorEstado= self::getSensores($imei);//estado_movil_id=7(normal), 10(Alarma)
        $rta         = array("rta"=>0,"estado_movil_id"=>$estado_movil_id,"tipo_alarma_id"=>0); 
        if($perField!='NULL' && $arrIOM[0]=='IOM'){
            $perFieldInput   = $arrIOM[1];
            $perFieldOutput  = $arrIOM[2];
            $perFieldWorkMode= $arrIOM[3];
            $manualRestartMethod= $arrIOM[4];
            $iomArr = str_split($perFieldInput);
            $keyAlarma=array_search('ALA', $arrIOM);
            /*****si $perFieldWorkMode= 0 =>RESET no informo alertas de nada solo actualizo estado de movil****/
            if($perFieldWorkMode!= 0 ){
                //se usa el campo input de la cadena salvo en estado de Panico "P" "ALA" y NB
                $estadoArr= null;
                if( $keyAlarma ){//Evaluo campo ALA
                    $estadoArr = str_split($arrIOM[$keyAlarma+1]);
                    $rta = self::evaluaCampoAla($estadoArr,$movil);
                    if($rta["tipo_alarma_id"]>0){
                        Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta["tipo_alarma_id"],'fecha_alarma'=>$fecha,'falsa'=>0]);
                        $rta["estado_movil_id"]=10;
                        $rta["rta"]            = 1;
                    }
                }
                $rta = self::evaluaPanico($arrIOM,$perFieldWorkMode,$posicion_id,$movil,$fecha,$estadoArr);
                $rta = self::evaluaNb($arrIOM,$perFieldWorkMode,$posicion_id,$movil,$fecha);               
                
                //luego del analisis actualizo los datos de sensores, primero analiso e informo alarmas, y estado del movil
                if(!$sensorEstado ){
                   HelpMen::report($movil->equipo_id,"Datos de sensores vacios en memoria, generando...");
                    DB::beginTransaction();
                    try {
                        EstadosSensores::create(['imei'=>$imei,'movil_id'=>intval($movil->movil_id),'iom'=>$perFieldInput]);
                        self::persistSensor($imei,$posicion_id,$movil,$fecha,$rta["tipo_alarma_id"],$rta["estado_movil_id"]);
                        DB::commit();
                    }catch (\Exception $ex) {
                        DB::rollBack();
                        $logcadena = "Error al dar de alta sensor IOM..".$ex."\r\n";
                        HelpMen::report($movil->equipo_id,$logcadena);
                    }
                }else{
                    if($sensorEstado->iom=="NULL"){
                        HelpMen::report($movil->equipo_id,"Actualizando datos de IOM en instalacion que antes tenia IO");
                        DB::beginTransaction();
                        try {
                            $sensorNuevo  = EstadosSensores::where('id', '=',$sensorEstado->id)->get()->first();
                            $sensorNuevo->iom=$perFieldInput;
                            $sensorNuevo->save();
                            self::persistSensor($imei,$posicion_id,$movil,$fecha,$rta["tipo_alarma_id"],$rta["estado_movil_id"]);
                            DB::commit();
                        }catch (\Exception $ex) {
                            DB::rollBack();
                            $logcadena = "Error acutalizando campo IOM..".$ex."\r\n";
                            HelpMen::report($movil->equipo_id,$logcadena);
                        }
                    }else{
                        $idEstados = self::cambiosInputIOM($imei,$iomArr,$sensorEstado,$movil,$estado_movil_id,$perFieldOutput,$manualRestartMethod);
                        if($idEstados["rta"]==1)
                            self::updateSensores($imei,$movil,$perFieldInput,"",$idEstados["tipo_alarma_id"],$idEstados["estado_movil_id"],$posicion_id,$fecha);
                    }
                }
            }else{
                /*Dicen que cuando se pone en este modo ahora hay que actualizar los datos...*/
                HelpMen::report($movil->equipo_id,"\r\n **EQUIPO EN MODO RESET...NO INFORMO ALARMA DE NINGUN TIPO*** \r\n ");
                self::actualizarPerifericos($movil,$iomArr,$perFieldOutput,$manualRestartMethod);
            }
        }
        return $rta;    
    }
    public static function evaluaNb($arrIOM,$perFieldWorkMode,$posicion_id,$movil,$fecha){
        $rta         = array("rta"=>0,"estado_movil_id"=>7,"tipo_alarma_id"=>0);
        $keyNB=array_search('NB', $arrIOM);
        if( $keyNB ){
            $rta['estado_movil_id']= 10;//estado "en alarma"
            $rta['tipo_alarma_id'] = 32;//modo NB
            HelpMen::report($movil->equipo_id,"***EQUIPO EN MODO SILENCIOSO*** \r\n");
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'],'fecha_alarma'=>$fecha,'falsa'=>0]);
        }
        return $rta;
    }
    /*****si $perFieldWorkMode= 0 =>RESET no informo alertas de nada solo actualizo estado de movil****/
    public static function evaluaPanico($arrIOM,$perFieldWorkMode,$posicion_id,$movil,$fecha,$estadoArr){
        $rta         = array("rta"=>0,"estado_movil_id"=>7,"tipo_alarma_id"=>0); 
        $keyPanico=array_search('P', $arrIOM);
        if( $keyPanico ){
            $rta['estado_movil_id']= 10;//estado "en alarma"
            $rta['tipo_alarma_id'] = 1;//panico
            HelpMen::report($movil->equipo_id,"***PANICO ACTIVADO*** \r\n");
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'] ,'fecha_alarma'=>$fecha,'falsa'=>0]);
        }
        if($perFieldWorkMode== 4 && isset($estadoArr[0]) && $estadoArr[0]=="0" && $estadoArr[0]!="X"){ //si está en modo alarmas darle bola al campo panico en ALA
            $rta['estado_movil_id']= 10;//estado "en alarma"
            $rta['tipo_alarma_id'] = 1;//panico
            HelpMen::report($movil->equipo_id,"***PANICO ACTIVADO*** \r\n");
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'] ,'fecha_alarma'=>$fecha,'falsa'=>0]);
        }
        return $rta;
    }
    public static function evaluaCampoAla($estadoArr,$movil){
        $rta         = array("rta"=>0,"estado_movil_id"=>7,"tipo_alarma_id"=>0); 
        if($estadoArr[1]=="0" && $estadoArr[1]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 4;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA CONDUCTOR ABIERTA*** \r\n ");
        }
        if($estadoArr[1]=="1" && $estadoArr[1]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 10;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA CONDUCTOR CERRADA*** \r\n ");
        }
        if($estadoArr[2]=="0" && $estadoArr[2]!="X"){
           $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 24;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA ACOMPAÑANTE ABIERTA*** \r\n ");
        }
        if($estadoArr[2]=="1" && $estadoArr[2]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 25;
            HelpMen::report($movil->equipo_id,"\r\n ***PUERTA ACOMPAÑANTE CERRADA*** \r\n ");
        }
        if($estadoArr[7]=="0" && $estadoArr[7]!="X"){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 3;
            HelpMen::report($movil->equipo_id,"\r\n ***MOTOR ENCENDIDO*** \r\n ");
        }
        if( $estadoArr[3]==1 && $estadoArr[3]!="X"){
            $rta['estado_movil_id']= 5;
            $rta['tipo_alarma_id'] = 12;
            HelpMen::report($movil->equipo_id,"\r\n ***MOVIL DESENGANCHADO*** \r\n ");
        }
        if( $estadoArr[3]==0 && $estadoArr[3]!="X"){
            $rta['estado_movil_id']= 5;
            $rta['tipo_alarma_id'] = 5;
            HelpMen::report($movil->equipo_id,"\r\n ***MOVIL ENGANCHADO*** \r\n ");
         }
        if( $estadoArr[5]==1 && $estadoArr[5]!="X" ){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 9;
            HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA ABIERTA*** \r\n");
        }
        if( $estadoArr[5]==0 && $estadoArr[5]!="X" ){
            $rta['estado_movil_id']= 10;
            $rta['tipo_alarma_id'] = 11;
            HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA CERRADA*** \r\n");
        }
        return $rta;
    }
    /*I4: Desenganche=>0 = ENGANCHADO; 1 = DESENGANCHADO | I5: Antisabotaje=>0 = VIOLACION; 1 = NORMAL | I6: Compuerta=>0 = CERRADA; 1 = ABIERTA*/
    public static function cambiosInputIOM($imei,$iomArr,$sensorEstado,$movil,$estado_movil_id,$perFieldOutput,$manualRestartMethod){
        $rta         = array("rta"=>0,"estado_movil_id"=>$estado_movil_id,"tipo_alarma_id"=>0); //alarma_id=7 (Normal)
        HelpMen::report($movil->equipo_id,"*Evaluando cambios IOM* \r\n ");
        self::actualizarPerifericos($movil,$iomArr,$perFieldOutput,$manualRestartMethod);
        if($sensorEstado && $sensorEstado->iom){
            $estadoArr = str_split($sensorEstado->iom);
            //Log::info(print_r($estadoArr,true));
            if( $estadoArr[3]==0 && $iomArr[3]==1 && $iomArr[3]!="X"){
                $rta["tipo_alarma_id"]=12;
                $rta["estado_movil_id"]=5;
                $rta["rta"]            = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***MOVIL DESENGANCHADO*** \r\n ");
            }
            if( $estadoArr[3]==1 && $iomArr[3]==0 && $iomArr[3]!="X"){
                $rta["tipo_alarma_id"]=5;
                $rta["estado_movil_id"]=7;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***MOVIL ENGANCHADO*** \r\n");
            }
            if( $estadoArr[5]==0 && $iomArr[5]==1 && $iomArr[5]!="X" ){
                $rta["tipo_alarma_id"]=9;
                $rta["estado_movil_id"]=10;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA ABIERTA*** \r\n");
            }
            if( $estadoArr[5]==1 && $iomArr[5]==0 && $iomArr[5]!="X"){
                $rta["tipo_alarma_id"]=11;
                $rta["estado_movil_id"]=7;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***COMPUERTA CERRADA*** \r\n");
            }
            //if($iomArr[0]==1)HelpMen::report($movil->equipo_id,"\r\n ***PANICO ACTIVADO*** \r\n");
            if($iomArr[4]==0 && $iomArr[4]!="X"){
                $rta["tipo_alarma_id"]=6;
                $rta["estado_movil_id"]=10;
                $rta["rta"]           = 1;
                HelpMen::report($movil->equipo_id,"\r\n ***ANTISABOTAJE ACTIVADO*** \r\n");
            }
        }
        return $rta;
    }
    public static function updateSensores($imei,$movil,$perField,$io,$tipo_alarma_id,$estado_movil_id,$posicion_id,$fecha){
        DB::beginTransaction();
        try {
            if($perField!=""){
                EstadosSensores::where('imei', '=', $imei)->update(array('iom' => $perField));
            }else{
                EstadosSensores::where('imei', '=', $imei)->update(array('io' => $io));
            }
            self::persistSensor($imei,$posicion_id,$movil,$fecha,$tipo_alarma_id,$estado_movil_id);
            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            $logcadena = "Error al tratar alarmas IO..".$ex."\r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
        }
    }
    public static function persistSensor($imei,$posicion_id,$movil,$fecha,$tipo_alarma_id,$estado_movil_id){
        $movilOldId = intval($movil->movilOldId);
        $movil_id   = intval($movil->movil_id);
        DB::beginTransaction();
        try {
            if($tipo_alarma_id!=49 && $tipo_alarma_id!=0 ){//solo si es cualquier alarma distinta de alimentacion ppal
                Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>$movilOldId,
                            'tipo_alarma_id'=>$tipo_alarma_id,'fecha_alarma'=>$fecha,'falsa'=>0]);
            }
            Movil::where('movil_id', '=', $movil_id)->update(array('estado_movil_id' => $estado_movil_id));
            DB::commit();
            self::startupSensores();
        }catch (\Exception $ex) {
            DB::rollBack();
            $logcadena = "Error al tratar alarmas persistSensor \r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
        }
    }
    public static function getSensores($imei) {
        Log::info("buscando informacion de sensores de IMEI:".$imei);
        $shmid    = MemVar::OpenToRead('sensores.dat');
        if($shmid=='0'){
            $memoEstados    = self::startupSensores();
            Log::error("Buscando datos de sensores IMEI:".$imei);
        }else{
            MemVar::initIdentifier($shmid);
            $memoEstados    = MemVar::GetValue();
            $memoEstados    = json_decode($memoEstados);
        }
        /*si encuentro el movil y el sensor difiere al enviado por parametro
        genero un nuevo elemento y lo cargo en el array y en la ddbb
        elimino el elemento anterior del array, limpio y vuelvo a cargar la memoria
        */
        $encontrado     = HelpMen::binarySearch($memoEstados, 0, count($memoEstados) - 1, $imei);
        return $encontrado;
        
    }
    public static function startupSensores(){
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
        $memvar     = MemVar::Instance('sensores.dat');
        $enstring   = json_encode($estados);
        $largo      = (int)strlen($enstring);
        $memvar->init('sensores.dat',$largo);
        $memvar->setValue( $enstring );
        $memoEstados= json_decode($enstring);
        return $memoEstados;
    
    }
    public static function actualizarPerifericos($movil,$estadoArr,$perFieldOutput,$manualRestartMethod){
        Log::info(":::::::Actualizando datos de Perifericos:::");
        try{
            PerifericoController::setSensores($movil->equipo_id,$estadoArr,$perFieldOutput,$manualRestartMethod);
        }catch (\Exception $ex) {
            DB::rollBack();
            $errorSolo  = explode("Stack trace", $ex);
            $logcadena ="Error al procesar update de comandos ".$errorSolo[0]." \r\n";
            Log::info($logcadena);
        }
    }
    
}