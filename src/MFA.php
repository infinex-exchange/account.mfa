<?php

use Infinex\Exceptions\Error;
use function Infinex\Validation\validateId;
use React\Promise;

class MFA {
    private $log;
    private $amqp;
    private $providers;
    private $cases;
    
    function __construct($log, $amqp, $providers, $cases) {
        $this -> log = $log;
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
        
        if(!validateId($body['uid']))
            throw new Error('VALIDATION_ERROR', 'uid');
        if(!is_string($body['action']))
            throw new Error('VALIDATION_ERROR', 'action');
            
        if(isset($body['context']) && !is_array($body['context']))
            throw new Error('VALIDATION_ERROR', 'context is not null or array');
        
        $context = isset($body['context']) ? $body['context'] : [];
        
        if(! $this -> cases -> isRequired2FA($body['uid'], @$body['case']))
            return Promise\resolve(null);
        
        if(!isset($body['code'])) {
            return Promise\resolve(
                $this -> providers -> challenge(
                    $body['uid'],
                    $body['action'],
                    $context
                )
            ) -> then(function($challengeInfo) {
                throw new Error('REQUIRE_2FA', $challengeInfo, 511);
            });
        }
        
        return Promise\resolve(
            $this -> providers -> response(
                $body['uid'],
                $body['action'],
                $context,
                $body['code']
            )
        ) -> then(function($valid) {
            if(!$valid)
                throw new Error('INVALID_2FA', 'Invalid 2FA code', 403);
        });
    }
}

?>