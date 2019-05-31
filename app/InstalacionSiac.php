<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InstalacionSiac extends Model
{
    protected $table = 'INSTALACIONES';
    protected $primaryKey = 'modem_id';
    protected $connection   = 'siac';
    public $timestamps = false;
    protected $fillable 	= ['modem_id','frecuencia_reporte_velocidad','frecuencia_reporte_detenido','frecuencia_reporte_keepalive',
								'frecuencia_reporte_exceso_velocidad','frecuencia_reporte_sin_posicion','exceso_velocidad'];
    
    /*public function movil() {
        return $this->hasOne('App\Movil');
    }*/

}
