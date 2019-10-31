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
            //print_r($data);
            return $data;
            //$client->set('foo', 'bar');
            //return 'foo stored as ' . $client->get('foo');
       }catch( \Exception $e){
            Log::error($e);
        }
    }
    public function setMovil($client,$movil){
        try{
            $key = $movil->imei;
             $client->hmset($key, [
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
            $data = $client->hgetall($key);
            return $data;
        }catch( \Exception $e){
            Log::error($e);
        }
    }
    public static function storeMoviles($moviles){
        $client = new \Predis\Client();
        Log::info(print_r($moviles,true));
        foreach($moviles as $movil){
            $devuelto=$this->setMovil($client,$movil);
            
        }
        
    }
}
