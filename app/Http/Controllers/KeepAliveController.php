<?php
namespace App\Http\Controllers;
use App\ColaMensajes;
use App\Helpers\HelpMen;
use function ArrayIterator\count;
use function Faker\Provider\ja_JP\Text\explode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Exception;
class KeepAliveController extends BaseController
{
  const EN_MOVIMIENTO = 1;
  const EN_DETENIDO   = 0;
  public function index(Request $request) {
    return "ok";
  }      //
  public function create(Request $request){
    $method   = $request->method();
    $comando  = "";
    if ($request->isMethod('post')) {
      $jsonReq      = $request->json()->all();
      if(isset($jsonReq["cadena"])){
        $movil    = HelpMen::movilesMemoria($jsonReq["cadena"]);
        if($movil){
          $mensaje  = $this->obtenerComandoPendiente($movil->equipo_id); 
          if($mensaje){
            if($mensaje->comando!="" && !is_null($mensaje->comando)){
            // Log::error(print_r($mensaje, true));
              if(isset($mensaje->auxiliar) && !is_null($mensaje->auxiliar) && $mensaje->auxiliar!=""){
                 $auxParams = explode(",",$mensaje->auxiliar);
                 $valorSet  = (isset($auxParams[1])&&$auxParams[1]=='2')?"?2,".$auxParams[2]:'='.$mensaje->auxiliar;
              }else{
                $valorSet="?";
              }
              $comando="AT".$mensaje->comando.$valorSet."\r\n";
            }else{
              $comando="AT".$this->decodificarComando($mensaje,$movil)."\r\n";
            }
          }else{
            $comando  ="AT+OK\r\n"; 
          }
          $logcadena ="KeepAlive IMEI:".$movil->imei." - equipo:".$movil->equipo_id." - Comando:".$comando." \r\n";
          HelpMen::report($movil->equipo_id,$logcadena);
        }else{
          Log::error("El IMEI:".$jsonReq["cadena"]. "no Existe en la DDBB, se desecha el reporte");
        }
        
       }elseif($jsonReq["KEY"]=="KA"){
        //por ahora devuelvo este de ejemplo
        $comando  ="AT+GETGP?\r\n";
      }else{
        return "ERROR:Json mal formado!";
        Log::error("Error:json mal formado, ver palabra clave");
      }
    }
    return  $comando;
  }
  public function tratarReporte($cadena,$movil){
    $rta  = "";
    try{
      $logcadena ="Equipo=>".$movil->equipo_id." MOVIL=>".$movil->movilOldId."-Cadena=>".$cadena." \r\n";
      HelpMen::report($movil->equipo_id,$logcadena);
      $rta  = app()->Puerto->analizeReport($cadena,$movil) ;
    }catch(Exception $e){
      $rta  = "error";
      Log::error($e);
    }
    return $rta;
  } 
 
