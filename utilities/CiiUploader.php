<?php

/**
 * Uploader class for CiiMS. All classes that do file uploads should extend from this class
 */
class CiiUploader
{
	/**
	 * Initialized config
	 */
	private $_core = array(
		'allowedExtensions' => array(
			'png',
	        'jpeg',
	        'jpg',
	        'gif',
	        'bmp'
		),
		'sizeLimit' => 10485760
	);

	/**
	 * Config options
	 */
	private $_config;

	/**
	 * The file
	 */
	public $file;

	/**
	 * __constructor
	 * @param $config
	 */
	public function __construct($config=array())
	{
		$this->_config = CMap::mergeArray($this->_core, $config);
		$this->checkServerSettings();

		if (Cii::get($_FILES, 'file') !== NULL)
           $this->file = new CiiFile();

       $this->verifyFile();
	}

	/**
	 * __getter
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		if (isset($this->_config[$name]))
			return $this->_config[$name];

		return NULL;
	}

	/**
	 * Verifies that a file is valid
	 * @return array
	 */
	public function verifyFile()
	{
		if (!$this->file)
            return array('error' => Yii::t('ciims.misc', 'No files were uploaded.'));
        
        $size = $this->file->size;

        if ($size == 0) 
            return array('error' => Yii::t('ciims.misc', 'File is empty'));
        
        if ($size > $this->sizeLimit) 
            return array('error' => Yii::t('ciims.misc', 'File is too large'));
        
        $pathinfo = pathinfo($this->file->name);
        $filename = $pathinfo['filename'];

        //$filename = md5(uniqid());
        $ext = $pathinfo['extension'];

        if(!in_array(strtolower($ext), $this->allowedExtensions))
        {
            $these = implode(', ', $this->allowedExtensions);
            return array('error' => Yii::t('ciims.misc', "File has an invalid extension, it should be one of {{these}}.", array('{{these}}' => $these)));
        }

        return array('success' => $filename);
	}

	/**
	 * Checks that the server size settings are implemented appropriatly
	 * @return boolean
	 */
	private function checkServerSettings()
    {
        $postSize = $this->toBytes(ini_get('post_max_size'));
        $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));

		if ($postSize < $this->sizeLimit || $uploadSize < $this->sizeLimit)
		{
			$size = max(1, $this->sizeLimit / 1024 / 1024) . 'M';
			throw new CException(Yii::t('ciims.misc', 'Increase post_max_size and upload_max_filesize to {{size}}', array('{{size}}' => $size)));
		}

		return true;
    }

	/**
	 * Converts ini settings to an integer
	 * @param string $str 	The file size to check
	 * @return int
	 */
	private function toBytes($str)
	{
		$val = trim($str);
		$last = strtolower($str[strlen($str)-1]);
		switch($last)
		{
			case 'g': $val *= 1024;
			case 'm': $val *= 1024;
			case 'k': $val *= 1024;
		}
		return $val;
	}
}