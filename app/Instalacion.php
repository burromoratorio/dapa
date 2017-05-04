<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Instalacion extends Model
{
    protected $table = 'INSTALACIONES';
    protected $primaryKey = 'instalacion_id';
    public $timestamps = false;
    protected $fillable 	= ['equipo_id', 'localidad_id', 'movil_id'];
    protected $guarded = ['movil'];
    /*public function movil() {
        return $this->hasOne('App\Movil');
    }*/

}
