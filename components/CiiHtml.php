<?php

class CiiHtml extends CHtml
{

	public static $sriAlgorithms = array(
		'sha256',
		'sha384'
	);

	public static function scriptFile($url,array $htmlOptions=array())
	{
		$defaultHtmlOptions=array(
			'type'=>'text/javascript',
			'src'=>$url,
			'crossorigin' => 'anonymous'
		);

		$htmlOptions['integrity'] = self::getSRI($url);
		if ($htmlOptions['integrity'] === false)
			unset($htmlOptions['integrity']);

		$htmlOptions=array_merge($defaultHtmlOptions,$htmlOptions);

		return self::tag('script',$htmlOptions,'');
	}

	public static function cssFile($url,$media='')
	{
		return CHtml::linkTag('stylesheet','text/css',$url,$media!=='' ? $media : null);
	}

	public static function getSRI($url)
	{
		if (file_exists(Yii::getPathOfAlias('webroot') . $url))
		{
			$integrity = '';
			foreach (self::$sriAlgorithms as $algo)
				$integrity .= $algo . '-' . base64_encode(hash($algo, file_get_contents(Yii::getPathOfAlias('webroot') . $url), true)) . ' ';
			return $integrity;
		}

		return false;
	}
}
