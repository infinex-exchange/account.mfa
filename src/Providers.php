<?php

use Infinex\Exceptions\Error;
use React\Promise;

class Providers {
    private $log;
    private $amqp;
    private $pdo;
    private $providers;
    private $defaultProvider;
    
    function __construct($log, $amqp, $pdo, $providers, $defaultProvider) {
        $this -> log = $log;
        $this -> amqp = $amqp;
        $this -> pdo = $pdo;
        $this -> providers = [];
        $this -> defaultProvider = $defaultProvider;
        
        if(!is_array($providers))
            $providers = [ $provider ];
        foreach($providers as $provider)
            $this -> providers[$provider -> getId()] = $provider;
        
        $this -> log -> debug('Initialized providers manager');
        $this -> log -> info(
            'Available providers: '.implode(', ', array_keys($this -> providers))
        );
        $this -> log -> info('Default provider: '.$this -> defaultProvider);
    }
    
    public function start() {
        $th = $this;
        
        $promises = [];
        
        $promises[] = $this -> amqp -> method(
            'getProviders',
            [$this, 'getProviders']
        );
        
        $promises[] = $this -> amqp -> method(
            'getUserProviders',
            [$this, 'getUserProviders']
        );
        
        return Promise\all($promises) -> then(
            function() use($th) {
                $th -> log -> info('Started providers manager');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to start providers manager: '.((string) $e));
                throw $e;
            }
        );
    }
    
    public function stop() {
        $th = $this;
        
        $promises = [];
        
        $promises[] = $this -> amqp -> unreg('getProviders');
        $promises[] = $this -> amqp -> unreg('getUserProviders');
        
        return Promise\all($promises) -> then(
            function() use ($th) {
                $th -> log -> info('Stopped providers manager');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to stop providers manager: '.((string) $e));
            }
        );
    }
    
    public function getProviders() {
        $providers = [];
        
        foreach($this -> providers as $providerid => $provider)
            $providers[$providerid] = [
                'description' => $provider -> getDescription()
            ];
        
        return [
            'providers' => $providers
        ];
    }
    
    public function getUserProviders($body) {
        if(!isset($body['uid']))
            throw new Error('MISSING_DATA', 'uid', 400);
        
        $providers = $this -> getProviders();
        foreach($providers['providers'] as $providerid => $v)
            $providers['providers'][$providerid]['configured'] = ($providerid == $this -> defaultProvider);
            $providers['providers'][$providerid]['enabled'] = false;
        
        $task = [
            ':uid' => $body['uid']
        ];
        
        $sql = 'SELECT providerid,
                       enabled
                FROM user_providers
                WHERE uid = :uid';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        
        $foundEnabled = false;
        while($row = $q -> fetch()) {
            $providers['providers'][ $row['providerid'] ]['configured'] = true;
            $providers['providers'][ $row['providerid'] ]['enabled'] = $row['enabled'];
            if($row['enabled'])
                $foundEnabled = true;
        }
        
        if(!$foundEnabled)
            $providers['providers'][$this -> defaultProvider]['enabled'] = true;
        
        return $providers;
    }
    
    public function configureUserProvider($body) {
        $th = $this;
        
        if(!isset($body['provider']))
            throw new Error('MISSING_DATA', 'provider', 400);
        
        $providers = $this -> getUserProviders([
            'uid' => @$body['uid']
        ]);
        
        if(!isset($providers['providers'][ $body['provider'] ]))
            throw new Error('NOT_FOUND', 'Unknown provider', 404);
        
        if($providers['providers'][ $body['provider'] ]['configured'])
            throw new Error('CONFLICT', 'Already configured', 409);
        
        return Promise\resolve(
            $this -> providers[ $body['provider'] ] -> configure(
                $body['uid']
            )
        ) -> then(function($th, $config, $body) {
            $task = [
                ':uid' => $body['uid'],
                ':providerid' => $body['provider'],
                ':config' => json_encode($config['private'], JSON_UNESCAPED_SLASHES)
            ];
            
            $sql = 'INSERT INTO user_providers(
                        uid,
                        providerid,
                        config
                    )
                    VALUES(
                        :uid,
                        :providerid,
                        :config
                    )';
            
            $q = $th -> pdo -> prepare($sql);
            $q -> execute($task);
            
            return $config['public'];
        });
    }
    
    public function removeUserProvider($body) {
        if(!isset($body['provider']))
            throw new Error('MISSING_DATA', 'provider', 400);
        
        $providers = $this -> getUserProviders([
            'uid' => @$body['uid']
        ]);
        
        if(!isset($providers['providers'][ $body['provider'] ]))
            throw new Error('NOT_FOUND', 'Unknown provider', 404);
        
        if(! $providers['providers'][ $body['provider'] ]['configured'])
            throw new Error('CONFLICT', 'Already not configured', 409);
        
        $task = [
            ':uid' => $body['uid'],
            ':providerid' => $body['provider']
        ];
        
        $sql = 'DELETE FROM user_providers
                WHERE uid = :uid
                AND providerid = :providerid';
        
        $q = $th -> pdo -> prepare($sql);
        $q -> execute($task);
    }
    
    public function enableUserProvider($body) {
        if(!isset($body['provider']))
            throw new Error('MISSING_DATA', 'provider', 400);
        
        $providers = $this -> getUserProviders([
            'uid' => @$body['uid']
        ]);
        
        if(!isset($providers['providers'][ $body['provider'] ]))
            throw new Error('NOT_FOUND', 'Unknown provider', 404);
        
        if(! $providers['providers'][ $body['provider'] ]['configured'])
            throw new Error('NOT_CONFIGURED', 'Cannot enable not configured provider', 403);
        
        $task = [
            ':uid' => $body['uid'],
            ':providerid' => $body['provider']
        ];
        
        $sql = 'UPDATE user_providers
                SET enabled = TRUE
                WHERE uid = :uid
                AND providerid = :providerid';
        
        $q = $th -> pdo -> prepare($sql);
        $q -> execute($task);
    }
    
    public function challenge($uid, $action, $context) {
        $pc = $this -> getUserProviderConfig($uid);
        
        return $this -> providers[ $pc['provider'] ] -> challenge(
            $uid,
            $pc['config'],
            $action,
            $context
        );
    }
    
    public function response($uid, $action, $context, $code) {
        $pc = $this -> getUserProviderConfig($uid);
        
        return $this -> providers[ $pc['provider'] ] -> response(
            $uid,
            $pc['config'],
            $action,
            $context,
            $code
        );
    }
    
    private function getUserProviderConfig($uid) {
        $task = [
            ':uid' => $uid
        ];
        
        $sql = 'SELECT providerid,
                       config
                FROM user_providers
                WHERE uid = :uid
                AND enabled = TRUE';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        if($row)
            return [
                'provider' => $row['providerid'],
                'config' => json_decode($row['config'], true)
            ];
        
        return [
            'provider' => $this -> defaultProvider,
            'config' => []
        ];
    }
}

?>