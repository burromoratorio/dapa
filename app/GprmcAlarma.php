<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GprmcAlarma extends Model
{
    protected $table 	= 'gprmc_alarmas';
    protected $primaryKey = 'id';
    public $timestamps 	= true;
    protected $guarded = ['id'];
    protected $connection = 'baymax';
    /*
   public function entregas() {
       	return $this->hasMany('EntregaViaje');
    }*/
}
