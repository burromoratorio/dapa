<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Posiciones extends Model
{
    protected $table = 'POSICIONES';
    public $timestamps = false;
    protected $connection = 'siac';
    protected $primaryKey = ['posicion_id'];    
    protected $fillable = array('movil_id','cmd_id','tipo', 'fecha','rumbo_id', 'fecha', 'latitud','longitud',
                            'velocidad','valida','estado_u','estavo_v','estado_w','km_recorridos','referencia',
                            'ltrs_consumidos','ltrs_100','rpm','ancho_pasada');

    public function movil() {
        return $this->belongsTo('App\Movil');
    }
    public function getAttribute($value)
    {
        return $this->attributes[$value];
    }
   
}
