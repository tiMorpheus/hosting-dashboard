<?php

namespace Proxy\Util;

use Blazing\Reseller\Api\Api;
use Blazing\Common\RestApiRequestHandler\Exception\BadRequestException;
use Proxy\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;

class TFA
{
    // Authorize if no token in cookies, but authorized in past with same ip
    const VALIDATE_STRATEGY_IP_GRANTS = 1;
    // If ip has not been authorized in past, redirect to email validation
    const VALIDATE_STRATEGY_IP_REQUIRED = 2;

    protected $app;
    /** @var Api */
    protected $api;
    /** @var User */
    protected $user;
    /** @var  Request */
    protected $request;
    protected $strategy = self::VALIDATE_STRATEGY_IP_GRANTS;

    protected $cookieName = 'tfa';
    protected $token = '{userId}.secret';
    protected $sign = 'secret';
    protected $tokenExpiration = 365 * 24 * 60 * 60;
    protected $otpExpiration = .25 * 60 * 60;
    protected $attempts = 5;

    // --- Setup

    public function __construct(Application $app = null)
    {
        if ($app) {
            $this->setApplication($app);
        }
    }

    public function setApplication(Application $app)
    {
        $this->app = $app;
        $this->api = $app['api'];
        $this->user = $app['session.user'];

        foreach ([
            'config.mta.config.cookieName' => 'cookieName',
            'config.mta.config.tokenSecretMask' => 'token',
            'config.mta.config.signSecret' => 'sign',
            'config.mta.config.tokenExpiration' => 'tokenExpiration',
            'config.mta.config.otpExpiration' => 'otpExpiration',
            'config.mta.config.attempts' => 'attempts',
        ] as $config => $mappedTo) {
            if (isset($app[$config]) and !empty($app[$config])) {
                $this->$mappedTo = $app[$config];
            }
        }

        return $this;
    }

    public function setRequest(Request $request = null)
    {
        $this->request = $request;

        return $this;
    }

