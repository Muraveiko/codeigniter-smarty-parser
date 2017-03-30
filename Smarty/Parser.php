<?php

namespace Muraveiko\Smarty;
/**
 * Smarty_Parser
 *
 * Smarty templating for Codeigniter
 *
 * Отличие от исходного парсера: пришлось пожертвовать поддержкой _parse_pair()
 * @link      https://www.codeigniter.com/userguide3/libraries/parser.html
 *
 * @author    Antson
 * @license   MIT
 *
 * использованы части кода CI Smarty (Dwayne Charrington)
 */
class Parser extends \CI_Parser
{

    /**
     * @var string  Default extension of templates if one isn't supplied
     */
    public $template_ext = '.php';

    /**
     * @var string  for use with MX_Controller
     */
    protected $_module = '';

    /**
     * @var array
     */
    protected $_template_locations = array();

    /**
     * @var string|null Current theme location
     */
    protected $_current_path = NULL;

    /**
     * @var string   The name of the theme in use
     */
    protected $_theme_name = '';

    /**
     * Left delimiter character for pseudo vars
     *
     * @var string
     */
    public $l_delim = '{$';

    /**
     * Right delimiter character for pseudo vars
     *
     * @var string
     */
    public $r_delim = '}';

    /**
     * Reference to CodeIgniter instance
     *
     * @var \CI_Controller
     */
    protected $CI;


    /**
     * @var \Smarty
     */
    protected $smarty;

    /**
     * What controllers are in use
     * @var string
     */
    protected $_controller;

    /**
     * What  methods are in use
     * @var string
     */
    protected $_method;

    // --------------------------------------------------------------------

    /**
     * Class constructor
     *
     */

    public function __construct()
    {
        parent::__construct();
        $this->smarty = new \Smarty();

        // Load the Smarty config file
        $this->CI->config->load('smarty');

        // Default template extension
        $this->template_ext = $this->CI->config->item('smarty.template_ext');

        // Turn on/off debug
        $this->smarty->debugging = $this->CI->config->item('smarty.smarty_debug');

        // Set some pretty standard Smarty directories
        $this->smarty->setCompileDir($this->CI->config->item('smarty.compile_directory'));
        $this->smarty->setCacheDir($this->CI->config->item('smarty.cache_directory'));
        $this->smarty->setConfigDir($this->CI->config->item('smarty.config_directory'));


        // How long to cache templates for
        $this->smarty->cache_lifetime = $this->CI->config->item('smarty.cache_lifetime');

        // Disable Smarty security policy
        $this->smarty->disableSecurity();

        // Set the error reporting level
        $this->smarty->error_reporting = $this->CI->config->item('smarty.template_error_reporting');

        // This will fix various issues like filemtime errors that some people experience
        // The cause of this is most likely setting the error_reporting value above
        // This is a static function in the main Smarty class
        \Smarty::muteExpectedErrors();


        // Should let us access Codeigniter stuff in views
        // This means we can go for example {$this->session->userdata('item')}
        // just like we normally would in standard CI views
        $this->smarty->assign("this", $this->CI);

        // Detect if we have a current module
        $this->_module = $this->current_module();

        // What controllers or methods are in use
        $this->_controller = $this->CI->router->class;
        $this->_method = $this->CI->router->method;

        // If we don't have a theme name stored
        if ($this->_theme_name == '') {
            $this->set_theme($this->CI->config->item('smarty.theme_name'));
        }

        // Update theme paths
        $this->_update_theme_paths();

        log_message('info', 'MY_Parser class loaded');
    }

    /* =====================================================================================================
     *                                    FOR USE AS SMARTY
       ==================================================================================================== */

    /**
     * Call
     * able to call native Smarty methods
     *
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws \Exception  if method not exists in Smarty
     */
    public function __call($method, $params = array())
    {
        if (method_exists($this->smarty, $method)) {
            return call_user_func_array(array($this->smarty, $method), $params);
        }
        throw new \Exception('no method ' . $method . ' in ' . __CLASS__);
    }


    /**
     * Smarty variables set
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (!property_exists($this, $name)) {
            $this->smarty->$name = $value;
        }
    }

    /**
     * Smarty variables get
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->smarty->$name;
    }

    /**
     * Smarty variable isset
     *
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->smarty->$name);
    }

    /**
     * Smarty variable unset
     *
     * @param $name
     */
    public function __unset($name)
    {
        unset($this->smarty->$name);
    }

    /* =====================================================================================================
     *                                  Additional Functionality
       ==================================================================================================== */

    /**
     * Set Theme
     *
     * Set the theme to use
     *
     * @access public
     * @param $name
     * @return void
     */
    public function set_theme($name)
    {
        // Store the theme name
        $this->_theme_name = trim($name);

        // Our themes can have a functions.php file just like Wordpress
        $functions_file = $this->CI->config->item('smarty.theme_path') . $this->_theme_name . '/functions.php';

        // Incase we have a theme in the application directory
        $functions_file2 = APPPATH . "themes/" . $this->_theme_name . '/functions.php';

        // If we have a functions file, include it
        if (file_exists($functions_file)) {
            include_once($functions_file);
        } elseif (file_exists($functions_file2)) {
            include_once($functions_file2);
        }

        // Update theme paths
        $this->_update_theme_paths();
    }

