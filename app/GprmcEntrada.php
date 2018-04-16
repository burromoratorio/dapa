<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GprmcEntrada extends Model
{
    //protected $table 	= 'gprmc_entrada';
    protected $table 	= 'GPRMC_ENTRADA';
    protected $primaryKey = 'id';
    public $timestamps 	= false;
    protected $guarded = ['id'];
    protected $connection = 'baymax';
    /*
   public function entregas() {
       	return $this->hasMany('EntregaViaje');
    }*/
}
