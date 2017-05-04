<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Movil extends Model
{
    protected $table = 'MOVILES';
    protected $primaryKey = 'movil_id';
    public $timestamps = false;

    public function posiciones() {
        return $this->hasMany('App\Posiciones');
    }

    public function cliente() {
        return $this->belongsTo('App\Cliente');
    }

    public function reenvio_movil() {
        return $this->hasOne('App\ReenvioMovil');
    }
    static function viajesAbiertos($dominio){
        return Movil::wherehas('viajes',function($query){ 
                $query->select('viaje_id','movil_id')
                ->whereNull('fecha_fin'); })
                ->where('dominio',$dominio)->first();
    }
    static function instalados(){
        return Movil::wherehas('instalacion',function($query){ 
                $query->select('instalacion_id','movil_id')
                ->where('activo',1)->get();
        )}
    }
    public function viajes() {
        return $this->hasMany('App\Viaje');
    }
    public function instalacion() {
        return $this->hasOne('App\Instalacion');
    }
}