    // --- Configuration

    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;
    }

    /**
     * Set cookieName
     *
     * @param string $cookieName
     * @return $this
     */
    public function setCookieName($cookieName)
    {
        $this->cookieName = $cookieName;

        return $this;
    }

    /**
     * Set token
     *
     * @param string $token
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Set sign
     *
     * @param string $sign
     * @return $this
     */
    public function setSign($sign)
    {
        $this->sign = $sign;

        return $this;
    }

    /**
     * Set tokenExpiration
     *
     * @param int $tokenExpiration
     * @return $this
     */
    public function setTokenExpiration($tokenExpiration)
    {
        $this->tokenExpiration = $tokenExpiration;

        return $this;
    }

    /**
     * Set otpExpiration
     *
     * @param float $otpExpiration
     * @return $this
     */
    public function setOtpExpiration($otpExpiration)
    {
        $this->otpExpiration = $otpExpiration;

        return $this;
    }

    /**
     * Set attempts
     *
     * @param int $attempts
     * @return $this
     */
    public function setAttempts($attempts)
    {
        $this->attempts = $attempts;

        return $this;
    }

    // --- Attributes

    public function setRedirectUrl($url)
    {
        $this->user->getSession()->set('tfa.redirect', $url);

        return $this;
    }

    public function getRedirectUrl()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $redirect = $this->request->get('redirect') ?
            urldecode($this->request->get('redirect')) :
            ($this->user->getSession()->has('tfa.redirect') ?
                $this->user->getSession()->get('tfa.redirect') :
                $redirect = $this->app[ 'url_generator' ]->generate('index', [], UrlGenerator::ABSOLUTE_URL));

        // Preserve for the next time
        if (!$this->user->getSession()->has('tfa.redirect')) {
            $this->setRedirectUrl($redirect);
        }

        return $redirect;
    }

    public function clearRedirectUrl()
    {
        $this->user->getSession()->remove('tfa.redirect');

        return $this;
    }

    public function setVerificationForced()
    {
        $this->user->getSession()->set('tfa.requiredVerification', 1);

        return $this;
    }

    public function isVerificationForced()
    {
        return !!$this->user->getSession()->get('tfa.requiredVerification');
    }

    public function clearVerificationForced()
    {
        $this->user->getSession()->remove('tfa.requiredVerification');

        return $this;
    }

    public function setUserKey($userKey)
    {
        $this->user->getSession()->set('tfa.userKey', $userKey);

        return $this;
    }

    public function getUserKey()
    {
        return $this->user->getId() ? $this->user->getId() : $this->user->getSession()->get('tfa.userKey');
    }

    public function clearUserKey()
    {
        $this->user->getSession()->remove('tfa.userKey');

        return $this;
    }

    // --- Logic

    public function isValidationNeeded()
    {
        return
            // Existent user
            (
                $this->user->isAuthorized() and
                !$this->isValidated($this->user->getId())
            ) or
            // A new user (has no account yet)
            (
                $this->isVerificationForced()
            );
    }

    public function isValidated($userId)
    {
        $tokenExists = (!empty($_COOKIE[$this->cookieName]) and $this->generateToken($userId) == $_COOKIE[$this->cookieName]);
        $granted = false;

        switch ($this->strategy) {
            case self::VALIDATE_STRATEGY_IP_GRANTS:
                if ($tokenExists) {
                    $granted = true;
                    break;
                }

                // Check by IP
                $granted = $this->api->mta()->isUserIpTrusted($this->request->getClientIp(), $userId)['result'];

                break;

            case self::VALIDATE_STRATEGY_IP_REQUIRED:
                $granted = $this->api->mta()->isUserIpTrusted($this->request->getClientIp(), $userId)['result'];

                break;
        }

        // Check if user is exceptional
        if (!$granted) {
            $granted = $this->api->mta()->isUserWhitelisted($userId)['result'];
        }

        // User is authorized, create token
        if ($granted and !$tokenExists) {
            $this->setTFAValidated($userId);
        }

        return $granted;
    }

    public function getToken()
    {
        return !empty($_COOKIE[$this->cookieName]) ? $_COOKIE[$this->cookieName] : false;
    }

    public function generateTFAOTP($userId)
    {
        $length = 6;
        $code = substr( str_shuffle('0123456789'), 0, $length);

        $this->api->mta()->storeOtp('tfa_email', $code, $this->otpExpiration, $this->attempts, $userId);

        return $code;
    }

    public function isOTPGenerated($userId)
    {
        $response = $this->api->mta()->isOtpExists('tfa_email', $userId);

        return $response['result'] ? $response['otp'] : false;
    }

    public function validateTFAOTP($userId, $otp, $decrement = true)
    {
        try {
            $validated = !!$this->api->mta()->getOtp('tfa_email', true, $otp, true, $userId)['otp'];
        }
        catch (BadRequestException $e) {
            $validated = false;
        }

        // Cleanup if code is match
        if ($validated) {
            $this->api->mta()->deleteOtp('tfa_email', $userId);
        }
        elseif ($decrement and $this->isOTPGenerated($userId)) {
            $this->api->mta()->decrementOtpAttempts('tfa_email', $userId);
        }

        return $validated;
    }

    public function isCodeExist($userId, $otp)
    {
        try {
            return !!$this->api->mta()->getOtp('tfa_email', false, $otp, false, $userId)['otp'];
        }
        catch (BadRequestException $e) {
            return false;
        }
    }

    public function setTFAValidated($userId)
    {
        $token = $this->generateToken($userId);
        setcookie($this->cookieName, $token, time() + $this->tokenExpiration, '/');

        // Save user ip
        $this->api->mta()->upsertUserIp($this->request->getClientIp(), $userId);

        $this->clearVerificationForced()->clearRedirectUrl()->clearUserKey();

        return $token;
    }

    public function signToken($token)
    {
        return md5($token . $this->sign);
    }

    public function generateToken($userId)
    {
        return md5(str_replace('{userId}', $userId, $this->token));
    }
}
