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
    const MCSTORAGE   = '/var/www/dapa/storage';
  	

    public static function Instance($datArchive)
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new MemVar();
            //self::$shm_key = ftok('/bin/ls', 't');
            self::$shm_key 	= ftok(self::MCSTORAGE.'/'.$datArchive, 't');
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
    	//self::$shm_key 	= ftok('/bin/ls', 't');
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
		}
		return $my_string;
	}
	public static function OpenToRead($datArchive){
		//self::$shm_key 	= ftok('/bin/ls', 't');
		Log::error("EL FTOKKKKK".self::MCSTORAGE.'/'.$datArchive);
		self::$shm_key 	= ftok(self::MCSTORAGE.'/'.$datArchive, 't');
		Log::info("Abriendo solo para leer:". self::$shm_key."  size:".self::$size);
		@$shmid 			= shmop_open(self::$shm_key, "a", 0, 0);
		if (!empty($shmid)) {
	        Log::info("shared memory exists");
	        return $shmid;
		} else {
	        Log::info("shared memory doesn't exist");
	        return '0';
		}
    	//self::$identifier = shmop_open(self::$shm_key, "c", 0, 0);
	}
	public static function Eliminar( ) {
		Log::info("The identifier::::::::: " . self::$identifier );
		if ( !is_null(self::$identifier) ){
			if (!shmop_delete(self::$identifier)) {
				Log::info("couldn't mark shared memory block for deletion.");
			}else{
				Log::info("MC eliminada ....limpiando variables.");
				self::$identifier 	= null;
				self::$key 			= '';
    			self::$size 		= 0;
			}
		}
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
