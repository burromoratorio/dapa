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
    const OFFSET_LATITUD= 2;
    const OFFSET_NS     = 3;
    const OFFSET_LONGITUD= 4;
    const OFFSET_EW     = 5;
    const OFFSET_VELOCIDAD= 6;
    const OFFSET_RUMBO  = 7;
    public static function compruebaMovilMC($imei,$shmid){
        //Movil Binary Search 
        $encontrado=false;
        MemVar::initIdentifier($shmid);
        $memoMoviles    = MemVar::GetValue();
        $memoMoviles    = json_decode($memoMoviles);
        if($memoMoviles!=''){
            $encontrado     = self::binarySearch($memoMoviles, 0, count($memoMoviles) - 1, $imei);
        }
       
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
    public static function binarySearch(Array $arr, $start, $end, $x){
        if ($end < $start)
            return false;
        $mid = floor(($end + $start)/2);
        if ($arr[$mid]->imei == $x) 
            return $arr[$mid];
        elseif ($arr[$mid]->imei > $x) {
            // call binarySearch on [start, mid - 1]
            return self::binarySearch($arr, $start, $mid - 1, $x);
        }else {
            // call binarySearch on [mid + 1, end]
            return self::binarySearch($arr, $mid + 1, $end, $x);
        }
    }
    public static function CargarMemoria($archivo,$dataArray){
        $memvar     = MemVar::Instance($archivo);
        $enstring   = json_encode($dataArray);
        $largo      = (int)strlen($enstring);
        $memvar->init($archivo,$largo);
        $memvar->setValue( $enstring );
    }
    public static function Gprmc2Data( $arrCadena ){
        //latitud
        $latitud    = self::ConvertirCoordenada( $arrCadena[self::OFFSET_LATITUD], $arrCadena[self::OFFSET_NS] );
        //lingitud
        $longitud   = self::ConvertirCoordenada( $arrCadena[self::OFFSET_LONGITUD], $arrCadena[self::OFFSET_EW] );
        $velocidad  = $arrCadena[self::OFFSET_VELOCIDAD];
        $rumbo      = $arrCadena[self::OFFSET_RUMBO];
        $velocidad  = ($velocidad!='')?((int)($velocidad*1.852)):0;
        return array(
            'latitud'   => $latitud,
            'longitud'  => $longitud,
            'velocidad' => $velocidad,
            'rumbo'     => self::Rumbo2String($rumbo)
        );
    }
    public static function ConvertirCoordenada( $coord, $hemisphere ) {
        if ($hemisphere == "N" || $hemisphere == "E") // North - East => Positivo
        {
            $signo = 1;
        }else{
            $signo = -1;
        }
        $coord /= 100.0; // Quedan los grados como enteros
        $grados = ((int)($coord)); // Resguarda los grados
        $coord -= $grados; // Le quita los grados
        $coord *= 100.0; // Lo lleva al formato inicial sin los grados
        $coord /= 60; // Lo lleva a decimales de grado
        $coord += $grados; // Le agrega los grados
        $coord *= $signo; // Le pone el signo segun norte o sur
        
        return $coord;
    }
    public static function Rumbo2String( $rumbo ){
        $arrRumbo = array(1 =>'Norte',2=>'Noroeste',3=>'Oeste',4=>'Suroeste',5=>'Sur',6=>'Sureste',7=>'Este',8=>'Noreste');
        if (($rumbo > 337.5 && $rumbo <= 22.5) || ($rumbo == 0)){
            $intRumbo = 1;//North
        }else if ($rumbo > 22.5 && $rumbo <= 67.5){
            $intRumbo = 6;//NorthEast
        }else if ($rumbo > 67.5 && $rumbo <= 112.5){
            $intRumbo = 7;//East
        }else if ($rumbo > 112.5 && $rumbo <= 157.5){
            $intRumbo = 6;//SouthEast
        }else if ($rumbo > 157.5 && $rumbo <= 202.5){
            $intRumbo = 5;//South
        }else if ($rumbo > 202.5 && $rumbo <= 247.5){
            $intRumbo = 4;//SouthWest
        }else if ($rumbo > 247.5 && $rumbo <= 292.5){
            $intRumbo = 3;//West
        }else if ($rumbo > 292.5 && $rumbo <= 337.5){
            $intRumbo = 2;//NorthWest
        }else{
            $intRumbo = 1;
        }
        //return $arrRumbo[$intRumbo];
        return $intRumbo;
    }
}
