<?php
namespace App\Http\Controllers;
use App\ColaMensajes;
use function ArrayIterator\count;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Exception;
use App\Helpers\HelpMen;
use App\Helpers\CommandHelp;

class KeepAliveController extends BaseController
{
  const EN_MOVIMIENTO = 1;
  const EN_DETENIDO   = 0;
  public function index(Request $request) {
    return "ok";
  }      //
  public function create(Request $request){
    $method   = $request->method();
    $comando  ="AT+OK\r\n"; 
    if ($request->isMethod('post')) {
    $jsonReq      = $request->json()->all();
    if(isset($jsonReq["cadena"])){
        $movil      = HelpMen::compruebaMovilRedis($jsonReq["cadena"]);
        if($movil){
            $commandHelp= new CommandHelp($this->equipo);
            $mensaje    = $this->obtenerComandoPendiente($movil->equipo_id,$commandHelp); 
            if($mensaje){
                $comando= $this->generarCadenaComando($mensaje, $comando,$commandHelp);
            }
            $logcadena ="\r\n KeepAlive IMEI:".$movil->imei." - equipo:".$movil->equipo_id." - Comando:".$comando." \r\n";
            HelpMen::report($movil->equipo_id,$logcadena);
        }else{
            Log::error("KA->El IMEI:".$jsonReq["cadena"]. "no Existe en la DDBB, se desecha el reporte");
        }
        
    }elseif($jsonReq["KEY"]=="KA"){
    //por ahora devuelvo este de ejemplo
    $comando  =" \r\n AT+GETGP?\r\n";
    }else{
    return "ERROR:Json mal formado!";
    Log::error("Error:json mal formado, ver palabra clave");
    }
    }
return  $comando;
}
  
public function obtenerComandoPendiente($equipo_id,$commandHelp){
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
        $esEnTest    = $commandHelp->isMovilInTest();
        if( is_null($esEnTest) || $esEnTest[0]->comandos==0 ){ //si no está en test doy prioridad a los OUTS
            $flagEnviarComando  = 1;
            $outmsj             = $this->OUTPendiente($equipo_id);
            $mensaje            = (is_null($outmsj))?$mensajePendiente:$outmsj;
        }else{//si está en test ejecuto uno a uno por prioridad
            $mensaje= TestController::tratarTest($mensajePendiente,$equipo_id);
        }
        $mensaje    = $commandHelp->intentarComando($mensaje,$equipo_id);
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
                                ->orderBy('prioridad','DESC')->get()->first(); 
    return $outMs;
}
public function generarCadenaComando($mensaje,$comando,$commandHelp){
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
        $comando="AT".$commandHelp->decodificarComando($mensaje,$movil)."\r\n";
    }
    return $comando;
}
  
  
}
