<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PosicionesHistoricas extends Model
{
    protected $table        = 'POSICIONES_HISTORICAS';
    public $timestamps      = false;
    protected $connection   = 'siac';
    protected $primaryKey   = ['posicion_id'];    
    protected $fillable     = array('movil_id', 'fecha', 'velocidad','latitud','longitud','valida','km_recorridos','referencia');
    protected $dateFormat   = 'Y-m-d H:i:s';

    protected $dates = [
        'fecha'
    ];
    public function movil() {
        return $this->belongsTo('App\Movil');
    }
    public function getAttribute($value)
    {
        return $this->attributes[$value];
    }
   
}
