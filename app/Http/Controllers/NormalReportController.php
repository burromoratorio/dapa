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
    //Log::useDailyFiles(storage_path().'/logs/gprmc.log');
    //Log::info(['Request'=>$request]);

    $method = $request->method();
    if ($request->isMethod('post')) {
	    $rta="";
      $jsonReq = $request->json()->all();
      //Log::error(print_r($jsonReq, true));
      if(isset($jsonReq["cadena"])){
        //pruebas en obtencion de imei del json -->el imei de sebas 861075026533174
        $arrCadena = app()->Puerto::changeString2array($jsonReq["cadena"]);
        $arrCadena['IMEI']  = '861075026533174';
        Log::info("se obtuvo este IMEI::".$arrCadena['IMEI']);
        /*primero validaciones en MC*/
        $shmid        = MemVar::OpenToRead();
        $requestApi   = '0';
        $mcRta        = '0';
        $mcRta2       = '0';
        if($shmid!='0'){
          Log::info("Existe el shmid->Verifico si el IMEI ".$arrCadena['IMEI']." está dentro");
          MemVar::initIdentifier($shmid);
          $memoMoviles  = MemVar::GetValue();
          $mcRta        = $this->compruebaMovilMC($arrCadena['IMEI'],json_decode($memoMoviles));
          if($mcRta=='0'){
            Log::info("El IMEI ".$arrCadena['IMEI']." no está en la memoria");
            $requestApi   = '1';
          }else{
            $mcRta2        = '1';
            Log::info("El IMEI ".$arrCadena['IMEI']." ESTA en la memoria");
          }
        }else{
          $requestApi   = '1';
          Log::info("No existe el shmid->voy a crear nuevo segmento");
        }
        /*fin validaciones MC*/
        /*cargo nuevos datos en MC API REQUEST y vuelvo a comprobar
          si no está en la DDBB-->no sigo la ejecucion de esa cadena
        */
        if($requestApi   == '1'){
          $apiRta   = $this->obtenerMoviles();
          if($apiRta->getStatusCode()=="200" && $apiRta->getReasonPhrase()=="OK"){
            $length   = strlen($apiRta->getBody());
            $largo    = (int)$length;
            Log::error("Content-Length:::".strlen($apiRta->getBody()));
            MemVar::VaciaMemoria();
            $memvar = MemVar::Instance();
            $memvar->init(0,$largo);
            $memvar->setValue( $apiRta->getBody() );
            $memoMoviles  = MemVar::GetValue();
            $movil_id        = $this->compruebaMovilMC($arrCadena['IMEI'],json_decode($memoMoviles));
          }else{
            Log::error("Bad Response :: code:".$code." reason::".$reason);
          }
        }
        /*Fin nuevaMC*/
        if($movil_id>0){
          $rta  = $this->tratarReporte($jsonReq['cadena'],$movil_id);
        }else{
         Log::error("El IMEI:".$arrCadena['IMEI']." No esta en la DDBB-->desecho reporte");
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
    //uso el imei de seba 861075026533174
    //Log::error(print_r($arrMovMc, true));
    if($arrMovMc !="" && count($arrMovMc)>0){
      foreach ($arrMovMc as $movil) {
        //if($movil->imei==$imei){
        if('8610750265331745'==$imei){
          $rta  = $movil->movilOldId;
          Log::info( "valor ENCONTRADO compruebaMovilMC = ".$movil->imei."==".$imei);
          break;
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
  public function tratarReporte($cadena,$movil_id){
    $rta  = "";
    try{
      Log::error("cadena entrante en NormalReportController ::".$cadena);
      $rta  = app()->Puerto->analizeReport($cadena,$movil_id) ;
    }catch(Exception $e){
      $rta  = "error";
      Log::error($e);
    }
    Log::info("rta en tratar reporte de NRController:".$rta);
    return $rta;
  } 
}
