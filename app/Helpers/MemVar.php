<?php
namespace App\Helpers;
use Log;
/************ class MemVar **********************/

/**
 * Singleton class
 *
 */
final class MemVar
{
    /**
     * Call this method to get singleton
     *
     * @return UserFactory
     */
    private static $identifier = null;
    private static $key  = '';
    private static $size = 0;
    private static $shm_key = null;
    const MCSTORAGE   = '/var/www/dapa/storage/app';
  	

    public static function Instance($datArchive)
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new MemVar();
            self::$shm_key 	= ftok(self::MCSTORAGE.'/'.$datArchive, 't');
        }else{
        	Log::error("es singleton, cuantas instancias queres!");
        }
        Log::info("creando singleton");
        return $inst;
    }

    /**
     * Private ctor so nobody else can instantiate it
     *
     */
    private function __construct()
    {

    }
    public function init($datArchive,$s){
    	self::$shm_key 	= ftok(self::MCSTORAGE.'/'.$datArchive, 't');
    	self::$size 	= $s;
    	Log::info("Seteando valores. key:". self::$shm_key."  size:".self::$size);
    	self::$identifier = shmop_open(self::$shm_key, "c", 0644, self::$size);
		if ( !is_null(self::$identifier) ){
			Log::info("se creo el siguiente identif:".self::$identifier);
		    return true;
		}else{
			Log::info("Couldn't create shared memory segment");
		    return false;
		}
	}
	public function setValue( $v ) {
		$shm_bytes_written = shmop_write(self::$identifier, $v, 0);
		shmop_close(self::$identifier);
		return $shm_bytes_written;
	}
	public static function Cargar($value,$datArchive,$size){
		self::$shm_key 		= ftok(self::MCSTORAGE.'/'.$datArchive, 't');
		self::$identifier 	= shmop_open(self::$shm_key, "c", 0644, $size);
		$shm_bytes_written 	= shmop_write(self::$identifier, $value, 0);
		shmop_close(self::$identifier);
		return $shm_bytes_written;
	}
	public static function initIdentifier($shmid){
		self::$identifier=$shmid;
	}
	public static function GetValue( ) {
		$my_string 	= "";
		if ( !is_null(self::$identifier) ){
			self::$size	= shmop_size(self::$identifier);
			$my_string 	= shmop_read(self::$identifier, 0, self::$size);
			//Log::info("The data inside shared memory was: " . $my_string );
		}else{
			Log::info("el identidier es null");
		}
		return $my_string;
	}
	public static function OpenToRead($datArchive){
		//Log::info("Abriendo solo para leer:". self::$shm_key."  size:".self::$size);
		self::$shm_key 	= ftok(self::MCSTORAGE.'/'.$datArchive, 't');
		@$shmid 		= shmop_open(self::$shm_key, "a", 0, 0);
		if (!empty($shmid)) {
	        return $shmid;
		} else {
	        return '0';
		}
    }
	public static function Eliminar( $datArchive ) {
		$shm_key 	= ftok(self::MCSTORAGE.'/'.$datArchive, 't');
		@$shmid 	= shmop_open(self::$shm_key, "w", 0644, self::$size);
		shmop_delete(@$shmid);
		
	}
	public static function VaciaMemoria(){
		$ipcs = array();
		exec('ipcs', $ipcs);
		foreach($ipcs as $row) {
			$row = explode(' ', $row);
		    if (!isset($row[1]))
		        continue;
		    $id  = trim($row[1]);
		    if (!is_numeric($id))
		        continue;
		    // Note: Consider adding filters here if you want to selectively remove resources
		    Log::info( "Removing Address {$id}" );
		    @exec("ipcrm -m {$id}");
		}
	}
}
