<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEstadosSensoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('moviles')->create('estados_sensores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('imei');
            $table->bigInteger('movil_id');
            $table->foreign('imei')->references('imei')->on('PLACAS_CELULARES');
            $table->string('iom')->nullable();
            $table->string('io')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('estados_sensores');
    }
}
