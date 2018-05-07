<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
class Movil extends Model
{
    protected $table = 'MOVILES';
    protected $primaryKey = 'movil_id';
    public $timestamps = false;
    protected $connection = 'moviles';

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
        $moviles = DB::table('MOVILES')
        ->where('MOVILES.activo' ,'=', 1)
        ->join('INSTALACIONES', 'MOVILES.movil_id', '=', 'INSTALACIONES.movil_id')
        ->get();
        return $moviles;
    }
    public function viajes() {
        return $this->hasMany('App\Viaje');
    }
    public function instalacion() {
        return $this->hasOne('App\Instalacion','movil_id');
    }
}
