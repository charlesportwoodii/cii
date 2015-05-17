<?php

class Cii
{

	/**
	 * Checks for the existance of an item at a given array index and returns that object if it exists
	 * @param array $array 	 The array to check
	 * @param mixed $item	 The indicie to check against
	 * @param mixed $default The default return value
	 * @return mixed array index or default
	 */
	public static function get($array=NULL, $item=NULL, $default=NULL)
	{
		if ($array === NULL)
			return isset($default) ? $default : $item;

		if (is_object($array) && isset($array->$item))
            return $array->$item;

        if (is_array($array))
            return isset($array[$item]) && !empty($array) ? $array[$item] : $default;

		if (!is_array($array))
			return isset($array) ? $array : $item;

		if (isset($array[$item]) && !empty($array))
			return $array[$item];

		return $default;
	}

    /**
     * Gets a configuration value from CiiMS::Configuration
     * @param  string $key     The key we want to retrieve from Configuration
     * @param  mixed  $default The default value to return if key is not found
     * @return mixed           The value from Config, or default
     */
    public static function getConfig($key, $default=NULL, $prefix='settings_', $force=false)
    {
        $cache = Yii::app()->cache->get($prefix.$key);

        if ($cache === false || $cache == NULL || $force === true)
        {
            $data = Yii::app()->db->createCommand('SELECT value FROM configuration AS t WHERE t.key = :key')
                                   ->bindParam(':key', $key)
                                   ->queryAll();

            if (!isset($data[0]['value']))
                $cache = $default;
            else
                $cache = $data[0]['value'];

            Yii::app()->cache->set($prefix.$key, $cache);
        }

        return $cache;
    }

    /**
     * Override control file
     * @return array
     */
    public static function getCiiConfig()
    {
        $config = Yii::getPathOfAlias('application.config.ciiconfig').'.php';
        if (file_exists($config))
            return require $config;

        return array();
    }

    /**
     * Sets the application language
     * @return string
     */
    public static function setApplicationLanguage()
    {
        $app = Yii::app();

        // If someone has hard-coded the app-language, return it instead
        if ($app->language != 'en_US')
            return $app->language;

        // If the language is set via POST, accept it
        if (php_sapi_name()  == 'cli')
            return $app->language;

        if (Cii::get($_POST, '_lang', false))
            $app->language = $_POST['_lang'];
        else if (isset($app->session['_lang']) && $app->session['_lang'] != NULL)
            $app->language = $app->session['_lang'];
        else
            $app->language = Yii::app()->getRequest()->getPreferredLanguage();


        Yii::app()->language = $app->session['_lang'] = $app->language;
        return $app->language;
    }

    /**
     * Gets a configuration value from user_metadata
     * @param  string $key     The key we want to retrieve from Configuration
     * @param  mixed  $default The default value to return if key is not found
     * @return mixed           The value from Config, or default
     */
    public static function getUserConfig($key, $default=NULL)
    {
        $uid = Yii::app()->user->id;
        $data = Yii::app()->db->createCommand('SELECT value FROM user_metadata AS t WHERE t.key = :key AND t.user_id = :id')
                              ->bindParam(':id', $uid)
                              ->bindParam(':key', $key)
                              ->queryAll();

        if (!isset($data[0]['value']))
            return NULL;

        return $data[0]['value'];
    }

    /**
     * Consolodates the finding of retrievinv the bcrypt_Cost
     * @param  integer $default The default bcrypt cost
     * @return int              The bcrypt cost
     */
    public static function getBcryptCost($default = 13)
    {
        return Cii::getConfig('bcrypt_cost', $default);
    }

	/**
	 * Provides methods to format a date throughout a model
     * By forcing all components through this method, we can provide a comprehensive
     * and consistent date format for the entire site
     * @param  mxied  $date   Likely a string in date format (of some kind)
     * @param  string $format The format we want to FORCE the dts to be formatted to
     *                        If this isn't supplied, we'll pull it from Cii::getConfig()
     * @return string
     */
	public static function formatDate($date, $format = NULL)
	{
        if ($format == NULL)
            $format = Cii::getConfig('dateFormat') . ' @ ' . Cii::getConfig('timeFormat');

        if ($format == ' @ ')
            $format = 'F jS, Y @ H:i UTC';

		return gmdate($format, $date);
	}