    /**
     * Get Theme
     *
     * Does what the function name implies: gets the name of
     * the currently in use theme.
     *
     * @return string
     */
    public function get_theme()
    {
        return (isset($this->_theme_name)) ? $this->_theme_name : '';
    }

    /**
     * Current Module
     *
     * Just a fancier way of getting the current module
     * if we have support for modules
     *
     * @access public
     * @return string
     */
    public function current_module()
    {
        // Modular Separation / Modular Extensions has been detected
        if (method_exists($this->CI->router, 'fetch_module')) {
            $module = $this->CI->router->fetch_module();
            return (!empty($module)) ? $module : '';
        } else {
            return '';
        }
    }

    /**
     * Find View
     *
     * Searches through module and view folders looking for your view, sir.
     *
     * @access protected
     * @param $file
     * @return string The path and file found
     */
    protected function _find_view($file)
    {
        // We have no path by default
        $path = NULL;

        // Get template locations
        $locations = $this->_template_locations;

        // Get the current module
        $current_module = $this->current_module();
        if ($current_module !== $this->_module) {
            $new_locations = array(
                $this->CI->config->item('smarty.theme_path') . $this->_theme_name . '/views/modules/' . $current_module . '/layouts/',
                $this->CI->config->item('smarty.theme_path') . $this->_theme_name . '/views/modules/' . $current_module . '/',
                APPPATH . 'modules/' . $current_module . '/views/layouts/',
                APPPATH . 'modules/' . $current_module . '/views/'
            );

            foreach ($new_locations AS $new_location) {
                array_unshift($locations, $new_location);
            }
        }

        // Iterate over our saved locations and find the file
        foreach ($locations AS $location) {
            if (file_exists($location . $file)) {
                // Store the file to load
                $path = $location . $file;

                $this->_current_path = $location;

                // Stop the loop, we found our file
                break;
            }
        }

        // Return the path
        return $path;
    }

    /**
     * Add Paths
     *
     * Traverses all added template locations and adds them
     * to Smarty so we can extend and include view files
     * correctly from a slew of different locations including
     * modules if we support them.
     *
     * @access protected
     */
    protected function _add_paths()
    {
        // Iterate over our saved locations and find the file
        foreach ($this->_template_locations AS $location) {
            $this->smarty->addTemplateDir($location);
        }
    }

    /**
     * Update Theme Paths
     *
     * Adds in the required locations for themes
     *
     * @access protected
     */
    protected function _update_theme_paths()
    {
        // Store a whole heap of template locations
        $this->_template_locations = array(
            $this->CI->config->item('smarty.theme_path') . $this->_theme_name . '/views/modules/' . $this->_module . '/layouts/',
            $this->CI->config->item('smarty.theme_path') . $this->_theme_name . '/views/modules/' . $this->_module . '/',
            $this->CI->config->item('smarty.theme_path') . $this->_theme_name . '/views/layouts/',
            $this->CI->config->item('smarty.theme_path') . $this->_theme_name . '/views/',
            APPPATH . 'modules/' . $this->_module . '/views/layouts/',
            APPPATH . 'modules/' . $this->_module . '/views/',
            APPPATH . 'views/layouts/',
            APPPATH . 'views/'
        );

        // Will add paths into Smarty for "smarter" inheritance and inclusion
        $this->_add_paths();
    }

    /* =====================================================================================================
     *                                  CI_PARSER EXTENDS
       ==================================================================================================== */

    /**
     * Parse
     *
     * Parses a template using Smarty 3 engine
     *
     * @param string $template
     * @param array $data
     * @param boolean $return  TRUE - fetch template
     * @return string
     */
    public function parse($template = '', $data = null, $return = FALSE)
    {

        // If empty then Default template name
        if ("" == $template) {
            $template = $this->_controller . DIRECTORY_SEPARATOR . $this->_method;
        }

        // If no file extension dot has been found default to defined extension for view extensions
        if (!stripos($template, '.')) {
            $template = $template . "." . $this->template_ext;
        }

        // If we have variables to assign, lets assign them
        if (!empty($data)) {
            foreach ($data AS $key => $val) {
                $this->smarty->assign($key, $val);
            }
        }

        $template_string = $this->smarty->fetch($template);

        if (FALSE === $return) {
            $this->CI->output->append_output($template_string);
            return TRUE;
        }

        return $template_string;

    }

    /**
     * Parse a String
     *
     * Parses pseudo-variables contained in the specified string,
     * replacing them with the data in the second param
     *
     * @param    string
     * @param    array
     * @param    bool
     * @return    string
     */
    public function parse_string($template, $data, $return = FALSE)
    {
        return $this->_parse($template, $data, $return);
    }


    /**
     * Set the left/right variable delimiters
     *
     * @param    string
     * @param    string
     * @return    void
     */
    public function set_delimiters($l = '{', $r = '}')
    {
        $this->l_delim = $l;
        $this->r_delim = $r;
    }


}
