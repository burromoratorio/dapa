<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Periferico extends Model
{
    protected $table = 'PERIF_IO';
    protected $primaryKey = 'perif_io_id';
    public $timestamps = false;
    protected $fillable 	= ['sensor_pulsador_panico','sensor_puerta_conductor','sensor_puerta_acompaniante',
                                    'sensor_desenganche','sensor_antisabotaje','sensor_compuerta','sensor_encendido'];
    
    public function instalacion() {
        return $this->belongsTo('App\Instalacion','instalacion_id','instalacion_id');
    }
    public static function obtenerSensores($equipo_id){
        //return Periferico::with('instalacion')->where('instalacion.equipo_id','=',$equipo_id)->get()->first();
        return Periferico::join('INSTALACIONES', 'PERIF_IO.instalacion_id', '=', 'INSTALACIONES.instalacion_id')
                        ->when($equipo_id, function ($query) use ($equipo_id) {
                            return $query->where('INSTALACIONES.equipo_id', $equipo_id);
                        })->get()->first();
    }
}
