<?php

/**
 * UserIdentity represents the data needed to identity a user.
 * It contains the authentication method that checks if the provided
 * data can identity the user.
 * @method string updatePassword(string $email, string $password)
 */
use Rych\OTP\Seed;
use Rych\OTP\TOTP;

class CiiUserIdentity extends CUserIdentity
{
	/**
	 * Constant variable for defining lockout status
	 * @var const ERROR_PASSWORD_LOCKOUT
	 */
	const ERROR_PASSWORD_LOCKOUT = 3;

    /**
     * Constant variable for requiring visibility of two factor auth code
     * @var const REQUIRE_TWO_FACTOR_AUTH
     */
    const REQUIRE_TWO_FACTOR_AUTH = 4;

    /**
     * Constant variable for indicating a two factor auth code is bad
     * @var const INVALID_TWO_FACTOR_AUTH
     */
    const INVALID_TWO_FACTOR_AUTH = 5;
    
	/**
	 * The Application to use for API generation
	 * @var string
	 */
	public $app_name = NULL;

    /**
     * Two factor authentication token
     * @var string
     */
    protected $twoFactorCode = false;
    
	/**
	 * The user id
	 * @var int $_id
	 */
	protected $_id;

	/**
	 * Whether or not to allow login or not
	 * Possibly should be renamed to doLogin
	 * @var boolean $force
	 */
	protected $force = false;

    /**
     * The Users ActiveRecord record response
     * @var Users $_user
     */
    protected $_user;

    /**
     * The bcrypt password hash cost
     * @var int $_cost
     */
    private $_cost;

    /**
     * The number of password attempts
     * @var int $_attempts
     */
    private $_attempts;

    /**
     * Constructor overload for two factor code
     */
    public function __construct($username, $password, $twoFactorCode=false)
    {
        parent::__construct($username, $password);
        $this->twoFactorCode = $twoFactorCode;
    }
    
    /**
	 * Gets the id for Yii::app()->user->id
	 * @return int 	the user id
	 */
	public function getId()
	{
    	return $this->_id;
	}

    /**
     * Retrieves the user's model, and presets an error code if one does not exists
     * @return Users $this->_user
     */
    public function getUser()
    {
        if ($this->_user !== NULL)
            return $this->_user;
        else
		    $this->_user = Users::model()->findByAttributes(array('email'=>$this->username));

        if ($this->_user == NULL)
            $this->errorCode = YII_DEBUG ? self::ERROR_USERNAME_INVALID : self::ERROR_UNKNOWN_IDENTITY;

        return $this->_user;
    }

    /**
     * Handles setting up all the data necessary for the workflow
     * @return void
     */
    private function setup($force = false)
    {
        // Set a default error code to indicate if anything changes
        $this->errorCode = NULL;

        // Indicate if this is a forced procedure
        $this->force = $force;

        // Load the current user
        $this->getUser();

        // Get the current bcrypt cost
		$this->_cost = Cii::getBcryptCost();

        // Preload the number of password attempts. As of 1.10.0
        $this->getPasswordAttempts();

        return;
    }

    /**
     * Retrieves the number of password login attempts so that we can automatically lock users out of they attempt a brute force attack
     * @return UserMetadata
     */
    protected function getPasswordAttempts()
    {
        if ($this->_user == NULL)
            return false;

        $this->_attempts = UserMetadata::model()->getPrototype('UserMetadata', array(
                'user_id' => $this->getUser()->id,
                'key' => 'passwordAttempts'
            ), array(
                'user_id' => $this->getUser()->id,
                'key' => 'passwordAttempts',
                'value' => 0
        ));

        return $this->_attempts;
    }

    /**
     * Validates the users two factor authentication code
     * @return boolean
     */
    private function validateTwoFactorCode()
    {
        $otpSeed = $this->getUser()->getMetadataObject('OTPSeed', false)->value;

        $otplib = new TOTP($otpSeed);

        return $otplib->validate($this->twoFactorCode);
    }

