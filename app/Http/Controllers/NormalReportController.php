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
class NormalReportController extends BaseController
{
  public function index(Request $request) {
    return "ok";
  }      //
  public function create(Request $request){
    $method = $request->method();
    if ($request->isMethod('post')) {
	  $jsonReq = $request->json()->all();
      if(isset($jsonReq["cadena"])){
        try{
          app()->Puerto->analizeReport($jsonReq['cadena']) ;
          Log::error("cadena entrante en NormalReportController: ::".$jsonReq['cadena']);
        }catch(Exception $e){
          Log::error($e);
        }
        return "ok";
      }else{
        return "ERROR:Json mal formado!";
        Log::error("Error:json mal formado, ver palabra clave");
      }
    }else{
      return "ERROR:Metodo no permitido";
      Log::error("Error:metodo no permitido,utilizar POST");
    }
  }
   public static function dameMoviles(){
        $movileros  = array("KEY"=>"CM","DT"=>array("IMEI"=>"863835020075979","RT"=>"zaraza"));
        Log::error("pidiendo moviles");
        return json_encode($movileros);
    }
   
}
