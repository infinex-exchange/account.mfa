<?php

use Infinex\Exceptions\Error;
use PragmaRX\Google2FA\Google2FA;

class GAProvider {
    private $log;
    private $amqp;
    private $google2fa;
    
    function __construct($log, $amqp) {
        $this -> log = $log;
        $this -> amqp = $amqp;
        
        $this -> google2fa = new Google2FA();
        
        $this -> log -> debug('Initialized Google Authenticator provider');
    }
    
    public getId() {
        return 'GA';
    }
    
    public function getDescription() {
        return 'Google Authenticator';
    }
    
    public function configure($uid) {
        $th = $this;
        
        return $this -> amqp -> call(
            'account.account',
            'uidToEmail',
            [
                'uid' => $uid
            ]
        ) -> then(function($email) use($th) {
            $secret = $th -> google2fa -> generateSecretKey();
            $url = $th -> google2fa -> getQRCodeUrl(
                'Infinex',
                $email,
                $secret
            );
            
            return [
                'private' => [
                    'secret' => $secret
                ],
                'public' => [
                    'url' => $url
                ]
            ];
        });
    }
    
    public function challenge($uid, $config, $action, $context) {
        return $this -> getDescription();
    }
    
    public function response($uid, $config, $action, $context, $code) {
        if(!$this -> validateCode($code))
            throw new Error('VALIDATION_ERROR', 'code', 400);
        
        return $this -> google2fa -> verifyKey($config['secret'], $code);
    }
    
    private function validateCode($code) {
        return preg_match('/^[0-9]{6}$/', $code);
    }
}

?>