	/**
	 * Authenticates the user into the system
	 * @param  boolean $force 				Whether or not to bypass the login process (passwordless logins from HybridAuth)
	 * @return UserIdentity::$errorCode 	The error code associated to the login process
	 */
	public function authenticate($force=false)
	{
        // Setup everything first
		$this->setup($force);

        // If something bad happened during the setup process, immediately bail with the error code
        if ($this->errorCode != NULL)
            return !$this->errorCode;

        // Validate the users password
        $this->validatePassword();

        // If this user has 5 or more failed password attempts
        if ($this->_attempts->value >= 5)
        {
            // And if they the updated time is still more than 15 minutes ago
            if ((strtotime($this->_attempts->updated) + strtotime("+15 minutes")) >= time())
            {
                // Resave the attempts model so that the updated time is reset
                $this->_attempts->save();

                // Throw an unknown identity error
                $this->errorCode = self::ERROR_UNKNOWN_IDENTITY;
            }
            else
            {
                // Automatically re-adjust the lockout time if this has passed.
                $this->_attempts->value = 0;
                $this->_attempts->save();
            }
        }

        // If the user needs two factor authentication
        if ($this->getUser()->needsTwoFactorAuth())
        {
            // If the code isn't supplied, throw an error
            if ($this->twoFactorCode === false)
               $this->errorCode = self::REQUIRE_TWO_FACTOR_AUTH;
            else
            {
                // If the 2fa code is supplied, verify it
                if (!$this->validateTwoFactorCode())
                    $this->errorCode = self::INVALID_TWO_FACTOR_AUTH;
            } 
        }

        // At this point, we should should know if the validation has succeeded or not. If the errorCode has been altered, immediately bail
        if ($this->errorCode != NULL)
            return !$this->errorCode;
        else
            $this->setIdentity();

        return !$this->errorCode;
    }

    /**
     * Do some basic password validation
     *
     */
    public function validatePassword()
    {
        // If the user is banned, inactive, or has a pending invitation, abort
        if ($this->_user->status == Users::BANNED || $this->_user->status == Users::INACTIVE || $this->_user->status == Users::PENDING_INVITATION)
            $this->errorCode = self::ERROR_UNKNOWN_IDENTITY;
        else if (!$this->password_verify_with_rehash($this->password, $this->_user->password))
            $this->errorCode = YII_DEBUG ? self::ERROR_PASSWORD_INVALID : self::ERROR_UNKNOWN_IDENTITY;

        if ($this->errorCode === 100 || $this->errorCode === NULL || $this->errorCode === self::REQUIRE_TWO_FACTOR_AUTH)
            return true;

        return false;
    }

    /**
     * Sets the identity attributes
     * @return void
     */
    protected function setIdentity()
    {
        $this->_id 					  = $this->_user->id;
        $this->setState('email', 		$this->_user->email);
        $this->setState('username', 	$this->_user->username);

        // TODO: Replace all instances of displayName with username
        $this->setState('displayName',  $this->_user->username);
        $this->setState('status', 		$this->_user->status);
        $this->setState('role', 		$this->_user->user_role);
        $this->setstate('apiKey',       $this->generateAPIKey());

        $this->errorCode = self::ERROR_NONE;

        return;
    }

    /**
     * Generates a new API key for this application
     * @return string
     */
    protected function generateApiKey()
    {
        // Load the hashing factory
        $factory = new CryptLib\Random\Factory;

        $meta = UserMetadata::model()->getPrototype('UserMetadata', array(
                'user_id' => $this->getUser()->id,
                'key' => 'api_key' . $this->app_name
            ), 
            array(
                'user_id' => $this->getUser()->id,
                'key' => 'api_key' . $this->app_name
        ));

        $meta->value   = $factory->getLowStrengthGenerator()->generateString(16);

        if ($meta->save())
            return $meta->value;

        throw new CHttpException(500, Yii::t('ciims.models.LoginForm', 'Unable to create API key, please try again.'));
    }

    /**
     * https://gist.github.com/nikic/3707231
     * Checks if a password is valid against our database
     * @param string $password_hash     The password to check against
     * @return boolean
     */
    protected function password_verify_with_rehash($password_hash, $bcryt_hash)
    {
        if (!password_verify($password_hash, $bcryt_hash))
           return false;

        if (password_needs_rehash($bcryt_hash, PASSWORD_BCRYPT, array('cost' => $this->_cost)))
        {
            // Update the hash in the db
            $this->getUser()->password = $this->password;
            return $this->getUser()->save();
        }

        // Otherwise return true
        return true;
    }
}