 public function obtenerComandoPendiente($equipo_id){
    $mensaje            = false;
    $flagEnviarComando  = 0;
    /*primero ver si hay un OUTS pendiente con tipo_posicion!=69 i !=70 =>, mando el outs y lo pongo en 69
    sino, mando cualquier otro que sea distinto de OUTS tipo_comando_id=22,
    para esto busco el comando con OUTPendiente, devuelvo $mensaje y sigo el tratamiento
    todas las actualizaciones de comandos de abajo deben excluir al tipo_comando_id=22
    */
    /*Identificar si el equipo está en test, si es asi ejecutar los comandos en orden de prioridades
    sino, resolver el OUT con mas alta prioridad*/
    ///ver si hay comando pendiente///
    $mensajePendiente            = ColaMensajes::where('modem_id', '=',$equipo_id)->where('rsp_id','=',1)
                                  ->orderBy('prioridad','DESC')->get()->first(); 
    if($mensajePendiente){
      //1-obtener comandos con 3 intentos y ponerlos en estado rsp_id=6->sin respuesta
      $esEnTest = $this->isMovilInTest($equipo_id);
      if( is_null($esEnTest) || count($esEnTest)==0 ){ //si no está en test doy prioridad a los OUTS
        $flagEnviarComando  = 1;
        $outmsj             = $this->OUTPendiente($equipo_id);
        $mensaje            = (is_null($outmsj))?$mensajePendiente:$outmsj;
      }else{//si está en test ejecuto uno a uno por prioridad
        $logcadena ="Ejecutando Test Equipo::".$equipo_id." Comandos en test:".count($esEnTest)." \r\n";
        HelpMen::report($equipo_id,$logcadena);
        $comandoAEnviar15   = $this->kaReportFrecuency($equipo_id,15);
        if($comandoAEnviar15){
          $mensaje          = $comandoAEnviar15;
        }else{
          $comandoAEnviar60 = $this->kaReportFrecuency($equipo_id,60);
          $mensaje          = ($comandoAEnviar60)?$mensajePendiente:null;
        }
      }
      DB::beginTransaction();
      try {
        if($mensaje){
          $mensaje->rsp_id        = 2;
          $mensaje->tipo_posicion = 69;
          //$mensaje->fecha_final   = date("Y-m-d H:i:s");
          //Log::info("Fecha final:".date("Y-m-d H:i:s"));
          $mensaje->intentos      += 1;
          $mensaje->save();
        }
        $tr_id  = ($mensaje)?$mensaje->tr_id:1;
        //3-los comandos en rsp_id=2 e intentos <3 ->los seteo en pendiente rsp_id=1 y les sumo 1 al intentos, excepto al obtenido para rta
        $mensajeUP  = ColaMensajes::where('modem_id', '=',$equipo_id)
                                  ->where('rsp_id','=',2)->where('intentos','<',5)->where('cmd_id','<>',22)
                                  ->where('tr_id','<>',$tr_id)
                                  ->increment('intentos', 1, ['rsp_id'=>1]);
        DB::commit();
      }catch (\Exception $ex) {
        DB::rollBack();
        $errorSolo  = explode("Stack trace", $ex);
        $logcadena ="Error al procesar el KA ".$errorSolo[0]." \r\n";
        HelpMen::report($equipo_id,$logcadena);
      }
    }else{
      $mensaje=false;
    }
    
    return $mensaje;
  }
  public function OUTPendiente($equipo_id){
    $outMs    = null;
    $outMs    = ColaMensajes::where('modem_id', '=',$equipo_id)
                                ->where('rsp_id','=',1)->where('cmd_id','=',22)
                                ->where('tipo_posicion','<>',69)->where('tipo_posicion','<>',70)
                                ->orderBy('prioridad','DESC')
                                ->get()->first(); 
    return $outMs;
  }
  public function isMovilInTest($equipo_id){
    $movilTest = false;
    if(DB::connection()->getDatabaseName()=='moviles'){
      config()->set('database.default', 'siac');
      $movilTest = DB::table('TEST_COMANDO')
                       ->select(DB::raw('count(*) as comandos'))
                       ->where('fin', '=', 0)->where('modem_id', '=',$equipo_id)
                       ->groupBy('modem_id')
                       ->get();
    }
    config()->set('database.default', 'moviles');
    Log::error(print_r($movilTest,true));
    return $movilTest;
  }
  public function kaReportFrecuency($equipo_id,$aux){
    $kaNormal    = null;
    $kaNormal    = ColaMensajes::where('modem_id', '=',$equipo_id)
                                ->where('rsp_id','<>',3)->where('rsp_id','<>',5)->where('comando','=','+KA')
                                ->where('auxiliar','=',$aux)
                                ->orderBy('prioridad','DESC')
                                ->get()->first(); 

    
    return $kaNormal;
  }
  public function decodificarComando($mensaje,$movil){
    //si el aux viene vacio....es una consulta mandar solo el ?
    //falta guardar en memoria el estado de velocidad max del equipo y tiempo de veloc max
    $auxParams  = explode(",",$mensaje->auxiliar);
    switch ($mensaje->cmd_id) {
      case 17:
        $cadenaComando  = "+GETGP?";
        break;
      case 20://frecuencias y velocidades
        if(isset($auxParams[1])){
          switch ($auxParams[1]) {
            case '6':
              $valorSet   = ($auxParams[2]=='' || is_null($auxParams[2]))?'?':"=0,".self::EN_MOVIMIENTO.",".$auxParams[2];
              $cadenaComando = "+FR".$valorSet;
              break;
            case '7':
              $valorSet   = ($auxParams[2]=='' || is_null($auxParams[2]))?'?':"=0,".self::EN_DETENIDO.",".$auxParams[2];
              $cadenaComando = "+FR".$valorSet;
              break;
            case '20':
              //seteo tiempo de reporte en veloc max ALV=t,v
              $valorSet   = ($auxParams[2]=='' || is_null($auxParams[2]))?'?':"=".$movil->velocidad_max.",".$auxParams[2];
              Log::info("valorset en:::".$valorset." el auxiliar:::".$mensaje->auxiliar);
              $cadenaComando = "+ALV".$valorSet;
              break;
            case '23':
              //seteo velocidad max max ALV=t,v
              $valorSet   = ($auxParams[2]=='' || is_null($auxParams[2]))?'?':"=".$auxParams[2].",".intval($movil->frec_rep_exceso_vel*60);
              $cadenaComando = "+ALV".$valorSet;
              break;
            case '2':
            //caso especial donde se consulta frecuencias
              $valorSet   = "?2,".$auxParams[2];
              $cadenaComando = "+FR".$valorSet;
              break;
            default:
              # code...
              break;
          }
        }else{
          $cadenaComando = "+FR?0,0";
        }
        
        break;
      case 22://modo corte(aux=1,2->0,1) y modo normal(aux=1,1->0,0)
          /*encontrar el tipo de instalacion...si tiene IOM resolver de otra manera*/
          if(!$movil->perif_io_id){ //moviles sin IOM 
              if(isset($auxParams[1]) && !is_null($auxParams[1]) && $auxParams[1]!=''){
                  $valor      = ($auxParams[1]=="2")?"1":"0";
                  $valorSet   = "=0,".$valor;
              }else{
                  $valorSet   = '?';
              }
              $cadenaComando = "+OUTS".$valorSet;
          }else{ //moviles con IOM
              switch ($auxParams[1]) {
                  case 0:
                      $valorSet="CMD_RESET";
                      break;
                  case 1:
                      $valorSet="CMD_NORMAL";
                      break;
                  case 2:
                      $valorSet="CMD_CORTE";
                      break;
                  case 3:
                      $valorSet="CMD_BLQINH";
                      break;
                  case 4:
                      $valorSet="CMD_ALARMAS";
                      break;
              }
              $cadenaComando = "+PER=IOM,".$valorSet;
          }
        break;
      case 100://reset
        $cadenaComando = "+RES=".$auxParams[0];
        break;
      case 134: //conf.sensores que generan corte
          $cadenaComando="+PER=IOM,HAS,".$mensaje->auxiliar;
       break;
      default:
        $cadenaComando  = "+GETGP?";
        break;
    }
    return $cadenaComando;
  }
}
