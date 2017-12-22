<?php
namespace App\Http\Controllers;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
Use Log;
use stdClass;
use Storage;
use DB;
//use App\Http\Controllers\PuertoController;
//Use Puerto;
class KeepAliveController extends BaseController
{
  public function index(Request $request) {
    return "ok";
  }      //
  public function create(Request $request){
    $method = $request->method();
    if ($request->isMethod('post')) {
	  $jsonReq = $request->json()->all();
      $rta="";
      $jsonReq = $request->json()->all();
      if(isset($jsonReq["cadena"])){
        $rta  = $this->tratarReporte($jsonReq['cadena']);
        return $rta;
       }elseif($jsonReq["KEY"]=="KA"){
          //por ahora devuelvo este de ejemplo
          $rta  ="AT+GETGP?\r\n";
        
        return $rta;
      }else{
        return "ERROR:Json mal formado!";
        Log::error("Error:json mal formado, ver palabra clave");
      }
      /*if(isset($jsonReq["cadena"])){
        try{
          $imei = app()->Puerto->getImei($jsonReq['cadena']) ;
          Log::error("cadena entrante: ::".$jsonReq['cadena']);
          Log::info("el imei obtenido es:".$imei);
        }catch(Exception $e){
          Log::error($e);
        }
        return "ok";
      }else{
        return "ERROR:Json mal formado!";
        Log::error("Error:json mal formado, ver palabra clave");
      }*/
    }else{
      return "ERROR:Metodo no permitido";
      Log::error("Error:metodo no permitido,utilizar POST");
    }
  }
  public function tratarReporte($cadena){
    $imei = "0000";
    try{
      $imei = app()->Puerto->getImei($cadena) ;
      Log::error("cadena entrante en KeepAliveController ::".$cadena);
    }catch(Exception $e){
      Log::error($e);
    }
    return $imei;
  } 
   
}
