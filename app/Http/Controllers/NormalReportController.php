<?php
namespace App\Http\Controllers;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
Use Log;
use stdClass;
use Storage;
use DB;

/*Helpers*/
use App\Helpers\MemVar;
use GuzzleHttp\Client;
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
  */   //
  public function create(Request $request){
    
    $method = $request->method();
    if ($request->isMethod('post')) {
	    $rta="";
      $jsonReq = $request->json()->all();
      //Log::error(print_r($jsonReq, true));
      if(isset($jsonReq["cadena"])){
        Log::info("Ingresando por MONA");
        $memvar = new MemVar( 100,420, 100 );
        $memvar->setValue( "valor de la variable en memoria compartida" );
        Log::info("puesto valor");
        $memvar->close();
        //Note: the 3rd and 4th should be entered as 0 if you are opening an existing memory segment. 
        $memvar = new MemVar( 100,0,0 );
        Log::info( "valor = ".$memvar->getValue());
        $memvar->close();
        
        $apiRta  = $this->obtenerMoviles();
        $code     = $apiRta->getStatusCode(); // 200
        $reason   = $apiRta->getReasonPhrase(); // OK
        if($code==200 && $reason=="ok"){
          Log::error("la respuesta:::".(string)$apiRta->getBody());
        }else{
          Log::error("Bad Response chavon:: code:".$code." reason::".$reason);
        }
        

       /* $memvar = new MemVar("863835020075979" );
        $memvar->setValue( 1 , "valor de la variable en memoria compartida" );
        Log::info("puesto valor");
        $memvar->close();
        
        $memvar = new MemVar( 100 );
        Log::info( "valor = ".$memvar->getValue( 1 ));
        $memvar->close();*/
        
        //if(!count(app()->moviles)>0){
          //app()->Puerto->moviles_activos;
          //Log::info(print_r(app()->moviles,true));
        //}
        
        $rta  = $this->tratarReporte($jsonReq['cadena']);
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
  protected function obtenerMoviles() {
      //Se arrancan pruebas con guzzle
      Log::error("Buscando moviles en code.siacseguridad.com");
      // Create a client with a base URI
      $client = new Client(['base_uri' => 'http://code.siacseguridad.com:8080/api/']);
      // Send a request to https://foo.com/api/test
      $response = $client->request('GET', 'moviles/cliente/1');

      return $response;
  }
  public static function dameMoviles(){
    Log::error("pidiendo moviles dameMoviles");
        $movileros  = array( array('IMEI' =>'863835020075979' ,'alias'=>'sba000','cmd'=>'AT+GETGP?\r\n' ), 
                            array('IMEI' =>'863835020075978' ,'alias'=>'sba001','cmd'=>'AT+GETGP?\r\n' ) );
        
        return json_encode($movileros);
    }
  public function tratarReporte($cadena){
    $rta  = "";
    try{
      Log::error("cadena entrante en NormalReportController ::".$cadena);
      $rta  = app()->Puerto->analizeReport($cadena) ;
    }catch(Exception $e){
      $rta  = "error";
      Log::error($e);
    }
    Log::info("rta en tratar reporte de NRController:".$rta);
    return $rta;
  } 
}
