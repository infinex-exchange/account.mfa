<?php

use Infinex\Exceptions\Error;

class ProvidersAPI {
    private $log;
    private $providers;
    
    function __construct($log, $providers) {
        $this -> log = $log;
        $this -> providers = $providers;
        
        $this -> log -> debug('Initialized providers API');
    }
    
    public function initRoutes($rc) {
        $rc -> get('/providers', [$this, 'getProviders']);
        $rc -> put('/providers/{prov}', [$this, 'configureProvider']);
        $rc -> post('/providers/{prov}', [$this, 'enableProvider']);
        $rc -> delete('/providers/{prov}', [$this, 'resetProvider']);
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
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
        
        //
    }
    
    public function enableProvider($path, $query, $body, $auth) {
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
        
        //
    }
    
    public function resetProvider($path, $query, $body, $auth) {
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
        
        //
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