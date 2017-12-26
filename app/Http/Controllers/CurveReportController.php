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
class CurveReportController extends BaseController
{
  public function index(Request $request) {
    return "ok";
  }      //
  public function create(Request $request){
    $method = $request->method();
    if ($request->isMethod('post')) {
      /**/
      $rta="";
      $jsonReq = $request->json()->all();
      if(isset($jsonReq["cadena"])){
        $rta  = $this->tratarReporte($jsonReq['cadena']);
        return $rta;
       }elseif($jsonReq["KEY"]=="CR"){
        foreach($jsonReq["PA"] as $posicion){
          Log::info($posicion["PS"]);
          $rta  = $this->tratarReporte($posicion["PS"]);
        }
        return $rta;
      }else{
        Log::error("Error:json mal formado, ver palabra clave");
        return "ERROR:Json mal formado!";
      }
      /**/
	  }else{
      return "ERROR:Metodo no permitido";
      Log::error("Error:metodo no permitido,utilizar POST");
    }
  }
  public function tratarReporte($cadena){
    try{
      app()->Puerto->analizeReport($cadena) ;
      Log::error("cadena entrante en CurveReportController ::".$cadena);
    }catch(Exception $e){
      Log::error($e);
    }
    return "ok";
  } 
   
}
