<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GprmcEntrada extends Model
{
    protected $table 	= 'gprmc_entrada';
    protected $primaryKey = 'id';
    public $timestamps 	= false;
    protected $guarded = ['id'];
    /*
   public function entregas() {
       	return $this->hasMany('EntregaViaje');
    }*/
}