<?php
namespace App\Helpers;
use Log;
/************ class MemVar **********************
class MemVar {
 
	var $identifier="";
	var $key = "";
	var $shm_size=0;
	function MemVar( $_key ) {

		$this->key 			= $_key;
		$this->identifier 	= shmop_open($_key, "w", 0644, 100);
		
		//$this->$identifier = @shm_attach( $_key );
	}

	function setValue( $_valor ) {
		if ($this->$identifier) {
		  	Log::info("en setValue:".$_keyvar. "el valor:".$_valor);
			shmop_write($this->$identifier, $_valor, 0);	
			$this->shm_size 	= shmop_size($this->identifier);
		} else {
		   #you need to create it with shmop_open using "c" only
		}
		
	}

	function getValue( $_keyvar ) {
		$my_string = shmop_read($this->identifier, 0, $this->shm_size);
		
		return $my_string;
	}

	function eliminar( ) {
	@shm_remove( $this->$identifier  );
	}

	function close( ) {
	@shm_detach( $this->$identifier );
	}
}
*/
class MemVar {
 
 var $identifier="";
 var $key = "";
 
 function MemVar( $_key ) {
  
  $this->key = $_key;
  
  $this->identifier = @shm_attach( $_key );
 }
 
 function setValue( $_keyvar , $_valor ) {
  @shm_put_var( $this->identifier , $_keyvar  , $_valor  );
 }
 
 function getValue( $_keyvar ) {
  return @shm_get_var( $this->identifier , $_keyvar );
 }
 
 function eliminar( ) {
  @shm_remove( $this->identifier  );
 }
 
 function close( ) {
  @shm_detach( $this->identifier );
 }
}