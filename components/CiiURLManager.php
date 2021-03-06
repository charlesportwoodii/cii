<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This class provides functionality for a dynamic ruleset, allowing us to inject routing rules on the fly via the admin
 * panel rather than relying solely upon the main.php array
 */
class CiiURLManager extends CUrlManager
{
	/**
	 * Whether or not we should cache url rules
	 * Override in main.php
	 * @var boolean
	 */
	public $cache = true;

	/**
	 * This is where our defaultRules are stored. This takes the place of the rules array in main.php
	 * This has been moved to here so that we can dynamically update rules without having to worry
	 * making sure the client updates their main.php file on updates.
	 * @var array
	 */
	public $defaultRules = array(
        '/'                                     => '/content/list',
        '/sitemap.xml'                          => '/site/sitemap',
        '/search/<page:\d+>'                    => '/site/search',
        '/search'                               => '/site/search',
        '/hybridauth/<provider:\w+>'            => '/hybridauth',
        '/contact'                              => '/site/contact',
        '/blog/<page:\d+>'                      => '/content/list',
        '/blog'                                 => '/content/list',
        '/activation/<id:\w+>'                  => '/site/activation',
        '/activation'                           => '/site/activation',
        '/emailchange/<key:\w+>'                => '/site/emailchange',
        '/emailchange'                          => '/site/emailchange',
        '/resetpassword/<id:\w+>'               => '/site/resetpassword',
        '/resetpassword'                        => '/site/resetpassword',
        '/forgot'                               => '/site/forgot',
        '/register'                             => '/site/register',
        '/register-success'                     => '/site/registersuccess',
        '/login'                                => '/site/login',
        '/logout'                               => '/site/logout',
        '/profile/edit'                         => '/profile/edit',
        '/profile/resend'                       => '/profile/resend',
        '/profile/<id:\w+>/<username:\w+>'  	=> '/profile/index',
        '/profile/<id:\w+>'                     => '/profile/index',
        '/acceptinvite'                         => '/site/acceptinvite',
        '/acceptinvite/<id:\w+>'                => '/site/acceptinvite',
        '/error/<code:\w+>'                     => '/site/error'
    );

	/**
	 * Overrides processRules, allowing us to inject our own ruleset into the URL Manager
	 * Takes no parameters
	 **/
	protected function processRules()
	{
		$this->cache = !YII_DEBUG;
		// Generate the clientRules
		$this->rules = $this->cache ? Yii::app()->cache->get('CiiMS::Routes') : array();
		if ($this->rules == false || empty($this->rules))
		{
			$this->rules = array();
			$this->rules = $this->generateClientRules();
			$this->rules = CMap::mergearray($this->addRssRules(), $this->rules);
			$this->rules = CMap::mergeArray($this->addModuleRules(), $this->rules);

			Yii::app()->cache->set('CiiMS::Routes', $this->rules);
		}

		// Append our cache rules BEFORE we run the defaults
		$this->rules['<controller:\w+>/<action:\w+>/<id:\d+>'] = '<controller>/<action>';
		$this->rules['<controller:\w+>/<action:\w+>'] = '<controller>/<action>';

		return parent::processRules();
	}

	/**
	 * Adds rules from the module/config/routes.php file
	 * @return
	 */
	private function addModuleRules()
	{
		// Load the routes from cache
		$moduleRoutes = array();
		$directories = glob(Yii::getPathOfAlias('application.modules') . '/*' , GLOB_ONLYDIR);

		foreach ($directories as $dir)
		{
			$routePath = $dir .DS. 'config' .DS. 'routes.php';
			if (file_exists($routePath))
			{
				$routes = require_once($routePath);
				// Unit tests are failing here for some reason
				if (!is_array($routes))
					continue;

				foreach ($routes as $k=>$v)
				$moduleRoutes[$k] = $v;
			}
		}

		return $moduleRoutes;
	}

	/**
	 * Generates RSS rules for categories
	 * @return array
	 */
	private function addRSSRules()
	{
		$categories = Categories::model()->findAll();
		foreach ($categories as $category)
		$routes[$category->slug.'.rss'] = "categories/rss/id/{$category->id}";

		$routes['blog.rss'] = '/categories/rss';
		return $routes;
	}

	/**
	 * Generates client rules, depending on if we want to handle rendering client side or server side
	 * @return array
	 */
	private function generateClientRules()
	{
		$theme;
		$themeName = Cii::getConfig('theme', 'default');
		if (file_exists(Yii::getPathOfAlias('base.themes.') . DS . $themeName .  DS . 'Theme.php'))
		{
			Yii::import('base.themes.' . $themeName . '.Theme');
			$theme = new Theme;
		}

		// Generate the initial rules
		$rules = CMap::mergeArray($this->defaultRules, $this->rules);

		// If the Theme has requested to handle routing client side, allow it to do so
		// Otherwise generate the URL rules for Yii to handle it
		if ($theme->noRouting !== false)
			return $this->routeAllRulesToRoot();
		else
			return CMap::mergeArray($rules, $this->generateRules());
	}

	/**
	 * Eraseses all the existing rules and remaps them to the index
	 * @return array
	 */
	private function routeAllRulesToRoot()
	{
		$rules = $this->rules;
		foreach ($this->defaultRules as $k=>$v)
			$rules[$k] = '/content/nr';

		foreach ($rules as $k=>$v)
			$rules[$k] = '/content/nr';

		$rules['/sitemap.xml'] = '/site/sitemap';
		$rules['/login'] = '/site/login';
		$rules['/'] = '/content/nr';
		$this->rules = CMap::mergeArray($this->addRSSRules(), $this->rules);
		$this->rules = CMap::mergeArray($this->addModuleRules(), $this->rules);
		return $rules;
	}

	/**
	 * Wrapper function for generation of content rules and category rules
	 * @return array
	 */
	private function generateRules()
	{
		return CMap::mergeArray($this->generateContentRules(), $this->generateCategoryRules());
	}

	/**
	 * Generates content rules
	 * @return array
	 */
	private function generateContentRules()
	{
		$rules = array();
		$criteria = Content::model()->getBaseCriteria();
		$content = Content::model()->findAll($criteria);
		foreach ($content as $el)
		{
			if ($el->slug == NULL)
				continue;

			$pageRule = $el->slug.'/<page:\d+>';
			$rule = $el->slug;


			$pageRule = $el->slug . '/<page:\d+>';
			$rule = $el->slug;

			$rules[$pageRule] = "content/index/id/{$el->id}/vid/{$el->vid}";
			$rules[$rule] = "content/index/id/{$el->id}/vid/{$el->vid}";
		}

		return $rules;
	}

	/**
	 * Generates category rules
	 * @return array
	 */
	private function generateCategoryRules()
	{
		$rules = array();
		$categories = Categories::model()->findAll();
		foreach ($categories as $el)
		{
			if ($el->slug == NULL)
				continue;

			$pageRule = $el->slug.'/<page:\d+>';
			$rule = $el->slug;

			$pageRule = $el->slug . '/<page:\d+>';
			$rule = $el->slug;

			$rules[$pageRule] = "categories/index/id/{$el->id}";
			$rules[$rule] = "categories/index/id/{$el->id}";
		}

		return $rules;
	}
}
