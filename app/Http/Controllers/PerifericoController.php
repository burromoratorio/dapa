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
            $perif->sensor_pulsador_panico=$sensores[0];
            $perif->sensor_puerta_conductor=$sensores[1];
            $perif->sensor_puerta_acompaniante=$sensores[2];
            $perif->sensor_desenganche=$sensores[3];
            $perif->sensor_antisabotaje=$sensores[4];
            $perif->sensor_compuerta=$sensores[5];
            $perif->save();
            Log::info("Actualizando PerifericoController:::");

            DB::commit();
        }catch (\Exception $ex) {
            DB::rollBack();
            $errorSolo  = explode("Stack trace", $ex);
            $logcadena ="Error al procesar update de comandos ".$errorSolo[0]." \r\n";
            Log::info($logcadena);
        }
    }
}
