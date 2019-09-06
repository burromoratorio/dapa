<?php
namespace App\Http\Controllers;

use App\Periferico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

/**
 * Encargado de administrar la tabla perif_io
 * bits IO y IOM
 * @author Alan Ramirez Moratorio
 */
class PerifericoController extends BaseController 
{
    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    public function __construct() {}
    public static function getSensores($equipo_id){
        $periferico = Periferico::obtenerSensores($equipo_id);
        return $periferico;
    }
    public static function setSensores($equipo_id,$sensores){
        DB::beginTransaction();
        try{
            
            $perif = self::getSensores($equipo_id);
            $consumer = Periferico::find($perif->perif_io_id);
            $consumer->sensor_pulsador_panico=$sensores[0];
            $consumer->sensor_puerta_conductor=$sensores[1];
            $consumer->sensor_puerta_acompaniante=$sensores[2];
            $consumer->PERIF_IO->sensor_desenganche=$sensores[3];
            $consumer->PERIF_IO->sensor_antisabotaje=$sensores[4];
            $consumer->sensor_compuerta=$sensores[5];
            $consumer->PERIF_IO->sensor_encendido=$sensores[7];
            $consumer->save();
            Log::info("Actualizando PerifericoController:::iddd:".$perif->perif_io_id);
            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            $errorSolo  = explode("Stack trace", $ex);
            $logcadena ="Error al procesar update de comandos ".$errorSolo[0]." \r\n";
            Log::info($logcadena);
        }
    }
}