    /**
     * Automatically handles TimeAgo for UTC
     *
     * @param  mxied  $date   Likely a string in date format (of some kind)
     * @param  string $format The format we want to FORCE the dts to be formatted to
     *                        If this isn't supplied, we'll pull it from Cii::getConfig()
     * @return CHtml:tag span element
     */
    public static function timeago($date, $format = NULL)
    {
        Yii::app()->controller->widget('vendor.yiqing-95.YiiTimeAgo.timeago.JTimeAgo', array(
            'selector' => ' .timeago',
            'useLocale' => false,
            'settings' => array(
                'refreshMillis' => 60000,
                'allowFuture' => true,
                  'strings' => array(
                    'prefixAgo' => null,
                    'prefixFromNow' => null,
                    'suffixAgo' => "ago",
                    'suffixFromNow' => "from now",
                    'seconds' => "less than a minute",
                    'minute' => "about a minute",
                    'minutes' => "%d minutes",
                    'hour' => "about an hour",
                    'hours' => "about %d hours",
                    'day' => "a day",
                    'days' => "%d days",
                    'month' => "about a month",
                    'months' => "%d months",
                    'year' => "about a year",
                    'years' => "%d years",
                    'wordSeparator' => " ",
                    'numbers' => array()
                  )
            )
        ));

       return CHtml::tag(
            'span',
            array(
                'class'=>"timeago",
                'style'=>'text-decoration:none; cursor: default', // Post processing class application
                'rel'=>'tooltip',
                'data-original-title'=>Cii::formatDate($date, $format),
                'title'=>CTimestamp::formatDate('c', $date)
            ),
            Cii::formatDate($date, $format)
        );
    }

    /**
     * Returns an array of HybridAuth providers to be used by HybridAuth and other parts of CiiMS
     * @return array of HA Providers prettily formatted
     */
    public static function getHybridAuthProviders()
    {
        $providers = Yii::app()->cache->get('hybridauth_providers');

        if ($providers === false)
        {
            // Init the array
            $providers = array();

            // Query for the providors rather than using Cii::getConfig(). The SQL performance SHOULD be faster
            $response = Yii::app()->db->createCommand('SELECT REPLACE(`key`, "ha_", "") AS `key`, value FROM `configuration` WHERE `key` LIKE "ha_%"')->queryAll();
            foreach ($response as $element)
            {
                $k = $element['key'];
                $v = $element['value'];
                $data = explode('_', $k);
                $provider = $data[0];
                $key = $data[1];
                if ($provider == 'linkedin')
                    $provider = 'LinkedIn';

                $provider = ucwords($provider);

                if (!in_array($key, array('id', 'key', 'secret')))
                    $providers[$provider][$key] = $v;
                else
                    $providers[$provider]['keys'][$key] = $v;
            }

            Yii::app()->cache->set('hybridauth_providers', $providers);
        }

        return $providers;
    }

    /**
     * Retrieves the comment provider
     * @return string
     */
    public static function getCommentProvider()
    {
        if (Cii::getConfig('useDisqusComments'))
            return 'CiiDisqusComments';
        else if (Cii::getConfig('useDiscourseComments'))
            return 'CiiDiscourseComments';
        return 'CiiMSComments';
    }

	/**
	 * Encrypts a message
	 * @param string $message 		The message to encryept
	 * @return string $ciphertext	The encrypted message
	 */
	public static function encrypt($message, $key=NULL)
	{
		if ($key != NULL)
			Yii::log('warning', 'Use of the `$key` parameter is deprecated', 'cii');
		
		$key = self::getEncryptionKey();

		try {
            $ciphertext = Crypto::Encrypt($message, $key);
        } catch (CryptoTestFailedException $ex) {
			Yii::log('Cannot safely perfrom encryption', 'error', 'cii');	
			throw new Exception('Cannot safely perform encryption');
        } catch (CannotPerformOperationException $ex) {
			Yii::log('Cannot safely perfrom encryption', 'error', 'cii');	
			throw new Exception('Cannot safely perform encryption');
        }
		
		return $ciphertext;
	}

