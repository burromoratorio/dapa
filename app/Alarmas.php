<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Alarmas extends Model
{
    protected $table        = 'ALARMAS';
    public $timestamps      = false;
    protected $connection   = 'siac';
    protected $primaryKey   = 'alarma_id';    
    protected $fillable     = array('posicion_id','movil_id','tipo_alarma_id','fecha_alarma','falsa');
    protected $dateFormat   = 'Y-m-d H:i:s';

    protected $dates = [
        'fecha_alarma'
    ];

    public function movil() {
        return $this->belongsTo('App\Movil');
    }
    public function posicion() {
        return $this->belongsTo('App\Posiciones');
    }
   
}
