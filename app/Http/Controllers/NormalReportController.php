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
  */  
  public function create(Request $request){
    
    $method = $request->method();
    if ($request->isMethod('post')) {
	    $rta="";
      $jsonReq = $request->json()->all();
      //Log::error(print_r($jsonReq, true));
      if(isset($jsonReq["cadena"])){
        //pruebas en obtencion de imei del json
        $arrCadena = app()->Puerto::changeString2array($jsonReq["cadena"]);
        Log::error(print_r($jsonReq["cadena"], true));
        Log::info("se obtuvo este IMEI::".print_r($arrCadena, true));
        $apiRta   = $this->obtenerMoviles();
        $code     = $apiRta->getStatusCode(); 
        $reason   = $apiRta->getReasonPhrase();
        if($code=="200" && $reason=="OK"){
          $body     = $apiRta->getBody();
          $length   = strlen($apiRta->getBody());
          Log::error("Content-Length:::".strlen($apiRta->getBody()));
          $largo  = (int)$length;
          $shmid  = MemVar::OpenToRead();
          if($shmid!='0'){
            Log::info("Existe el shmid->Verifico si el IMEI 352024025265533está dentro");
            MemVar::initIdentifier($shmid);
            $memoMoviles  = MemVar::GetValue();
            $mcRta        = $this->compruebaMovilMC('352024025265533',json_decode($memoMoviles));
            if($mcRta=='0'){
              Log::info("El IMEI 352024025265533 no está en la memoria");
              //$mcRta        = $this->compruebaMovilMC('352024025265533',json_decode($apiRta->getBody()));
              MemVar::VaciaMemoria();
              $memvar = MemVar::Instance();
              $memvar->init(0,$largo);
              $memvar->setValue( $apiRta->getBody() );
            }else{
              Log::info("El IMEI 352024025265533 ESTA en la memoria");
            }
            
          }else{
            Log::info("No existe el shmid->voy a crear nuevo segmento");
            /*MemVar::VaciaMemoria();
            $memvar = MemVar::Instance();
            $memvar->init(0,$largo);
            $memvar->setValue( $body );
            */
          }
          
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
      $response = $client->request('GET', 'equipos/1');

      return $response;
  }
  public function compruebaMovilMC($imei,$arrMovMc){
    $rta  = '0';
    //Log::error(print_r($arrMovMc, true));
    if($arrMovMc !="" && count($arrMovMc)>0){
      foreach ($arrMovMc as $movil) {
        if($movil->imei==$imei){
          $rta  = '1';
          Log::info( "valor ENCONTRADO compruebaMovilMC = ".$movil->imei."==".$imei);
        }        
      }
    }else{
      $rta  = '0';
    }
    return $rta;
    
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
