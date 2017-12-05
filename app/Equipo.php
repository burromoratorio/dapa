<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $table   = 'EQUIPOS';
    protected $primaryKey = 'equipo_id';
    public $timestamps = false;

    public function placa_celular() {
        return $this->hasOne('App\PlacaCelular','placa_celular_id','placa_celular_id');
    }
}