	/**
	 * Decrypts a message
	 * @param string $message 		The message to decryept
	 * @return string $decrypted	The decrypted message
	 */
	public static function decrypt($message, $key=NULL)
	{
		if ($key != NULL)
			Yii::log('warning', 'Use of the `$key` parameter is deprecated', 'cii');

        if (empty($messagE))
            return null;
            
		$key = self::getEncryptionKey();
	
		try {
            $decrypted = self::Decrypt($message, $key);
        } catch (InvalidCiphertextException $ex) {
			Yii::log('Ciphertext or key is invalid', 'error', 'cii');
			return null;
        } catch (CryptoTestFailedException $ex) {
			Yii::log('Ciphertext or key is invalid', 'error', 'cii');
			return null;
        } catch (CannotPerformOperationException $ex) {
			Yii::log('Ciphertext or key is invalid', 'error', 'cii');
			return null;
        }
		
		return $decrypted;
	}	

	/**
	 * Retrieves the encryption key from cache, or generations a new one in the instance one does not exists
	 * @return string
	 */
	public static function getEncryptionKey()
	{
		$key = self::getConfig('encryptionKey', NULL, 'settings_', true);
		if ($key == NULL || $key == "")
		{
				try {
					$key = Crypto::CreateNewRandomKey();
				} catch (CryptoTestFailedException $ex) {
					throw new CException('Encryption key generation failed');
				} catch (CannotPerformOperationException $ex) {
					throw new CException('Encryption key generation failed');
				}

				$config = new Configuration;
				$config->attributes = array(
					'key' => 'encryptionkey',
					'value' => bin2hex($key)
				);

				if (!$config->save())
					throw new CException('Encryption key generation failed');
		}

		return hex2bin(self::getConfig('encryptionKey', NULL, 'settings_', true));
	}

	/**
	 * Beause doing this all over the place is stupid...
     * @param  mixed $data     The data we want to debug
     * @param  bool $full_dump Whether or not we want the data outputted with var_dump or not
     */
	public static function debug($data, $full_dump = false)
	{
		echo '<pre class="cii-debug">';
		if ($full_dump)
			var_dump($data);
		else
			print_r($data);
		echo '</pre>';

		return;
	}

    /**
     * Returns the current CiiMS Version
     * @return string
     */
    public static function getVersion()
    {
        $version = Yii::app()->cache->get('ciims_version');
        if ($version === false)
        {
            $version = file_get_contents(Yii::getPathOfAlias('application.config.VERSION'));
            Yii::app()->cache->set('ciims_version', $version);
        }

        return $version;
    }

    /**
     * Generates a Safe Hash to use throughout CiiMS
     * @param  integer $length the hash length, default of 16
     * @return string
     */
    public static function generateSafeHash($length=16)
    {
        $factory = new CryptLib\Random\Factory;
        return preg_replace('/[^A-Za-z0-9\-]/', '', $factory->getLowStrengthGenerator()->generateString($length));
    }

    /**
     * Loads the user information
     */
    public static function loadUserInfo()
    {
        if (defined('CIIMS_INSTALL'))
            return;
        
        if (isset(Yii::app()->user))
        {
            // Load some specific CiiMS JS here
            $json = CJSON::encode(array(
                'email' 		=>  Cii::get(Yii::app()->user, 'email'),
                'token' 		=> Cii::getUserConfig('api_key', false),
                'role' 			=> Cii::get(Yii::app()->user, 'role'),
                'isAuthenticated' 	=> isset(Yii::app()->user->id),
                'debug' 		=> YII_DEBUG,
                'time' 			=> time(),
                'version' 		=> YII_DEBUG ? Cii::getVersion() : null,
                'language' 		=> Cii::setApplicationLanguage(),
                'hosted' 		=> defined('CII_CONFIG'),
		'api' 			=> Yii::app()->getBaseUrl(true) . '/api'
            ));

            Yii::app()->clientScript->registerScript('ciims', "
                localStorage.setItem('ciims', '$json');
            ", CClientScript::POS_HEAD);
        }
    }
}
