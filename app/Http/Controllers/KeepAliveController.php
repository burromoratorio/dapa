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
        $movil    = $this->movilesMemoria($jsonReq["cadena"]);
        //$rta      = $movil->equipo_id;
        $mensaje  = $this->obtenerComandoPendiente($movil->equipo_id); 
        if($mensaje){
          if($mensaje->comando!='' && !is_null($mensaje->comando)){
            Log::info("mensajecomando:::".$mensaje->comando);
            $comando="AT".$mensaje->comando.'='.$mensaje->auxiliar."?\r\n";
          }else{
            $comando  = "AT".$this->decodificarComando($mensaje,$movil)."?\r\n";
          }
        }else{
          $comando  ="ok\r\n"; 
        }
        Log::info("comandeando:".$comando);
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
  public function compruebaMovilMC($imei,$shmid){
    //Movil Binary Search 
    MemVar::initIdentifier($shmid);
    $memoMoviles    = MemVar::GetValue();
    $memoMoviles    = json_decode($memoMoviles);
    $encontrado     = app()->Puerto::binarySearch($memoMoviles, 0, count($memoMoviles) - 1, $imei);
    return $encontrado;
    
  } 
  protected function obtenerMoviles() {
      Log::error("Buscando moviles en code.siacseguridad.com");
      // Create a client with a base URI
      $client = new Client(['base_uri' => 'http://code.siacseguridad.com:8080/api/']);
      // Send a request to https://foo.com/api/test
      $response = $client->request('GET', 'equipos/1');

      return $response;
  }
  public function movilesMemoria($imei){
    Log::error(print_r($imei, true));
    $requestApi   = '0';
    $mcRta        = '0';
    $mcRta2       = '0';
    $movil        = false;
    $shmid        = MemVar::OpenToRead('moviles.dat');
    if($shmid!='0'){
        Log::info("Verificando validez IMEI ".$imei);
        $mcRta        = $this->compruebaMovilMC($imei,$shmid);
      if($mcRta==false){
        Log::info("El IMEI ".$imei." no está en la memoria");
        $requestApi= '1';
      }else{
        $mcRta2    = '1';
        $movil     = $mcRta;
        Log::info("::::::::Procesando:".$imei."- Movil_id:".$movil->movil_id."-MovilOld_id:".$movil->movilOldId."::::::::");
      }
    }else{
        $requestApi   = '1';
        Log::info("No existe el shmid->voy a crear nuevo segmento");
    }
    if($requestApi   == '1'){
        $apiRta   = $this->obtenerMoviles();
        if($apiRta->getStatusCode()=="200" && $apiRta->getReasonPhrase()=="OK"){
          $length   = strlen($apiRta->getBody());
          $largo    = (int)$length;
          //Log::error("Content-Length:::".strlen($apiRta->getBody()));
          MemVar::VaciaMemoria();
          $memvar = MemVar::Instance('moviles.dat');
          $memvar->init('moviles.dat',$largo);
          $memvar->setValue( $apiRta->getBody() );
          $shmid  = MemVar::OpenToRead('moviles.dat');
          $movil  = $this->compruebaMovilMC($imei,$shmid);
        }else{
          Log::error("Bad Response :: code:".$code." reason::".$reason);
        }
    }
  return $movil;
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
        $cadenaComando  = "+GETGP";
        break;
      case 20://frecuencias y velocidades
      Log::info("entra por 20");
        switch ($auxParams[1]) {
          case '6':
            $cadenaComando = "FR=0,".self::EN_MOVIMIENTO.",".$auxParams[2];
            break;
          case '7':
            $cadenaComando = "FR=0,".self::EN_DETENIDO.",".$auxParams[2];
            break;
            case '20':
            //seteo tiempo de reporte en veloc max ALV=t,v
            $cadenaComando = "ALV=".$movil->velocidad_max.",".$auxParams[2];
            break;
            case '23':
            //seteo velocidad max max ALV=t,v
            $cadenaComando = "ALV=".$auxParams[2].",".intval($movil->frec_rep_exceso_vel*60);
            break;
          default:
            # code...
            break;
        }
        break;
      case 22://modo corte(aux=1,2->0,1) y modo normal(aux=1,1->0,0)
        Log::info("entra por 22 y auxParams:".$auxParams[1]);
        $valor  = ($auxParams[1]=="2")?"1":"0";
        $cadenaComando = "OUTS=0,".$valor;
        break;
      case 100://reset
      Log::info("entra por 100");
        $cadenaComando = "RES=".$auxParams[0];
        break;
      default:
      Log::info("entra por default");
        $cadenaComando  = "+GETGP";
        break;
    }
    Log::info("cadenaComando:".$cadenaComando);
    return $cadenaComando;
  }
}
