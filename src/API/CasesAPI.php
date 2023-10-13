<?php

use Infinex\Exceptions\Error;

class CasesAPI {
    private $log;
    private $cases;
    
    function __construct($log, $cases) {
        $this -> log = $log;
        $this -> cases = $cases;
        
        $this -> log -> debug('Initialized cases API');
    }
    
    public function initRoutes($rc) {
        $rc -> get('/cases', [$this, 'getCases']);
        $rc -> patch('/cases', [$this, 'updateCases']);
    }
    
    public function getCases($path, $query, $body, $auth) {
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
        
        //
    }
    
    public function updateCases($path, $query, $body, $auth) {
        if(!$auth)
            throw new Error('UNAUTHORIZED', 'Unauthorized', 401);
        
        //
    }
    
    private function ptpCases($record) {
        //
    }
}

?>