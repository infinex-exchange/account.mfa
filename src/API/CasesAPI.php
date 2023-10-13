<?php

use Infinex\Exceptions\Error;

class CasesAPI {
    private $log;
    private $cases;
    private $mfa;
    
    function __construct($log, $cases, $mfa) {
        $this -> log = $log;
        $this -> cases = $cases;
        $this -> mfa = $mfa;
        
        $this -> log -> debug('Initialized cases API');
    }
    
    public function initRoutes($rc) {
        $rc -> get('/cases', [$this, 'getCases']);
        $rc -> patch('/cases', [$this, 'updateCases']);
    }
    
    public function getCases($path, $query, $body, $auth) {
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
        
        $resp = $this -> cases -> getUserCases([
            'uid' => $auth['uid']
        ]);
        
        foreach($resp['cases'] as $k => $v)
            $resp['cases'][$k] = $this -> ptpCase($v);
        
        return $resp;
    }
    
    public function updateCases($path, $query, $body, $auth) {
        $th = $this;
        
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
       
       return $this -> mfa -> mfa([
           'uid' => $auth['uid'],
           'case' => null,
           'action' => 'config2fa',
           'context' => [ 'updateCases' => @$body['cases'] ],
           'code' => @$body['code2FA']
       ]) -> then(function() use($th, $auth, $body) {
           $th -> cases -> updateUserCases([
               'uid' => $auth['uid'],
               'cases' => @$body['cases']
           ]);
       });
    }
    
    private function ptpCase($record) {
        return [
            'description' => $record['description'],
            'enabled' => $record['enabled']
        ];
    }
}

?>