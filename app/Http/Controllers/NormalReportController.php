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
  private static $memvar=null;
  public function index(Request $request) {
    return "ok";
  }   
  /*
    desde MONA ingresa con $jsonReq["cadena"]
    desde Puerto_lite con ($jsonReq["KEY"]
  */   // probar con este imei 863835020075979
  public function create(Request $request){
    
    $method = $request->method();
    if ($request->isMethod('post')) {
	    $rta="";
      $jsonReq = $request->json()->all();
      //Log::error(print_r($jsonReq, true));
      if(isset($jsonReq["cadena"])){
       // $mcRta    = $this->compruebaMovilMC('863835020075979');
        $apiRta   = $this->obtenerMoviles();
        $code     = $apiRta->getStatusCode(); 
        $reason   = $apiRta->getReasonPhrase();
        if($code=="200" && $reason=="OK"){
          Log::error("Moviles en api:::".(string)$apiRta->getBody());
          //cantidad de octetos en la rta, es decir rta*8=xbits==es decir son los bytes
          $length   = $apiRta->getHeader('Content-Length');
          Log::info("length::".$length[0]);
          $largo  = (int)$length[0];
          //self::$memvar->eliminar();
          self::$memvar = MemVar::inicializar();//new MemVar( 0,420,$largo  );
          //self::$memvar->setValue( (string)$apiRta->getBody() );
          Log::info("puesto valor");
          //self::$memvar->close();
          //Note: the 3rd and 4th should be entered as 0 if you are opening an existing memory segment. 
          //self::$memvar = new MemVar( 100,0,0 );
          //Log::info( "valor = ".$memvar->getValue());
          //self::$memvar->close();
        }else{
          Log::error("Bad Response :: code:".$code." reason::".$reason);
        }
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
      Log::error("Buscando moviles en code.siacseguridad.com");
      // Create a client with a base URI
      $client = new Client(['base_uri' => 'http://code.siacseguridad.com:8080/api/']);
      // Send a request to https://foo.com/api/test
      $response = $client->request('GET', 'moviles/cliente/1');

      return $response;
  }
  public function compruebaMovilMC($imei){
    //863835020075979
    //Note: the 3rd and 4th should be entered as 0 if you are opening an existing memory segment. 
    self::$memvar       = new MemVar( 100,0,0 );
    $jsonMoviles  = json_decode(self::$memvar->getValue());
    var_dump($jsonMoviles);
    /*foreach ($jsonMoviles as $movil) {
      Log::info( "valor compruebaMovilMC = ".print_r($movil, true));
    }*/
    
    $memvar->close();
    return '1';
  }
  /*public static function dameMoviles(){
    Log::error("pidiendo moviles dameMoviles");
        $movileros  = array( array('IMEI' =>'863835020075979' ,'alias'=>'sba000','cmd'=>'AT+GETGP?\r\n' ), 
                            array('IMEI' =>'863835020075978' ,'alias'=>'sba001','cmd'=>'AT+GETGP?\r\n' ) );
        
        return json_encode($movileros);
    }*/
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
