<?php
namespace App\Http\Controllers;
use App\Helpers\HelpMen;
use App\Helpers\MemVar;
use \App\Helpers\RedisHelp;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Exception;
use stdClass;
//use App\Http\Controllers\PuertoController;
//Use Puerto;
class NormalReportController extends BaseController
{
  public function index(Request $request) {
    return "ok";
  }   
  /*
    desde MONA ingresa con $jsonReq["cadena"]
    desde Puerto_lite con ($jsonReq["KEY"]
  */  
  public function create(Request $request){
    $method = $request->method();
    if ($request->isMethod('post')) {
	    $rta="";
      $jsonReq = $request->json()->all();
      if(isset($jsonReq["cadena"])){
        //Log::info("en normal antes que nada la cadenaaaaaaa::::".$jsonReq["cadena"]);  
        $arrCadena = app()->Puerto::changeString2array($jsonReq["cadena"]);
        /*primero validaciones en MC*/
        $shmid        = MemVar::OpenToRead('moviles.dat');
        $requestApi   = '0';
        $mcRta        = '0';
        $movil        = false;
        if(isset($arrCadena['IMEI'])){
            Log::info("Verificando validez IMEI ".$arrCadena['IMEI']);
            $movil      = HelpMen::compruebaMovilRedis($arrCadena['IMEI']);
            Log::info(print_r($movil,true));
            if($movil==false){//no fue encontrado en MC
              Log::info("El IMEI ".$arrCadena['IMEI']." no está en la memoria");
              $requestApi = '1';
            }elseif ($movil['equipo_id']=="-666") {//en MC pero sin instalacion
              Log::info("El IMEI ".$arrCadena['IMEI']." se encuentra sin INSTALACION");
              $requestApi = '2';
            }else{ 
              $movil     = json_encode($movil);
              $logcadena ="::::::::Procesando:".$arrCadena['IMEI']."- Movil_id:".$movil->movil_id."-MovilOld_id:".$movil->movilOldId.":::::::: \r\n";
              HelpMen::report($movil->equipo_id,$logcadena);
            }
        }else{
            Log::error("CADENA MAL FORMADA SIN IMEI");
        }
        
        if($requestApi   == '1'){
          $movilOmoviles  = $this->fijateQueOnda($arrCadena['IMEI']); //devuelve un obj movil, o un json con todos los moviles de ddbb
            if(isset($movilOmoviles->imei)){ //esta en memo
              $movil = $movilOmoviles;
            }else{//no esta en memo ni en ddbb
              $memoMoviles    = json_decode($movilOmoviles);
              $movilFicticio  = new stdClass();
              $movilFicticio->equipo_id = "-666";$movilFicticio->imei = $arrCadena['IMEI']; $movilFicticio->movil_id = "";
              $movilFicticio->frec_rep_detenido = "";$movilFicticio->frec_rep_velocidad = "";
              $movilFicticio->frec_rep_exceso_vel = "";$movilFicticio->velocidad_max = "";
              $movilFicticio->movilOldId = "";$movilFicticio->estado_u = "";
              array_push($memoMoviles, $movilFicticio);
              $movilesForMemo = json_encode($memoMoviles);
              $length         = strlen($movilesForMemo);
              $largo          = (int)$length;
              //Log::info($movilesForMemo);
              MemVar::VaciaMemoria();
              $memvar = MemVar::Instance('moviles.dat');
              $memvar->init('moviles.dat',$largo);
              $memvar->setValue( $movilesForMemo );
              $shmid  = MemVar::OpenToRead('moviles.dat');
            }
        }
        if($requestApi   == '2'){
          $movilOmoviles= $this->fijateQueOnda($arrCadena['IMEI']);
            if(isset($movilOmoviles->imei)){
              $movil = $movilOmoviles;
            }
        }
        /*Fin nuevaMC*/
        if($movil!=false){
          $rta    = $this->tratarReporte($jsonReq['cadena'],$movil);
        }else{
            if(isset($arrCadena['IMEI'])){
                Log::error("El IMEI:".$arrCadena['IMEI']." No esta en la DDBB-->desecho reporte");
            }else{
                Log::error("CADENA MAL FORMADA SIN IME-->desecho reporte");
            }
        }
      }elseif($jsonReq["KEY"]=="NR"){
        Log::info("Ingresando por Puerto_lite");
        foreach($jsonReq["PA"] as $posicion){
          Log::info($posicion["PS"]);
          $rta  = $this->tratarReporte($posicion["PS"]);
        }
      }else{
        $rta= "ERROR:Json mal formado, ver palabra clave!";
        Log::error("Error:json mal formado, ver palabra clave");
      }
    }else{
      $rta= "ERROR:Metodo no permitido";
      Log::error("Error:metodo no permitido,utilizar POST");
    }
    return response()->json([
            'controlador' => 'NR',
            'estado' => $rta
        ]);
  }
  protected function fijateQueOnda($imei){
    $movil  = false;
    $apiRta   = $this->obtenerMoviles();
    if($apiRta->getStatusCode()=="200" && $apiRta->getReasonPhrase()=="OK"){
      $length       = strlen($apiRta->getBody());
      $largo        = (int)$length;
      $memoMoviles  = json_decode($apiRta->getBody());
      $movilesForMemo=$apiRta->getBody();
      $encontrado   = HelpMen::binarySearch($memoMoviles, 0, count($memoMoviles) - 1, $imei);
      if($encontrado){
        MemVar::VaciaMemoria();
        $memvar = MemVar::Instance('moviles.dat');
        $memvar->init('moviles.dat',$largo+1);
        $memvar->setValue( $movilesForMemo );
        $shmid  = MemVar::OpenToRead('moviles.dat');
        $movil = $encontrado;
      }else{
        $movil = $movilesForMemo;
      }
    }else{
      Log::error("Bad Response :: code:".$code." reason::".$reason);
    }
    return $movil;
  }
  protected function obtenerMoviles() {
      Log::error("Buscando moviles en code.siacseguridad.com");
      $urlP     = env('CODE_URL');
      $client   = new Client(['base_uri' => $urlP]);
      $response = $client->request('GET', 'equipos/1');

      return $response;
  }
    public function compruebaMovilRedis($imei){
        $client = new \Predis\Client();
        $encontrado=RedisHelp::lookForMovil($client, $imei);
        return $encontrado;
    }
  public function compruebaMovilMC($imei,$shmid){
    $encontrado=false;
    MemVar::initIdentifier($shmid);
    $memoMoviles    = MemVar::GetValue();
    $memoMoviles    = json_decode($memoMoviles);
    if($memoMoviles!=''){
        $ultimoIndex    = (count($memoMoviles)-1);
        if($memoMoviles[$ultimoIndex]->imei==$imei && $memoMoviles[$ultimoIndex]->equipo_id=="-666"){//si es el ultimo y no tiene instalacion
          $encontrado   = "-666";
        }else{
          $encontrado   = HelpMen::binarySearch($memoMoviles, 0, count($memoMoviles) - 1, $imei);
        }
    }
    return $encontrado;
    
  }
  public function tratarReporte($cadena,$movil){
    $rta  = "";
    try{
      $logcadena ="Equipo=>".$movil->equipo_id." MOVIL=>".$movil->movilOldId."-Cadena=>".$cadena."\r\n";
      HelpMen::report($movil->equipo_id,$logcadena);
      Log::info($logcadena);
      $rta  = app()->Puerto->analizeReport($cadena,$movil) ;
    }catch(Exception $e){
      $rta  = "error";
      Log::error($e);
    }
    return $rta;
  } 
  public static function VaciaMemoria(){
       MemVar::VaciaMemoria();
       return "ok";
  }
}
