<?php

use Infinex\Exceptions\Error;

class EmailProvider {
    private $log;
    private $amqp;
    private $pdo;
    
    function __construct($log, $amqp, $pdo) {
        $this -> log = $log;
        $this -> amqp = $amqp;
        $this -> pdo = $pdo;
        
        $this -> log -> debug('Initialized email provider');
    }
    
    public function getId() {
        return 'EMAIL';
    }
    
    public function getDescription() {
        return 'E-mail codes';
    }
    
    public function configure($uid) {
        return [
            'private' => [
            ],
            'public' => [
            ]
        ];
    }
    
    public function challenge($uid, $config, $action, $context) {
        $th = $this;
        
        $generatedCode = sprintf('%06d', rand(0, 999999));
        
        $task = array(
            ':uid' => $uid,
            ':action' => $action,
            ':context_hash' => md5(json_encode($context)),
            ':code' => $generatedCode
        );
        
        $sql = "INSERT INTO email_codes (
            uid,
            action,
            context_hash,
            code
        )
        VALUES (
            :uid,
            :action,
            :context_hash,
            :code
        )";
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        
        return $this -> amqp -> call(
            'account.account',
            'getUser',
            [
                'uid' => $uid
            ]
        ) -> then(function($user) use($th, $uid, $action, $context, $generatedCode) {
            $context['code'] = $generatedCode;
            
            $th -> amqp -> pub(
                'mail',
                [
                    'uid' => $uid,
                    'template' => '2fa_'.$action,
                    'context' => $context,
                    'email' => $user['email']
                ]
            );
            
            return $user['email'];
        });
    }
    
    public function response($uid, $config, $action, $context, $code) {
        if(!$this -> validateCode($code))
            throw new Error('VALIDATION_ERROR', 'code', 400);
        
        $task = array(
            ':uid' => $uid,
            ':action' => $action,
            ':context_hash' => md5(json_encode($context)),
            ':code' => $code
        );
        
        $sql = 'DELETE FROM email_codes
                WHERE uid = :uid
                AND action = :action
                AND context_hash = :context_hash
                AND code = :code
                RETURNING 1';
        
        $q = $this -> pdo -> prepare($sql);
        $q -> execute($task);
        $row = $q -> fetch();
        
        if(!$row)
            return false;
        
        return true;
    }
    
    private function validateCode($code) {
        return preg_match('/^[0-9]{6}$/', $code);
    }
}

?>