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
	    $rta="";
      $jsonReq = $request->json()->all();
      Log::info("mira lo que llega a normal controller en DAPA:");
      Log::error(print_r($jsonReq, true));
      if(isset($jsonReq["cadena"])){
        $rta  = $this->tratarReporte($jsonReq['cadena']);
      }elseif($jsonReq["KEY"]=="NR"){
        foreach($jsonReq["PA"] as $posicion){
          Log::info($posicion["PS"]);
          $rta  = $this->tratarReporte($posicion["PS"]);
          Log::info("va a devolver esto:".$rta);
        }
      }else{
        $rta= "ERROR:Json mal formado!";
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
   public static function dameMoviles(){
        $movileros  = array( array('IMEI' =>'863835020075979' ,'alias'=>'sba000','cmd'=>'AT+GETGP?\r\n' ), 
                            array('IMEI' =>'863835020075978' ,'alias'=>'sba001','cmd'=>'AT+GETGP?\r\n' ) );
        Log::error("pidiendo moviles");
        return json_encode($movileros);
    }
  public function tratarReporte($cadena){
    $rta  = "";
    try{
      $rta  = app()->Puerto->analizeReport($cadena) ;
      Log::error("cadena entrante en NormalReportController ::".$cadena);
    }catch(Exception $e){
      $rta  = "error";
      Log::error($e);
    }
    Log::info("rta en tratar reporte de NRController:".$rta);
    return $rta;
  } 
}
