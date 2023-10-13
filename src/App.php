<?php

require __DIR__.'/Providers/EmailProvider.php';
require __DIR__.'/Providers/GAProvider.php';

require __DIR__.'/Providers.php';
require __DIR__.'/Cases.php';
require __DIR__.'/MFA.php';

require __DIR__.'/API/ProvidersAPI.php';
require __DIR__.'/API/CasesAPI.php';

use React\Promise;

class App extends Infinex\App\App {
    private $pdo;
    
    private $providers;
    private $cases;
    private $mfa;
    
    private $providersApi;
    private $casesApi;
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
        
        $this -> providers = new Providers(
            $this -> log,
            $this -> amqp,
            $this -> pdo,
            [
                new EmailProvider(
                    $this -> log,
                    $this -> amqp,
                    $this -> pdo
                ),
                new GAProvider(
                    $this -> log
                )
            ],
            'EMAIL'
        );
        
        $this -> cases = new Cases(
            $this -> log,
            $this -> amqp,
            $this -> pdo
        );
        
        $this -> mfa = new MFA(
            $this -> log,
            $this -> amqp,
            $this -> providers,
            $this -> cases
        );
        
        $this -> providersApi = new ProvidersAPI(
            $this -> log,
            $this -> providers,
            $this -> mfa
        );
        
        $this -> casesApi = new CasesAPI(
            $this -> log,
            $this -> cases,
            $this -> mfa
        );
        
        $this -> rest = new Infinex\API\REST(
            $this -> log,
            $this -> amqp,
            [
                $this -> providersApi,
                $this -> casesApi
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
                return Promise\all([
                    $th -> providers -> start(),
                    $th -> cases -> start()
                ]);
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
                return Promise\all([
                    $th -> providers -> stop(),
                    $th -> cases -> stop()
                ]);
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