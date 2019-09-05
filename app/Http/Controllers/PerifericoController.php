<?php
namespace App\Http\Controllers;

use App\EstadosSensores;
use App\Instalacion;
use App\Periferico;
use App\Movil;
use App\Helpers\HelpMen;
use App\Helpers\MemVar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

namespace App\Http\Controllers;
/**
 * Encargado de administrar la tabla perif_io
 * bits IO y IOM
 * @author Alan Ramirez Moratorio
 */
class PerifericoController {
    private function __clone() {} //Prevent any copy of this object
    private function __wakeup() {}
    public function __construct() {}
    public static function getSensores($equipo_id){
        $sensor = new App\Periferico;
        $sensor->obtenerSensores($equipo_id);
    }
}
