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
    
    public function index() {
        try{
            $client = new \Predis\Client();
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
    public static function setMovil($client,$movil){
        try{
            $key = $movil->imei;
            $client->hmset:moviles($key, [
                'equipo_id' => $movil->equipo_id,
                'movil_id' => $movil->movil_id,
                'frec_rep_detenido' => $movil->frec_rep_detenido,
                'frec_rep_velocidad' => $movil->frec_rep_velocidad,
                'frec_rep_exceso_vel'=>$movil->frec_rep_exceso_vel,
                'velocidad_max'=>$movil->velocidad_max,
                'perif_io_id'=>$movil->perif_io_id,
                'movilOldId'=>$movil->movilOldId,
                'estado_u'=>$movil->estado_u,
                'estado_v'=>$movil->estado_v
            ]);
        }catch( \Exception $e){
            Log::error($e);
        }
    }
    public static function setPosicionMovil($client,$posicion){
        try{
            $key = $posicion['imei'];
             $client->hmset($key, [
                'fecha' => $posicion['fecha'],
                'velocidad' => $posicion['velocidad'],
                'indice' => $posicion['indice'],
                ]);
            //$data = $client->hgetall($key);
            //eturn $data;
        }catch( \Exception $e){
            Log::error($e);
        }
    }
    public static function deletePosicionMovil($client,$posicion){
        try{
            $key = $posicion['imei'];
             $client->hdel('imei',$imei);
                
            //$data = $client->hgetall($key);
            //eturn $data;
        }catch( \Exception $e){
            Log::error($e);
        }
    }
    public static function storeMoviles($moviles){
        $client = new \Predis\Client();
        foreach($moviles as $movil){
            $devuelto= self::setMovil($client,$movil);
        }
        Log::info(":::::::::Moviles en Redis:".count($moviles).":::::::::");
    }
    public static function lookForMovil($client,$imei){
        $movil=false;
        $data = $client->hgetall($imei);
        if(isset($data['equipo_id'])){
            $movil=$data;
            Log::info(print_r($data, true));
        }else{
            Log::error("el movil no esta");
        }
    }
}
