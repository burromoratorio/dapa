<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class PlacaCelular extends Model
{
    protected $table = 'PLACAS_CELULARES';
    protected $primaryKey = 'placa_celular_id';
    public $timestamps = false;

    public function equipo() {
        return $this->belongsTo('App\Equipo','placa_celular_id','placa_celular_id');
    }
    
}

