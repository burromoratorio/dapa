<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Waypoint extends Model
{
    protected $table 	= 'waypoints';
    protected $primaryKey = 'waypoint_id';
    public $timestamps 	= false;
    protected $fillable = ['nombre', 'nombre_abreviado', 'latitud', 'longitud', 'codigo_waypoint', 'cliente_id'];
    /*
   public function entregas() {
       	return $this->hasMany('EntregaViaje');
    }*/
}
