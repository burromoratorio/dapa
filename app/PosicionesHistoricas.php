<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PosicionesHistoricas extends Model
{
    protected $table        = 'POSICIONES_HISTORICAS';
    public $timestamps      = false;
    protected $connection   = 'siac';
    protected $primaryKey   = ['posicion_id'];    
    protected $fillable     = array('posicion_id','movil_id', 'fecha', 'velocidad','latitud','longitud','valida','cmd_id',
                                'km_recorridos','referencia','rumbo_id','estado_u' ,'estado_v' ,
                                'estado_w','ltrs_consumidos','ltrs_100');
                                        
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
