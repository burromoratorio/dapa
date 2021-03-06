<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Log;

use Exception;
use Illuminate\Support\Facades\Redis;

/**
 * Description of RedisController
 *
 * @author siacadmin
 */
class RedisHelp {
    private static $client = null;
    
    public static function setClient(){
        self::$client = new \Predis\Client();
    }
    public static function limpiarBase(){
        Log::error(":::::::::::::::LIMPIANDO BASES DE DATOS REDIS:::::::::::::::\r\n");
        if(!self::$client)self::setClient();
        self::$client->flushdb();
        return "Base de datos Vacia....";
    }
    public function index() {
        try{
            $key = 'linus torvalds';
            $client->hmset($key, [
                'age' => 44,
                'country' => 'finland',
                'occupation' => 'software engineer',
                'reknown' => 'linux kernel',
            ]);
            $data = $client->hgetall('linus torvalds');
            return $data;
        }catch( \Exception $e){
            Log::error($e);
        }
    }
    public static function setMovil($movil){
        if(!self::$client)self::setClient();
        try{
            $key = $movil->imei;
            self::$client->hmset($key,['imei' => $key,
                'equipo_id' => $movil->equipo_id,
                'movil_id' => $movil->movil_id,
                'movilOldId'=>$movil->movilOldId,
                'frec_rep_detenido' => $movil->frec_rep_detenido,
                'frec_rep_velocidad' => $movil->frec_rep_velocidad,
                'frec_rep_exceso_vel'=>$movil->frec_rep_exceso_vel,
                'velocidad_max'=>$movil->velocidad_max,
                'perif_io_id'=>$movil->perif_io_id,
                'estado_u'=>$movil->estado_u,
                'estado_v'=>$movil->estado_v,
                'fecha_posicion'=>'',
                'velocidad'=>'',
                'indice'=>'',
                'io'=>'',
                'iom'=>'',
                'bio'=>'',
                'test'=>'0',
                'lastCommand'=>'NO',
                'report'=>''
            ]);
            
        }catch( \Exception $e){
            Log::error($e);
        }
    }
    /**
     * Setea estados de perifericos y IO
     *
     * @param  iom String
     * @param  IO String
     *
     */
    public static function setIO($movil,$io){
        if(!self::$client)self::setClient();
        try{
            Log::error("seteando IO:".$io." al movil:".$movil->imei);
           self::$client->hSet($movil->imei,'io',$io); 
        }catch(Exception $e){
            Log::error("Error al setear estado en redis IMEI:".$movil->imei."--".$e);
        }
        //$redis->hGet('h', 'key1'); /* returns "hello" */
    }
    public static function setIOM($movil,$iom){
        if(!self::$client)self::setClient();
        try{
            Log::error("seteando iom:".$iom." al movil:".$movil->imei);
           self::$client->hSet($movil->imei,'iom',$iom); 
        }catch(Exception $e){
            Log::error("Error al setear estado en redis IMEI:".$movil->imei."--".$e);
        }
        //$redis->hGet('h', 'key1'); /* returns "hello" */
    }
    public static function setBIO($movil,$bio){
        if(!self::$client)self::setClient();
        try{
            Log::error("seteando iom:".$bio." al movil:".$movil->imei);
           self::$client->hSet($movil->imei,'bio',$bio); 
        }catch(Exception $e){
            Log::error("Error al setear BIO en redis IMEI:".$movil->imei."--".$e);
        }
        //$redis->hGet('h', 'key1'); /* returns "hello" */
    }
    public static function setPosicionMovil($posicion){
        if(!self::$client)self::setClient();
        try{
            self::$client->hSet($posicion['imei'],'fecha_posicion',$posicion['fecha']); 
            self::$client->hSet($posicion['imei'],'velocidad',$posicion['velocidad']);
            self::$client->hSet($posicion['imei'],'indice',$posicion['indice']);
        }catch( \Exception $e){
            Log::error("Error al setear ultima posicion IMEI:".$posicion['imei']."---".$e);
        }
    }
    public static function setTestMovil($imei,$listado){
        if(!self::$client)self::setClient();
        try{
           self::$client->hSet($imei,'test',$listado); 
        }catch(Exception $e){
            Log::error("Error al setear estado en redis IMEI:".$movil->imei."--".$e);
        }
    }
    public static function setLastCommand($imei,$tr_id){
        if(!self::$client)self::setClient();
        try{
           self::$client->hSet($imei,'lastCommand',$tr_id); 
        }catch(Exception $e){
            Log::error("Error al setear estado en redis IMEI:".$movil->imei."--".$e);
        }
    }
    public static function setCommandListTest($imei,$commandList){
        if(!self::$client)self::setClient();
        try{
           self::$client->hSet($imei,'test',$commandList); 
        }catch(Exception $e){
            Log::error("Error al setear estado en redis IMEI:".$movil->imei."--".$e);
        }
    }
    public static function setLastReport($imei,$cadena){
        if(!self::$client)self::setClient();
        try{
           self::$client->hSet($imei,'report',$cadena); 
        }catch(Exception $e){
            Log::error("Error al setear ultima cadena en redis IMEI:".$movil->imei."--".$e);
        }
    }
    public static function deletePosicionMovil($posicion){
        if(!self::$client)self::setClient();
        try{
            self::$client->hDel($posicion['imei'],'fecha_posicion',$posicion['fecha']); 
            self::$client->hDel($posicion['imei'],'velocidad',$posicion['velocidad']);
            self::$client->hDel($posicion['imei'],'indice',$posicion['indice']);
        }catch( \Exception $e){
            Log::error($e);
        }
    }
    public static function storeMoviles($moviles){
        foreach($moviles as $movil){
            self::$client->del($movil->imei);
            $devuelto= self::setMovil($movil);
           // $data = $client->hgetall($movil->imei);
           // Log::info(print_r($data,true));
        }
        Log::info(":::::::::Moviles en Redis:".count($moviles).":::::::::");
    }
    public static function lookForMovil($imei){
        $movil=false;
        if(!self::$client)self::setClient();
        $data = self::$client->hgetall($imei);
        if(isset($data['equipo_id'])){
            $movil=$data;
            Log::info("Movil encontrado equipo:".$data['equipo_id']);
        }else{
            Log::error("el movil no esta en REDIS::::::");
        }
        return json_decode(json_encode($movil), FALSE); 
    }
}
