<?php

require __DIR__.'/EmailCodes.php';
require __DIR__.'/MFA.php';

require __DIR__.'/API/MFAAPI.php';

use React\Promise;

class App extends Infinex\App\App {
    private $pdo;
    
    private $ec;
    private $mfa;
    
    private $mfaApi;
    private $rest;
    
    function __construct() {
        parent::__construct('account.mfa');
        
        $this -> pdo = new Infinex\Database\PDO(
            $this -> loop,
            $this -> log,
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );
        
        $this -> ec = new EmailCodes(
            $this -> log,
            $this -> pdo
        );
        
        $this -> mfa = new MFA(
            $this -> log,
            $this -> amqp,
            $this -> pdo,
            $this -> ec
        );
        
        $this -> mfaApi = new MFAAPI(
            $this -> log,
            $this -> mfa
        );
        
        $this -> rest = new Infinex\API\REST(
            $this -> log,
            $this -> amqp,
            [
                $this -> mfaApi
            ]
        );
    }
    
    public function start() {
        $th = $this;
        
        parent::start() -> then(
            function() use($th) {
                return $th -> pdo -> start();
            }
        ) -> then(
            function() use($th) {
                return $th -> ec -> start();
            }
        ) -> then(
            function() use($th) {
                return $th -> mfa -> start();
            }
        ) -> then(
            function() use($th) {
                return $th -> rest -> start();
            }
        ) -> catch(
            function($e) {
                $th -> log -> error('Failed start app: '.((string) $e));
            }
        );
    }
    
    public function stop() {
        $th = $this;
        
        $this -> rest -> stop() -> then(
            function() use($th) {
                return $th -> mfa -> stop();
            }
        ) -> then(
            function() use($th) {
                return $th -> ec -> stop();
            }
        ) -> then(
            function() use($th) {
                return $th -> pdo -> stop();
            }
        ) -> then(
            function() use($th) {
                $th -> parentStop();
            }
        );
    }
    
    private function parentStop() {
        parent::stop();
    }
}

?>