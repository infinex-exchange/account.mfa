<?php

use Infinex\Exceptions\Error;
use React\Promise;

class MFA {
    private $log;
    private $amqp;
    private $pdo;
    private $providers;
    private $cases;
    
    function __construct($log, $amqp, $pdo, $providers, $cases) {
        $this -> amqp = $amqp;
        $this -> providers = $providers;
        $this -> cases = $cases;
        
        $this -> log -> debug('Initialized MFA engine');
    }
    
    public function start() {
        $th = $this;
        
        $promises = [];
        
        $promises[] = $this -> amqp -> method(
            'mfa',
            [$this, 'mfa']
        );
        
        return Promise\all($promises) -> then(
            function() use($th) {
                $th -> log -> info('Started MFA engine');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to start MFA engine: '.((string) $e));
                throw $e;
            }
        );
    }
    
    public function stop() {
        $th = $this;
        
        $promises = [];
        
        $promises[] = $this -> amqp -> unreg('mfa');
        
        return Promise\all($promises) -> then(
            function() use ($th) {
                $th -> log -> info('Stopped MFA engine');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to stop MFA engine: '.((string) $e));
            }
        );
    }
    
    public function mfa($body) {
        if(!isset($body['uid']))
            throw new Error('MISSING_DATA', 'uid');
        if(!isset($body['action']))
            throw new Error('MISSING_DATA', 'action');
        
        if(! $this -> cases -> isRequired2FA($body['uid'], @$body['case']))
            return;
        
        if(!isset($body['code'])) {
            $challengeInfo = $this -> providers -> challenge(
                $body['uid'],
                $body['action'],
                @$body['context']
            );
            throw new Error('REQUIRE_2FA', $challengeInfo, 511);
        }
        
        if(! $this -> providers[ $provider['provider'] ] -> response(
            $body['uid'],
            $body['action'],
            @$body['context'],
            $body['code']
        ))
            throw new Error('INVALID_2FA', 'Invalid 2FA code', 401);
    }
}

?>