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
  */   // probar con este imei 863835020075979
  public function create(Request $request){
    
    $method = $request->method();
    if ($request->isMethod('post')) {
	    $rta="";
      $jsonReq = $request->json()->all();
      //Log::error(print_r($jsonReq, true));
      if(isset($jsonReq["cadena"])){
        $apiRta   = $this->obtenerMoviles();
        $code     = $apiRta->getStatusCode(); 
        $reason   = $apiRta->getReasonPhrase();
        if($code=="200" && $reason=="OK"){
          //Log::error("Moviles en api:::".(string)$apiRta->getBody());
          //cantidad de octetos en la rta, es decir rta*8=xbits==es decir son los bytes
          //$apiRta->getHeader('Content-Length');
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
              /*MemVar::VaciaMemoria();
              $memvar = MemVar::Instance();
              $memvar->init(0,$largo);
              $memvar->setValue( $memoMoviles );*/
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
          //MemVar::VaciaMemoria();
          //MemVar::GetValue();
          //MemVar::Eliminar();
          /*$memvar = MemVar::Instance();
          if( $memvar->init(0,$largo) ){
            MemVar::OpenToRead();
            $memvar->setValue( (string)$apiRta->getBody() );
            MemVar::GetValue();
            //MemVar::Eliminar();
          }else{

          }*/
          // new MemVar( 0,420,$largo  );
          //$memvar->setValue( (string)$apiRta->getBody() );
          Log::info("puesto valor");
          //$memvar->close();
          //Note: the 3rd and 4th should be entered as 0 if you are opening an existing memory segment. 
          //$memvar = new MemVar( 100,0,0 );
          //Log::info( "valor = ".$memvar->getValue());
          //$memvar->close();
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
    Log::error(print_r($arrMovMc, true));
    if($arrMovMc !="" && count($arrMovMc)>0){
      foreach ($arrMovMc as $movil) {
        if($movil->imei==$imei){
          $rta  = '1';
          Log::info( "valor ENCONTRADO compruebaMovilMC = ".$movil->imei."==".$imei);
        }else{
          $rta  = '0';
          Log::info( "NO ES = ".$movil->imei);
        }
        
      }
    }else{
      $rta  = '0';
    }
    //863835020075979
    //Note: the 3rd and 4th should be entered as 0 if you are opening an existing memory segment. 
    //$memvar       = new MemVar( 100,0,0 );
    //$jsonMoviles  = json_decode($memvar->getValue());
    //var_dump($jsonMoviles);
    /*foreach ($jsonMoviles as $movil) {
      Log::info( "valor compruebaMovilMC = ".print_r($movil, true));
    }*/
    
    //$memvar->close();
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
