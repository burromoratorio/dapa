<?php
namespace App\Http\Controllers;

use App\Alarmas;
use App\EstadosSensores;
use App\Movil;
use App\Helpers\HelpMen;
use App\Helpers\MemVar;
use App\Helpers\RedisHelp;

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
        if($movil->perif_io_id && $movil->perif_io_id!=''){//tiene instalado IOM
            HelpMen::report($movil->equipo_id,"movil con iom");
            if($perField!='NULL'){//analisis en bits sensores IOM y ALA
                HelpMen::report($movil->equipo_id,"reporte solo de posicion sin reporte de cadena iom");
                $cambioBits = self::analisisIOM($perField,$imei,$posicion_id,$movil,$fecha,$estadoMovilidad);
            }else{//evaluo bateria del IO por mas que tenga IOM el equipo
                HelpMen::report($movil->equipo_id,"reporte solo de posicion evaluo bateria y demas porque tiene iom");
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
        $estado_movil_id=$estadoMovilidad;
        $tipo_alarma_id=7;
//si no tiene posicion_id y es una alarma de panico , informar mail?¡
        if($ioData[0]=="I00"){//ingreso de alarma de panico bit en 0
            $logcadena = "Panico presionado Equipo:".$imei." - Movil:".$movilOldId."\r\n";
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
        /*cambios de estado IO alarmas de bateria*/
        $io             = str_replace("I", "",$ioData[1] );
        $sensorEstado   = $movil->io;//self::getSensores($imei);
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
        if( (!$movil->perif_io_id || $movil->perif_io_id=='')  && $sensorEstado==''){
            HelpMen::report($movil->equipo_id,"Datos de sensores IO vacios en memoria, generando...");
            DB::beginTransaction();
            try {
                EstadosSensores::create(['imei'=>$imei,'movil_id'=>intval($movil->movil_id),'io'=>$io]);
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
                self::updateSensores($movil,"",$io,$tipo_alarma_id,$estado_movil_id,$posicion_id,$fecha);
            }
        }
        RedisHelp::setEstadosMovil($movil, '', $io);
        return $rta;
    }
    /************Analisis de cadena IOM*********/
    /*$arrPeriferico[0]=IOM,  $arrPeriferico[1]=I1..I14, $arrPeriferico[2]=O1..O14, $arrPeriferico[3]=E(modo de trabajo del equipo)
    $arrPeriferico[4]=PR(método de restablecimiento Manual),  $arrPeriferico[5]=NB(Normal o backgrond),  $arrPeriferico[6]=P (Panico) 
    si algun sendor trae el caracter X entonces no lo tengo en cuenta */
    //$perField -->campo PER completo que viene en cadena de posicion
    public static function analisisIOM($perField,$imei,$posicion_id,$movil,$fecha,$estado_movil_id){
        $arrIOM      = explode(',',$perField);
        //string del tipo:IOM,10111000011110,000000XXXX,4,1,ALA,XX1XXXXXXXXXXX    
        $sensorEstado= $movil->iom;
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
                    $rta = self::evaluaCampoAla($estadoArr,$movil);
                    if($rta["tipo_alarma_id"]>0){
                        Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta["tipo_alarma_id"],
                                        'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0','usuario_id'=>980]);
                        $rta["estado_movil_id"]=10;
                        $rta["rta"]            = 1;
                    }
                }
                $rta = self::evaluaPanico($arrIOM,$perFieldWorkMode,$posicion_id,$movil,$fecha,$estadoArr);
                $rta = self::evaluaNb($arrIOM,$perFieldWorkMode,$posicion_id,$movil,$fecha);               
                //luego del analisis actualizo los datos de sensores, primero analizo e informo alarmas, y estado del movil
                if(is_null($sensorEstado) || $sensorEstado=='' ){
                    $sensorNuevo  = EstadosSensores::where('imei', '=',$movil->imei)->orderBy('updated_at','DESC')->first();
                    if(!$sensorNuevo){//si no tenia en la ddbb data
                        HelpMen::report($movil->equipo_id,"Datos de sensores vacios en DDBB, generando...");
                        DB::beginTransaction();
                        try {
                            EstadosSensores::create(['imei'=>$movil->imei,'movil_id'=>intval($movil->movil_id),'iom'=>$perFieldInput]);
                            DB::commit();
                        }catch (\Exception $ex) {
                            DB::rollBack();
                            $logcadena = "Error al dar de alta sensor IOM..".$ex."\r\n";
                            HelpMen::report($movil->equipo_id,$logcadena);
                        }
                        self::persistSensor($posicion_id,$movil,$fecha,$rta["tipo_alarma_id"],$rta["estado_movil_id"]);
                    }else{//si tenía pero posiblemente solo de IO, genero el IOM
                        HelpMen::report($movil->equipo_id,"Actualizando datos de IOM en instalacion que antes tenia IO");
                        self::updateSensores($movil,$perFieldInput,"",$rta["tipo_alarma_id"],$rta["estado_movil_id"],$posicion_id,$fecha);
                        //poner tambien el actualiza perifericos
                        self::actualizarPerifericos($movil,$iomArr,$perFieldOutput,$manualRestartMethod);
                    }
                    
                }else{
                    HelpMen::report($movil->equipo_id,"Actualizando datos de IOM en instalacion que antes tenia IO");
                    $rta = self::cambiosInputIOM($iomArr,$sensorEstado,$movil,$estado_movil_id,$perFieldOutput,$manualRestartMethod);
                    if($rta["rta"]==1)
                    self::updateSensores($movil,$perFieldInput,"",$rta["tipo_alarma_id"],$rta["estado_movil_id"],$posicion_id,$fecha);
                }
                
            }else{
                /*Dicen que cuando se pone en este modo ahora hay que actualizar los datos...*/
                HelpMen::report($movil->equipo_id,"\r\n **EQUIPO EN MODO RESET...NO INFORMO ALARMA DE NINGUN TIPO*** \r\n ");
                self::actualizarPerifericos($movil,$iomArr,$perFieldOutput,$manualRestartMethod);
            }
            RedisHelp::setEstadosMovil ($movil, $perField, '');
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
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'],
                            'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0','usuario_id'=>980]);
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
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'] ,
                        'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0','usuario_id'=>980]);
        }
        //si está en modo alarmas darle bola al campo panico en ALA
        if($perFieldWorkMode== 4 && isset($estadoArr[0]) && $estadoArr[0]=="0" && $estadoArr[0]!="X"){ 
            $rta['estado_movil_id']= 10;//estado "en alarma"
            $rta['tipo_alarma_id'] = 1;//panico
            HelpMen::report($movil->equipo_id,"***PANICO ACTIVADO*** \r\n");
            Alarmas::create(['posicion_id'=>$posicion_id,'movil_id'=>intval($movil->movilOldId),'tipo_alarma_id'=>$rta['tipo_alarma_id'] ,
                        'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0','usuario_id'=>980]);
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
    public static function cambiosInputIOM($iomArr,$sensorEstado,$movil,$estado_movil_id,$perFieldOutput,$manualRestartMethod){
        $rta         = array("rta"=>0,"estado_movil_id"=>$estado_movil_id,"tipo_alarma_id"=>0); //alarma_id=7 (Normal)
        HelpMen::report($movil->equipo_id,"*Evaluando cambios IOM* \r\n ");
        self::actualizarPerifericos($movil,$iomArr,$perFieldOutput,$manualRestartMethod);
        $estadoArr = explode(',',$sensorEstado);
        if($estadoArr[0]=='IOM' && $estadoArr[1]){
            $estadoArr = str_split($estadoArr[1]);
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
    public static function updateSensores($movil,$perField,$io,$tipo_alarma_id,$estado_movil_id,$posicion_id,$fecha){
        DB::beginTransaction();
        try {
            if($perField!=""){
                EstadosSensores::where('imei', '=',$movil->imei)->update(array('iom' => $perField));
            }else{
                EstadosSensores::where('imei', '=', $movil->imei)->update(array('io' => $io));
            }
            self::persistSensor($posicion_id,$movil,$fecha,$tipo_alarma_id,$estado_movil_id);
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
                            'fecha_alarma'=>$fecha,'falsa'=>0,'nombre_estacion'=>'GSM0','usuario_id'=>980]);
            }
            Movil::where('movil_id', '=', $movil_id)->update(array('estado_movil_id' => $estado_movil_id));
            DB::commit();
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
        //Log::info($enstring);
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
