<?php
namespace App\Helpers;
/************ class MemVar ***********************/
class MemVar {
 
	var $identifier="";
	var $key = "";

	function MemVar( $_key ) {

	$this->$key = $_key;

	$this->$identifier = @shm_attach( $_key );
	}

	function setValue( $_keyvar , $_valor ) {
	@shm_put_var( $this->$identifier , $_keyvar  , $_valor  );
	}

	function getValue( $_keyvar ) {
	return @shm_get_var( $this->$identifier , $_keyvar );
	}

	function eliminar( ) {
	@shm_remove( $this->$identifier  );
	}

	function close( ) {
	@shm_detach( $this->$identifier );
	}
}