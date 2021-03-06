<?php

class CiiFileUpload
{

	// The Content ID
	private $_id = NULL;

	// Whether or not we should promote the content or not
	private $_promote = false;

	// The end response
	private $_response = NULL;

	// The response object
	public $_result = array();

	/**
	 * Constructor for handling uploads
	 * @param int $id      The content id
	 * @param int $promote Whether or not the image should be promoted or not
	 */
	public function __construct($id, $promote=0)
	{
		$this->_id = $id;
		$this->_promote = $promote;
	}

	/**
     * Handles all uploads
     */
	public function uploadFile()
	{
        if (!isset(Yii::app()->params['ciims_plugins']['upload']))
        {
            Yii::import('cii.utilities.CiiFileUploader');
            $className = 'CiiFileUploader';
        }
        else
            $className = Yii::app()->params['ciims_plugins']['upload']['class'];

        $config = isset(Yii::app()->params['ciims_plugins']['upload']['options']) ? Yii::app()->params['ciims_plugins']['upload']['options'] : array();
        $class = new $className($config);
        $this->_result = $class->upload();
        $this->_response = $this->_handleResourceUpload($this->_result['url']);

        return $this->_response;
	}
    
    /**
     * Generic function to handle all resource uploads
     * @param  string $value    The value that should be assigned to $meta->value
     * @return string
     */
    private function _handleResourceUpload($value)
    {
      if (Cii::get($this->_result,'success', false) == true)
        {
            $meta = ContentMetadata::model()->findbyAttributes(array('content_id' => $this->_id, 'key' => $this->_result['filename']));

            if ($meta == NULL)
                $meta = new ContentMetadata;

            $meta->content_id = $this->_id;
            $meta->key = $this->_result['filename'];
            $meta->value = $value;
            if ($meta->save())
            {
                if ($this->_promote)
                    $this->_promote($this->_result['filename']);
                $this->_result['filepath'] = $value;
                return htmlspecialchars(CJSON::encode($this->_result), ENT_NOQUOTES);
            }
            else
                throw new CHttpException(400,  Yii::t('ciims.misc', 'Unable to save uploaded image.'));
        }
        else
            throw new CHttpException(400, $this->_result['error']);
    }


    /**
     * Promotes an image to blog-image
     * @param string $key   The key to be promoted
     * @return boolean      If the change was sucessfuly commited
     */
    private function _promote($key = NULL)
    {
        $promotedKey = 'blog-image';

        // Only proceed if we have valid date
        if ($this->_id == NULL || $key == NULL)
            return false;
        
        $model = ContentMetadata::model()->findByAttributes(array('content_id' => $this->_id, 'key' => $key));
        
        // If the current model is already blog-image, return true (consider it a successful promotion, even though we didn't do anything)
        if ($model->key == $promotedKey)
            return true;
        
        $model2 = ContentMetadata::model()->findByAttributes(array('content_id' => $this->_id, 'key' => $promotedKey));
        if ($model2 === NULL)
        {
            $model2 = new ContentMetadata;
            $model2->content_id = $this->_id;
            $model2->key = $promotedKey;
        }
        
        $model2->value = $model->value;
        
        if (!$model2->save())
            return false;
        
        return true;
    }
}
