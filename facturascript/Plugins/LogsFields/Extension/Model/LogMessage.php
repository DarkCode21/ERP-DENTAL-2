<?php
namespace FacturaScripts\Plugins\LogsFields\Extension\Model;
use Closure;
use Exception;
use FacturaScripts\Core\Model\User;

class LogMessage 
{
	public $email;
	
	public function saveInsertBefore(): Closure
    {
        return function () {
			$user = new User();
        	$user->loadFromCode($this->nick);
            $this->email =  $user->email;
       
        };
    }
	
	public function test() : Closure
	{
		return function () {
		  	$this->email = $this->toolBox()->utils()->noHtml($this->email);
      	};
	}

}