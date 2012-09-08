<?php if( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Sk Reword Module  
 *
 * @package			SK Reword
 * @version			1.0.0
 * @author			Jean-Francois Paradis - https://github.com/skaimauve
 * @copyright 		Copyright (c) 2012 Jean-Francois Paradis
 * @license 		MIT License - please see LICENSE file included with this distribution
 * @link			http://github.com/skaimauve/reword
 */

$plugin_info = array(
	'pi_name'			=> 'sk_reword',
	'pi_version'		=> '1.0.0',
	'pi_author'			=> 'Jean-Francois Paradis',
	'pi_description'	=> 'Translates a string or a field',
	'pi_usage'			=> 'http://github.com/skaimauve/reword'
);

class Sk_reword {

	protected $EE;
	protected $RR; // We use a singleton to prevent multiple initilization.
	
	public $return_data;

	// --------------------------------------------------------------------

	public function __construct()
	{
		$this->EE =& get_instance();
		$this->RE =& Sk_reword_engine::get_instance(); 

		// Parameters

		$set = $this->EE->TMPL->fetch_param('set');
		$text = $this->EE->TMPL->fetch_param('text');

		$string = $this->EE->TMPL->fetch_param('string');

		$date = $this->EE->TMPL->fetch_param('date');
		$format = $this->EE->TMPL->fetch_param('format');

		// Processing
		
		// 1. Lookup $text in dictionary, similar to translate() in Wordpress.

		if ($text) 
		{
			$result = $this->RE->translate($text, $set);
			$this->return_data = empty($result) ? $text : $result;
		}

		// 2a. Replace %s in $text with the content of $string. 
		// 2b. Also replace multiple occurences of %s with the values in $string separated by '|'. 
		
		if ($string) 
		{
			// Handle multiple values	
			$string = explode('|', $string);
			$search = array_fill(0,count($string),'%s');

			$this->return_data = isset($this->return_data) ? 
				$this->mb_str_replace_limit($search, $string, $this->return_data, 1) : 
				$string;
		}

		// 3. Lookup $format in dictionary, then apply it to $date (defaut now()), and then replace %d in $text.

		if ($format) 
		{
			// Translate format
			$result = $this->RE->translate($format, $set);
			$format = empty($result) ? $format : $result;

			// Convert format (see function mdate in CodeIgniter Date helper)
			$format = str_replace('%\\', '', preg_replace("/([a-z]+?){1}/i", "\\\\\\1", $format));

			// Apply format
			$date = empty($date) ? date($format) : date($format, $date);
		
			// Insert
			$this->return_data = isset($this->return_data) ? 
				str_replace('%d', $date, $this->return_data) : 
				$date;
		}
	}

	// ===================================================================

	/**
	 * str_replace_limit
	 *
	 * Replace on strings allowing specification of a limit. 
	 */
	public function mb_str_replace_limit($search, $replace, $subject, $limit, &$count = null)
	{
	    $count = 0;
	    if ($limit < 1) return $subject;

		if (!is_array($search)) $search = array($search); 
		if (!is_array($replace)) $replace = array($replace); 

	    for ($i = 0; $i < count($search); $i++) {
		    $search_item = $search[$i];

		    for ($j = 0; $j < $limit; $j++) {
		        $position = mb_strpos($subject, $search_item);

		        if ($position !== FALSE) {
		        	$replace_item = isset($replace[$i]) ? $replace[$i] : end($replace);
			        $subject = mb_substr($subject, 0, $position) . $replace_item . mb_substr($subject, $position + mb_strlen($search_item));
			        $count++;
		        }
			}
		}
		
		return $subject;
	}

}

// --------------------------------------------------------------------

class Sk_reword_engine {

	const default_idiom = 'english';
	const default_set = 'all';

	const path_idioms = 'sk_reword/languages.php';
	const path_languages = 'languages/';

	const cookie_expiration = 86500;

	protected $EE;

	protected $user_idiom = '';
	protected $user_idiom_code = '';

	protected $language_path;
	protected $language;
	protected $language_list;

	// --------------------------------------------------------------------

	/**
	 * constructor
	 *
	 * Required for singleton:
	 * - Prevent users of creating instances directly.
	 * - Allow subclasses to implement their own initialization code.
	 */
    protected function __construct() 
    {
		$this->EE =& get_instance();

		// Global config constant is supported (always add trailing '/')
		if ($this->EE->config->item('language_path'))
		{
			$this->language_path = $this->EE->config->item('language_path');
		}
		else
		{
			$this->language_path = PATH_THEMES.self::path_languages;
		}
    }

	// --------------------------------------------------------------------

	/**
	 * clone
	 *
	 * Required for singleton:
	 * - Prevent prevent anyone cloning an instance 
	 */
    final private function __clone() {}

	// --------------------------------------------------------------------

    /** 
     * get_instance
     *
	 * Required for singleton:
     * - Serve-up the singleton
     */
	final public static function get_instance()
	{
		static $instance;

		if (! isset($instance)) {
			$instance = new self;
		}
		return $instance;
	}

	// ===================================================================

	/**
	 * translate
	 *
	 * Fetch a specific line of text from the given set. If that fails, 
	 * return the original text.
	 */
	public function translate($text, $set)
	{
		$this->load_language($set);

		// Convert escaped characters
		$result = isset($this->language[$set][$text]) ? $this->language[$set][$text] : $text;			
		
		return stripslashes($result);		
	}

	// --------------------------------------------------------------------

	/**
	 * load_language
	 *
	 * Retrieve the given dictionary for the current user language 
	 */
	protected function load_language($set)
	{
		if ( ! isset($this->language[$set]))
		{
			$site 		= $this->EE->config->config['site_short_name'];
			$idiom		= $this->EE->security->sanitize_filename($this->get_user_idiom());
			$set_name	= empty($set) ? self::default_set : $this->EE->security->sanitize_filename($set);

			$file =  $this->language_path.$site.'/'.$idiom.'/lang.'.$set_name.'.php';

			if ( file_exists($file) )
			{
				include($file);
				$this->language[$set] = $lang;

			} else {

				$this->language[$set] = array();
			}
		}
	}

	// ===================================================================

	/**
	 * get_user_idiom
	 *
	 * Gets the current idiom or find it
	 */
	public function get_user_idiom()
	{	
		if (! $this->user_idiom) 
		{
			$this->user_idiom = $this->find_user_idiom();
		}

		return $this->user_idiom;
	}

	// --------------------------------------------------------------------

	/**
	 * get_user_idiom_code
	 *
	 * Gets the current idiom code, or convert the current idiom 
	 */
	public function get_user_idiom_code()
	{	
		if (! $this->user_idiom_code) 
		{
			$this->user_idiom_code = $this->idiom_to_code($this->get_user_idiom());
		}

		return $this->user_idiom_code;
	}
		
	// --------------------------------------------------------------------

	/**
	 * find_user_idiom
	 *
	 * Gets the current idiom
	 */
	protected function find_user_idiom()
	{	

		// 1. Always check URL first for new setting

		if ($this->EE->input->get('lang'))
		{
			// Code Igniter and EE rely on idiom, and not idiom code, so we convert
			$idiom = $this->code_to_idiom($this->EE->input->get('lang'));

			// Do we have a corresponding idiom?
			if ($idiom) 
			{
				// Set a cookie			
				$this->EE->functions->set_cookie('language', $idiom, self::cookie_expiration);
	
				return $idiom;
			}
		}
		
		// 2. Look in session (should never happen)

		if ($this->EE->session->userdata('language'))
		{
			return $this->EE->session->userdata('language');
		}

		// 3. Look in cookie

		if ($this->EE->input->cookie('language'))
		{
			return $this->EE->input->cookie('language');
		}
		
		// 4. Use the browser settings
		
		if ($this->EE->input->server('HTTP_ACCEPT_LANGUAGE')) 
		{
			// Code Igniter and EE rely on idiom, and not idiom code, so we extract and convert
			$idiom = $this->accept_language_to_idiom($this->EE->input->server('HTTP_ACCEPT_LANGUAGE'));

			// Do we have a corresponding idiom?
			{ 
				// Set a cookie so we don't have to parse the header again			
				$this->EE->functions->set_cookie('language', $idiom, self::cookie_expiration);
	
				return $idiom;
			}
		}

		// 5. Use configuration default

		if ($this->EE->config->item('deft_lang') != '')
		{
			return $this->EE->config->item('deft_lang');
		}

		// 6. Use class default

		return self::default_idiom;
 	}

	// --------------------------------------------------------------------

	/**
	 * accept_language_to_idiom
	 *
	 * Extract language from header sent by browser (LTR, does not support quality parameter).
	 * Return FALSE if not found.
	 */

	public function accept_language_to_idiom($http_accept_language)
	{
		foreach (explode(',', $http_accept_language) as $lang) {

		    if (preg_match('/^([a-zA-Z]{2,8})(-[a-zA-Z]{2,8})?(;q=\d\.\d)?$/', trim($lang), $matches)) {

			    $idiom = $this->code_to_idiom($matches[1]);

			    if ($idiom) {
			    	return $idiom;
				}

		    }
		}
		
		return FALSE;
	}

	// ===================================================================

	/**
	 * idiom_to_code
	 *
	 * Convert a language to a language code using the configured pairs.
	 * Return FALSE if not found.
	 */
	public function idiom_to_code($idiom)
	{
		$this->load_idioms();

		return isset($this->language_list[$idiom]) ? $this->language_list[$idiom] : FALSE;					
	}


	// --------------------------------------------------------------------

	/**
	 * code_to_idiom
	 *
	 * Convert a language code to a language string using the configured pairs.
	 * Return FALSE if not found.
	 */
	public function code_to_idiom($idiom_code)
	{
		$this->load_idioms();
		
		// Searches the values and returns the corresponding key
		$idiom = array_search($idiom_code, $this->language_list, TRUE);

		return ( $idiom !== FALSE ) ? $idiom : FALSE;					
	}

	// --------------------------------------------------------------------

	/**
	 * load_idioms
	 *
	 * Retrieve the idiom list to convert language code to a language string
	 */
	protected function load_idioms()
	{
		if ( ! isset($this->language_list))
		{
			$file = PATH_THIRD.self::path_idioms;

			if ( file_exists($file) ) 
			{
				include($file);			
				$this->language_list = $languages;
	
			} else {
	
				$this->language_list = array();
			}
		}
	}

}
// END CLASS

/* End of File: pi.sk_reword.php */
/* Location: ./system/expressionengine/third_party/sk_reword/pi.sk_reword.php */


