<?php

use Infinex\Exceptions\Error;
use function Infinex\Validation\validateId;
use React\Promise;

class Cases {
    private $log;
    private $amqp;
    private $pdo;
    
    function __construct($log, $amqp, $pdo) {
        $this -> log = $log;
        $this -> amqp = $amqp;
        $this -> pdo = $pdo;
        
        $this -> log -> debug('Initialized cases manager');
    }
    
    public function start() {
        $th = $this;
        
        $promises = [];
        
        $promises[] = $this -> amqp -> method(
            'getCases',
            [$this, 'getCases']
        );
        
        $promises[] = $this -> amqp -> method(
            'getUserCases',
            [$this, 'getUserCases']
        );
        
        $promises[] = $this -> amqp -> method(
            'updateUserCases',
            [$this, 'updateUserCases']
        );
        
        return Promise\all($promises) -> then(
            function() use($th) {
                $th -> log -> info('Started cases manager');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to start cases manager: '.((string) $e));
                throw $e;
            }
        );
    }
    
    public function stop() {
        $th = $this;
        
        $promises = [];
        
        $promises[] = $this -> amqp -> unreg('getCases');
        $promises[] = $this -> amqp -> unreg('getUserCases');
        $promises[] = $this -> amqp -> unreg('updateUserCases');
        
        return Promise\all($promises) -> then(
            function() use ($th) {
                $th -> log -> info('Stopped cases manager');
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error('Failed to stop cases manager: '.((string) $e));
            }
        );
    }
    
    public function getCases() {
        $sql = 'SELECT caseid,
                       description
                FROM cases
                ORDER BY caseid ASC';
        
        $q = $this -> pdo -> query($sql);
        
        $cases = [];
        while($row = $q -> fetch())
            $cases[ $row['caseid'] ] = [
                'description' => $row['description']
            ];
        
        return [
            'cases' => $cases
        ];
    }
    
    public function getUserCases($body) {
        if(!isset($body['uid']))
            throw new Error('MISSING_DATA', 'uid');
        
        if(!validateId($body['uid']))
            throw new Error('VALIDATION_ERROR', 'uid');
        
        $cases = $this -> getCases();
        foreach($cases['cases'] as $caseid => $v)
            $cases['cases'][$caseid]['enabled'] = false;
        
        $task = [
            ':uid' => $body['uid']
        ];
        
        $sql = 'SELECT cases
                FROM user_cases
                WHERE uid = :uid';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        if(!$row)
            return $cases;
        
        $override = json_decode($row['cases'], true);
        foreach($override as $caseid => $enabled)
            $cases['cases'][$caseid]['enabled'] = $enabled;
        
        return $cases;
    }
    
    public function updateUserCases($body) {
        if(!isset($body['cases']))
            throw new Error('MISSING_DATA', 'cases', 400);
        
        if(!is_array($body['cases']))
            throw new Error('VALIDATION_ERROR', 'cases is not an array', 400);
        
        $cases = $this -> getUserCases([
            'uid' => @$body['uid']
        ]);
        
        $override = [];
        foreach($cases['cases'] as $caseid => $v)
            $override[$caseid] = $v['enabled'];
        
        foreach($body['cases'] as $caseid => $enabled) {
            if(!array_key_exists($caseid, $cases['cases']))
                throw new Error('VALIDATION_ERROR', 'cases contains an invalid key', 400);
            if(!is_bool($enabled))
                throw new Error('VALIDATION_ERROR', 'cases contains a non-boolean value', 400);
            
            $override[$caseid] = $enabled;
        }
        
        $task = [
            ':uid' => $body['uid'],
            ':cases' => json_encode($override)
        ];
        
        $sql = 'UPDATE user_cases
                SET cases = :cases
                WHERE uid = :uid
                RETURNING 1';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        if(!$row) {
            $sql = 'INSERT INTO user_cases(uid, cases) VALUES(:uid, :cases)';
            
            $q = $this -> pdo -> prepare($sql);
            $q -> execute($task);
        }
    }
    
    public function isRequired2FA($uid, $case) {
        if(!$case)
            return true;
        
        $cases = $this -> getUserCases([
            'uid' => $uid
        ]);
        
        if(!isset($cases['cases'][$case]))
            throw new Error('NOT_FOUND', 'Unknown case');
        
        return $cases['cases'][$case]['enabled'];
    }
}

?>