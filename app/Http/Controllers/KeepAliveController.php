<?php
namespace App\Http\Controllers;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
Use Log;
use stdClass;
use Storage;
use DB;
/*DDBB Principal*/
use App\ColaMensajes;
/*Helpers*/
use App\Helpers\MemVar;
use App\Helpers\HelpMen;
use GuzzleHttp\Client;
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
        //$rta      = $movil->equipo_id;
        $mensaje  = $this->obtenerComandoPendiente($movil->equipo_id); 
        if($mensaje){
          if($mensaje->comando!='' && !is_null($mensaje->comando)){
            $comando="AT".$mensaje->comando.'='.$mensaje->auxiliar."\r\n";
          }else{
            $comando="AT".$this->decodificarComando($mensaje,$movil)."\r\n";
          }
        }else{
          $comando  ="AT+OK\r\n"; 
        }
        Log::info("KeepAlive equipo:".$movil->equipo_id." - Comando:".$comando);
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
      Log::error("Equipo=>".$movil->equipo_id." MOVIL=>".$movil->movilOldId."-Cadena=>".$cadena);
      $rta  = app()->Puerto->analizeReport($cadena,$movil) ;
    }catch(Exception $e){
      $rta  = "error";
      Log::error($e);
    }
    return $rta;
  } 
 
  public function obtenerComandoPendiente($equipo_id){
    $mensaje  = false;
    $mensaje  = ColaMensajes::where('modem_id', '=',$equipo_id)
                                ->where('rsp_id','=',1)->orderBy('prioridad','DESC')
                                ->get()->first(); 
    if($mensaje){
      $mensaje->rsp_id      = 2;
      $mensaje->fecha_final = date("Y-m-d H:i:s");
      $mensaje->save();
    }
    
    return $mensaje;
  }
  public function decodificarComando($mensaje,$movil){
    
    //falta guardar en memoria el estado de velocidad max del equipo y tiempo de veloc max
    $auxParams  = explode(",",$mensaje->auxiliar);
    switch ($mensaje->cmd_id) {
      case 17:
      Log::info("entra por 17");
        $cadenaComando  = "+GETGP?";
        break;
      case 20://frecuencias y velocidades
      Log::info("entra por 20");
        switch ($auxParams[1]) {
          case '6':
            $cadenaComando = "+FR=0,".self::EN_MOVIMIENTO.",".$auxParams[2];
            break;
          case '7':
            $cadenaComando = "+FR=0,".self::EN_DETENIDO.",".$auxParams[2];
            break;
            case '20':
            //seteo tiempo de reporte en veloc max ALV=t,v
            $cadenaComando = "+ALV=".$movil->velocidad_max.",".$auxParams[2];
            break;
            case '23':
            //seteo velocidad max max ALV=t,v
            $cadenaComando = "+ALV=".$auxParams[2].",".intval($movil->frec_rep_exceso_vel*60);
            break;
          default:
            # code...
            break;
        }
        break;
      case 22://modo corte(aux=1,2->0,1) y modo normal(aux=1,1->0,0)
        Log::info("entra por 22 y auxParams:".$auxParams[1]);
        $valor  = ($auxParams[1]=="2")?"1":"0";
        $cadenaComando = "+OUTS=0,".$valor;
        break;
      case 100://reset
      Log::info("entra por 100");
        $cadenaComando = "+RES=".$auxParams[0];
        break;
      default:
      Log::info("entra por default");
        $cadenaComando  = "+GETGP?";
        break;
    }
    return $cadenaComando;
  }
}
