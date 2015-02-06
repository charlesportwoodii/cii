<?php

Yii::import('cii.utilities.CiiUploader');
class CiiFileUploader extends CiiUploader
{
    /**
    * Returns array('success'=>true) or array('error'=>'error message')
    * @param string $uploadDirectory
    */
    public function upload()
    {
        $uploadDirectory = Yii::getPathOfAlias('webroot.uploads') . '/';

        if (!is_writable($uploadDirectory))
            return array('error' => Yii::t('ciims.misc', "{{dir}} Server error. Upload directory isn't writable.", array('{{dir}}' => $uploadDirectory)));

        $check = $this->verifyFile();

        if (isset($check['error']))
            return $check;

        $filename = $check['success'];
        $fullFileName = $filename.'.'.$this->file->getExtension();

        // don't overwrite previous files that were uploaded
        while (file_exists($uploadDirectory . $fullFileName))
            $fullFileName = $filename.rand(10, 99).'.'.$this->file->getExtension();

        if ($this->file->save($uploadDirectory . $fullFileName))
            return array('success' => true, 'filename' => $fullFileName, 'url' => Yii::app()->baseUrl . '/uploads/'.$fullFileName);
        
        return array('error'=> Yii::t('ciims.misc', 'Could not save uploaded file. The upload was cancelled, or server error encountered'));
    }
}
