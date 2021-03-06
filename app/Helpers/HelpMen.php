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
    
    public static function obtenerMoviles() {
        Log::error("Buscando moviles en code.siacseguridad.com");
        // Create a client with a base URI
        $urlP       = env('CODE_URL');
        $client = new Client(['base_uri' => $urlP]);
        $response = $client->request('GET', 'equipos/1');
        return $response;
    }
    
    public static function report($archivo,$logdata) {
            $logstring	= "[".date('Y-m-d H:i:s')."]".$logdata;
        file_put_contents(storage_path('logs/equipo_'.$archivo.'_'.date('Y-m-d').'.log'), (string) $logstring, FILE_APPEND);
        return;
    }
    public static function posteaPosicion($recurso,$api,$json){		
            $urlP       = ($api=="molinos")?env('MOLINOS_URL').$recurso:env('API_URL').$recurso;
            $client 	= new Client( [ 'headers' => [ 'Content-Type' => 'application/json' ] ] );
            $response 	= $client->post($urlP,['body' => json_encode( $json )] );
            $statuscode = $response->getStatusCode();

            if (200 === $statuscode || 201 === $statuscode) {
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
     /*
     * 
     * Nuevo entorno con redis
     *
     *  */
    public static function compruebaMovilRedis($imei){
        Log::info("ENTRANDO A COMPROBAR REDIS");
        return RedisHelp::lookForMovil($imei);
    }
    public static function solicitarMoviles(){
        $apiRta   = self::obtenerMoviles();
        if($apiRta->getStatusCode()=="200" && $apiRta->getReasonPhrase()=="OK"){
            $moviles   = json_decode($apiRta->getBody()->getContents());
            self::CargarRedis($moviles);
        }
    }
    public static function CargarRedis($dataArray){
        RedisHelp::storeMoviles($dataArray);
    }
    /*fin redis*/
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
    /*@param fecha: yyyy-mm-dd HH:mm:ss-> 2020-02-08 17:57:05 
     */
    public static function fechaHistorica($fecha){
        $fechaVieja     = 0;
        $fechaRepo      = strtotime ( $fecha ) ; 
        $hoy            = date('Y-m-j H:i:s'); 
        $newDateCompa   = strtotime ( '-30 minute' , strtotime ($hoy) ) ; 
        if($fechaRepo<=$newDateCompa){
            $fechaVieja     = 1;
        }
        return $fechaVieja;
    }
}
