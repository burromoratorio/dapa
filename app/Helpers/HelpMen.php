<?php
namespace App\Helpers;
use Illuminate\Http\Request;
Use Log;
use stdClass;
use Storage;
use DB;
/*DDBB Principal*/
use App\ColaMensajes;
/*Helpers*/
use App\Helpers\MemVar;
use GuzzleHttp\Client;
/************ class HelpMen **********************/

/**
 * helper functions class
 *
 */

class HelpMen
{
	public static function compruebaMovilMC($imei,$shmid){
	    //Movil Binary Search 
	    MemVar::initIdentifier($shmid);
	    $memoMoviles    = MemVar::GetValue();
	    $memoMoviles    = json_decode($memoMoviles);
	    $encontrado     = app()->Puerto::binarySearch($memoMoviles, 0, count($memoMoviles) - 1, $imei);
	    return $encontrado;
    } 
  	public static function obtenerMoviles() {
      Log::error("Buscando moviles en code.siacseguridad.com");
      // Create a client with a base URI
      $urlP       = env('CODE_URL');
      $client = new Client(['base_uri' => $urlP]);
      $response = $client->request('GET', 'equipos/1');
      return $response;
  	}
  	public static function movilesMemoria($imei){
	    $requestApi   = '0';
	    $mcRta        = '0';
	    $mcRta2       = '0';
	    $movil        = false;
	    $shmid        = MemVar::OpenToRead('moviles.dat');
	    if($shmid!='0'){
	        Log::info("Verificando validez IMEI ".$imei);
	        $mcRta        = self::compruebaMovilMC($imei,$shmid);
	      if($mcRta==false){
	        Log::info("El IMEI ".$imei." no está en la memoria");
	        $requestApi= '1';
	      }else{
	        $mcRta2    = '1';
	        $movil     = $mcRta;
	        Log::info("::::::::Procesando:".$imei."- Movil_id:".$movil->movil_id."-MovilOld_id:".$movil->movilOldId."::::::::");
	      }
	    }else{
	        $requestApi   = '1';
	        Log::info("No existe el shmid->voy a crear nuevo segmento");
	    }
	    if($requestApi   == '1'){
	        $apiRta   = self::obtenerMoviles();
	        if($apiRta->getStatusCode()=="200" && $apiRta->getReasonPhrase()=="OK"){
	          $length   = strlen($apiRta->getBody());
	          $largo    = (int)$length;
	          //Log::error("Content-Length:::".strlen($apiRta->getBody()));
	          MemVar::VaciaMemoria();
	          $memvar = MemVar::Instance('moviles.dat');
	          $memvar->init('moviles.dat',$largo);
	          $memvar->setValue( $apiRta->getBody() );
	          $shmid  = MemVar::OpenToRead('moviles.dat');
	          $movil  = self::compruebaMovilMC($imei,$shmid);
	        }else{
	          Log::error("Bad Response :: code:".$code." reason::".$reason);
	        }
	    }
	  return $movil;
  }
  	public static function report($archivo,$logdata) {
  		$logstring	= "[".date('Y-m-d H:i:s')."]".$logdata;
	    file_put_contents(storage_path('logs/equipo_'.$archivo.'_'.date('Y-m-d').'.log'), (string) $logstring, FILE_APPEND);
	    return;
	}
	public static function posteaPosicion($recurso,$json){		
            $urlP       = env('API_URL').$recurso;
            $client 	= new Client( [ 'headers' => [ 'Content-Type' => 'application/json' ] ] );
            $response 	= $client->post($urlP,['body' => json_encode( $json )] );
            $statuscode = $response->getStatusCode();

            if (200 === $statuscode) {
              // Do something
            }
            elseif (304 === $statuscode) {
              // Nothing to do
            }
            elseif (404 === $statuscode) {
              // Clean up DB or something like this
            }
            else {
                    Log::error("Bad :: code:".$statuscode." reason::".$response->getBody());
            }
           return $response->getBody();
    }
}
