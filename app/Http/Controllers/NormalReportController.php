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
        $requestApi   = '0';
        $mcRta        = '0';
        $movil        = false;
        if(isset($arrCadena['IMEI'])){
            Log::info("Verificando validez IMEI ".$arrCadena['IMEI']);
            $movil      = HelpMen::compruebaMovilRedis($arrCadena['IMEI']);
            Log::info(print_r($movil,true));
            if($movil==false){//no fue encontrado en redis
              Log::info("El IMEI ".$arrCadena['IMEI']." no está en Redis");
              HelpMen::solicitarMoviles();
              $movil      = HelpMen::compruebaMovilRedis($arrCadena['IMEI']);
              if($movil==false){
                $movilFicticio  = new stdClass();
                $movilFicticio->equipo_id = "-666";$movilFicticio->imei = $arrCadena['IMEI']; $movilFicticio->movil_id = "";
                $movilFicticio->frec_rep_detenido = "";$movilFicticio->frec_rep_velocidad = "";$movilFicticio->frec_rep_exceso_vel = "";
                $movilFicticio->velocidad_max = "";$movilFicticio->movilOldId = "";$movilFicticio->estado_u = "";
                $movilFicticio->estado_v = "";$movilFicticio->perif_io_id="";
                RedisHelp::setMovil($movilFicticio);
                $movil=$movilFicticio;
              }
            }elseif ($movil['equipo_id']=="-666") {//en MC pero sin instalacion
              $movil     = json_encode($movil);
              Log::info("El IMEI ".$arrCadena['IMEI']." se encuentra sin INSTALACION");
            }else{ 
              $movil     = json_encode($movil);
              $logcadena ="::::::::Procesando:".$arrCadena['IMEI']."- Movil_id:".$movil->movil_id."-MovilOld_id:".$movil->movilOldId.":::::::: \r\n";
              HelpMen::report($movil->equipo_id,$logcadena);
            }
        }else{
            Log::error("CADENA MAL FORMADA SIN IMEI");
        }
        /*Fin nuevaMC*/
        if($movil!=false && $movil->equipo_id!='-666'){
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
    return response()->json(['controlador' => 'NR','estado' => $rta]);
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
