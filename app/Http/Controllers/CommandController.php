<?php
namespace App\Http\Controllers;
use App\ColaMensajes;
use App\InstalacionSiac;
use App\Helpers\HelpMen;
use App\Helpers\RedisHelp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Exception;

class CommandController extends BaseController
{
    const ESTADO_PENDIENTE = 1;
    const ESTADO_EN_PROCESO = 2;
    const ESTADO_OK = 3;
    const POSICION_ACTUAL=17;
    const CONFIGURAR_FRECUENCIAS=20;
    const CAMBIO_MODO_PRESENCIA_GPRS=22;
    const RESET_GPRMC=100;
    static $comandoDefinitions  = array("+GETGP"=>17,"+FR"=>20,"+ALV"=>20,"+RES"=>100,"+OUTS"=>22,"+VER"=>00,
          "+PER=IOM,HAS"=>134,"+PER=IOM,INI"=>106,"+PER=IOM,CMD_NORMAL"=>22,"+PER=IOM,CMD_CORTE"=>22,
          "+PER=IOM,CMD_BLOQINH"=>22,"+PER=IOM,CMD_ALARMAS"=>22,"+PER=IOM,CMD_RESET"=>22,"+PER=IOM,ERROR"=>22,
          "+PER=IOM,CFG_CORTE"=>112,"+PER=IOM,RES"=>115);
    static $comandoGenerico     = array("+GEN"=>666); 
    public function index(Request $request) {
      //return "ok";
    }      //
    public function create(Request $request){
      $method = $request->method();
      $response = "";
      if ($request->isMethod('post')) {
              $jsonReq = $request->json()->all();
        if(isset($jsonReq["cadena"])){
          try{
            //app()->Puerto->analizeReport($jsonReq['cadena']) ;
            //$response ="AT+GETGP?";//"$9\r\nAT+GETGP?\r\n";
            Log::info("cadena entrante en CommansController ::".$jsonReq['cadena']);
          }catch(Exception $e){
            Log::error($e);
          }
          return $response;
        }else{
          return "ERROR:Json mal formado!";
          Log::error("Error:json mal formado, ver palabra clave");
        }
      }else{
        return "ERROR:Metodo no permitido";
        Log::error("Error:metodo no permitido,utilizar POST");
      }
    }
    public function update(Request $request, $imei) {
        $comandoRta = $request->input('rta');
        $estado     = $request->input('estado_comando_id');
        $estadotipo_posicion = 66;
        ///Log::info('recibido en dapa, IMEI:'.$imei.' rta:'.$comandoRta.' estado:'.$estado.' se obtuvo:'.$arrCmdRta[0]." de CMD_ID:".self::$comandoDefinitions[$arrCmdRta[0]]);
        $movil    = HelpMen::compruebaMovilRedis($imei);
        if($movil){
            $equipo_id= $movil->equipo_id;
            if($movil->lastCommand!='NO'){
                $pendiente    = ColaMensajes::where('tr_id', '=',$movil->lastCommand)->get()->first();
                $commandoId   = $pendiente->cmd_id;
                $logcadena = " Respuesta TEST IMEI:".$imei." - Equipo:".$equipo_id." rta:".$comandoRta." de CMD_ID:".$commandoId." \r\n";
                HelpMen::report($equipo_id,$logcadena);
                $pendiente->rsp_id      = 3;
               // $pendiente->comando     = $arrCmdRta[0];
                $pendiente->respuesta   = $comandoRta;
                $pendiente->fecha_final = date("Y-m-d H:i:s");
                $pendiente->save();
                $logcadena = " actualizacion correcta :AT+OK \r\n";
                //si tengo un reporte en redis, busco y actualizo el QEA con ese reporte
                //
                $this->actualizaQea($equipo_id,$movil);
                
                //elimino el comando ya tratado de redis y limpio el lastCommand
                $arrCmd    = explode(",",$movil->test);
                if(count($arrCmd)>1){//fifo, elimino el primero ya tratado
                    array_shift($arrCmd);
                    $commandList=implode(",",$arrCmd);
                }else{
                    $commandList='0';
                }
                RedisHelp::setCommandListTest($imei,$commandList);
                RedisHelp::setLastCommand($imei, "NO");
            }else{
               $arrCmdRta  = explode(":",$comandoRta);//esto porque una rta es del tipo +PER:IOM,CMD_CORTE,OK->separo el encabezado para buscar en el array de definiciones
                HelpMen::report($equipo_id," commandController::".$arrCmdRta[0]." \r\n");
                $commandoId = (isset(self::$comandoDefinitions[$arrCmdRta[0]]))?self::$comandoDefinitions[$arrCmdRta[0]]:self::$comandoGenerico["+GEN"];
                if($arrCmdRta[0]=="+PER"){
                    HelpMen::report($equipo_id," commandController::".$arrCmdRta[1]." \r\n");
                    $mensaje  = $this->tratarIOM($equipo_id,$arrCmdRta[1]);
                }elseif ($mensaje  = $this->tratarIOM($equipo_id,$arrCmdRta[1])){
                    $mensaje  = $this->tratarOUTS($equipo_id,$arrCmdRta[1]);
                }else {
                    $mensaje  = ColaMensajes::where('modem_id', '=',$equipo_id)
                    ->where('rsp_id','=',2)
                    ->where('cmd_id','=',$commandoId)->where('cmd_id','<>',22)
                    ->orderBy('prioridad','DESC')
                    ->get()->first();
                    //Log::info("Equipo:".$equipo_id." de CMD_ID:".$commandoId." cajeta...". print_r($mensaje,true));
                }
                $logcadena = " Respuesta IMEI:".$imei." - Equipo:".$equipo_id." rta:".$comandoRta." de CMD_ID:".$commandoId." \r\n";
                HelpMen::report($equipo_id,$logcadena);
                if(!is_null($mensaje)){
                    $mensaje->rsp_id      = 3;
                    $mensaje->comando     = $arrCmdRta[0];
                    $mensaje->respuesta   = $comandoRta;
                    $mensaje->fecha_final = date("Y-m-d H:i:s");
                    $mensaje->save();
                    $logcadena = " actualizacion correcta :AT+OK \r\n";
                    //actualizo la instalacion para reflejar el cambio de frecuencias
                    $instalacionMovil = InstalacionSiac::where('modem_id',$equipo_id)->get()->first();
                    if($commandoId==20){
                        $valoresArr = explode(",",$arrCmdRta[1]);
                        if($arrCmdRta[0]=='+FR'){
                          if($valoresArr[1]==1){
                            $instalacionMovil->frecuencia_reporte_velocidad = $valoresArr[2];
                          }
                          if($valoresArr[1]==0){
                            $instalacionMovil->frecuencia_reporte_detenido  = $valoresArr[2];
                          }
                        }
                        if($arrCmdRta[0]=='+ALV'){
                          $instalacionMovil->frecuencia_reporte_exceso_velocidad  = $valoresArr[0];
                        }
                        $instalacionMovil->save();
                    } 
                    HelpMen::report($equipo_id,$logcadena);
                    return "AT+OK \r\n";
                }else{
                    $logcadena = "No existe comando pendiente \r\n";
                    HelpMen::report($equipo_id,$logcadena);
                }
            
            }
            
        }else{
            Log::error("IMEI:".$imei. " NO TIENE INSTALACION->KA \r\n");
        }
        
    }
    public function tratarOUTS($equipo_id,$valor){
      $OUTPendiente = null;
      $OUTPendiente = $this->OUTPendiente($equipo_id);
      if(!is_null($OUTPendiente)){
        if($OUTPendiente->auxiliar=='0,1' && $valor=='10'){//activar modo corte
          $logcadena = "Modo Corte activado equipo:".$equipo_id." \r\n";
          HelpMen::report($equipo_id,$logcadena);
          $OUTPendiente->tipo_posicion  = 70;
        }
        if($OUTPendiente->auxiliar=='0,0' && $valor=='00'){//activar modo corte
          $logcadena = "Modo Corte desactivado equipo:".$equipo_id." \r\n";
          HelpMen::report($equipo_id,$logcadena);
          $OUTPendiente->tipo_posicion  = 70;
        }
      }
      return $OUTPendiente;
    }
    public function OUTPendiente($equipo_id){
      $outMs    = null;
      $outMs    = ColaMensajes::where('modem_id', '=',$equipo_id)
                                  ->where('rsp_id','=',2)->where('cmd_id','=',22)
                                  ->where('tipo_posicion','=',69)
                                  ->orderBy('prioridad','DESC')
                                  ->get()->first(); 
      return $outMs;
    }
    public function tratarIOM($equipo_id,$valor){
        HelpMen::report($equipo_id,$valor." \r\n");
        $logcadena="....";
        $arrVal=explode(",",$valor);
        $OUTPendiente = null;
        $comandosSeteo= array("HAS","CFG_CORTE","RES","INI");
        if(isset($arrVal[1]) && in_array($arrVal[1],$comandosSeteo)){
            $busqueda= "+PER=IOM,".$arrVal[1];
            $OUTPendiente = $this->IOMPendiente($equipo_id,self::$comandoDefinitions[$busqueda]);
            $OUTPendiente->tipo_posicion  = 70;
            HelpMen::report($equipo_id,"SETEO DE:".$arrVal[1]." ID:".self::$comandoDefinitions[$busqueda]." \r\n");
        }else{
            $OUTPendiente = $this->OUTPendiente($equipo_id);//primero trato el OUT
            if(!is_null($OUTPendiente) && isset($arrVal[1])){
                switch ($arrVal[1]){
                    case'CMD_NORMAL':
                        $logcadena = "Modo normal activado equipo:".$equipo_id." \r\n";
                        break;
                    case'CMD_CORTE':
                        $logcadena = "Modo corte activado equipo:".$equipo_id." \r\n";
                        break;
                    case'CMD_BLOQINH':
                        $logcadena = "Modo Bloqueo Inhibicion activado equipo:".$equipo_id." \r\n";
                        break;
                    case'CMD_ALARMAS':
                        $logcadena = "Modo Alarmas activado equipo:".$equipo_id." \r\n";
                        break;
                    case'CMD_RESET':
                        $logcadena = "Modo Reset activado equipo:".$equipo_id." \r\n";
                        break;
                    case 'ERROR':
                        $logcadena = "Modulo IOM reporta error en equipo:".$equipo_id." \r\n";
                        break;
                }
            $OUTPendiente->tipo_posicion  = 70;
            }
        }
        HelpMen::report($equipo_id,$logcadena);
        return $OUTPendiente;
    }
    public function IOMPendiente($equipo_id,$cmd){
        $outMs    = null;
        $outMs    = ColaMensajes::where('modem_id', '=',$equipo_id)
        ->where('rsp_id','=',2)->where('cmd_id','=',$cmd)
        ->where('tipo_posicion','=',69)
        ->orderBy('prioridad','DESC')
        ->get()->first();
        return $outMs;
    }
    public function actualizaQea($equipo_id,$movil){
        //rta-->+PER:QEA,OK
        $qea    = null;
        $qea    = ColaMensajes::where('modem_id', '=',$equipo_id)
        ->where('comando','=','+PER')
        ->orderBy('fecha_inicio','DESC')
        ->get()->first();
        if(!is_null($qea)){
            $qea->respuesta  = $movil->report;
            $qea->save();
        }
        //return $outMs;
    }
}

