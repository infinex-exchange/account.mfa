<?php

use Infinex\Exceptions\Error;

class ProvidersAPI {
    private $log;
    private $providers;
    private $mfa;
    
    function __construct($log, $providers, $mfa) {
        $this -> log = $log;
        $this -> providers = $providers;
        $this -> mfa = $mfa;
        
        $this -> log -> debug('Initialized providers API');
    }
    
    public function initRoutes($rc) {
        $rc -> get('/providers', [$this, 'getProviders']);
        $rc -> put('/providers/{provider}', [$this, 'configureProvider']);
        $rc -> post('/providers/{provider}', [$this, 'enableProvider']);
        $rc -> delete('/providers/{provider}', [$this, 'removeProvider']);
    }
    
    public function getProviders($path, $query, $body, $auth) {
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
        
        $resp = $this -> providers -> getUserProviders([
            'uid' => $auth['uid']
        ]);
        
        foreach($resp['providers'] as $k => $v)
            $resp['providers'][$k] = $this -> ptpProvider($v);
        
        return $resp;
    }
    
    public function configureProvider($path, $query, $body, $auth) {
        $th = $this;
        
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
       
       return $this -> mfa -> mfa([
           'uid' => $auth['uid'],
           'case' => null,
           'action' => 'config2fa',
           'context' => [ 'configureProvider' => $path['provider'] ],
           'code' => @$body['code2FA']
       ]) -> then(function() use($th, $auth, $path) {
           return $th -> providers -> configureUserProvider([
               'uid' => $auth['uid'],
               'provider' => $path['provider']
           ]);
       });
    }
    
    public function enableProvider($path, $query, $body, $auth) {
        $th = $this;
        
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
        
        return $this -> mfa -> mfa([
           'uid' => $auth['uid'],
           'case' => null,
           'action' => 'config2fa',
           'context' => [ 'enableProvider' => $path['provider'] ],
           'code' => @$body['code2FA']
        ]) -> then(function() use($th, $auth, $path) {
            $th -> providers -> enableUserProvider([
                'uid' => $auth['uid'],
                'provider' => $path['provider']
            ]);
        });
    }
    
    public function removeProvider($path, $query, $body, $auth) {
        $th = $this;
        
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
        
        return $this -> mfa -> mfa([
           'uid' => $auth['uid'],
           'case' => null,
           'action' => 'config2fa',
           'context' => [ 'removeProvider' => $path['provider'] ],
           'code' => @$body['code2FA']
        ]) -> then(function() use($th, $auth, $path) {
            $th -> providers -> removeUserProvider([
                'uid' => $auth['uid'],
                'provider' => $path['provider']
            ]);
        });
    }
    
    private function ptpProvider($record) {
        return [
            'description' => $record['description'],
            'configured' => $record['configured'],
            'enabled' => $record['enabled']
        ];
    }
}

?>