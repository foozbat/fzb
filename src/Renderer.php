<?php
/* 
	file:         renderer.class.php
	type:         Class Definition
	written by:   Aaron Bishop
	description:  This class contains handles the assignment of render variables and the displaying of templates
*/

namespace Fzb;

use Exception;
use Fzb;

class RendererException extends Exception { }

class Renderer
{
	// DATA MEMBERS //
	private $template_dir;
	private $template_ext;

	private $render_vars = array();
	private $reserved_var_names = ['_vars', '_base_path', 'html'];

	private $global_state = array();

	private $selects = array();
	private $checks = array();
	private $texts = array();

	// CONSTRUCTOR //
	function __construct($template_dir = "", $template_ext = "")
	{
		if ($template_dir != "") {
			$this->template_dir = $template_dir;
		} else if (defined('TEMPLATES_DIR')) {
			$this->template_dir = TEMPLATES_DIR;
		} else {
			$this->template_dir = "/templates";
		}

		if ($template_ext != "") {
			$this->template_ext = $template_ext;
		} else if (defined('TEMPLATE_EXT')) {
			$this->template_ext = TEMPLATE_EXT;
		} else {
			$this->template_ext = "tpl.php";
		}

		if ($this->template_dir == "" || $this->template_ext == "") {
			throw new RendererException("Renderer requires the templates directory and extension to be defined.");
		}

		// register default render_vars;
		$_base_path = "";
		if (!isset($_ENV['URL_ROUTE'])) {
			$_base_path = $_SERVER['REQUEST_URI'];
		} else {
			$_base_path = explode($_ENV['URL_ROUTE'], $_SERVER['REQUEST_URI'], 2)[0];
		}

		$base_path = "";
		$router = fzb_get_router();
		if(!is_null($router)) {
			$base_path = $router->get_app_path();
		}
		$this->render_vars['_base_path'] = ltrim($_base_path, "/");
	}

	// METHODS //

	public function assign($name, $value)
	{
		if (in_array($name, $this->reserved_var_names)) {
			throw new RendererException("$name is a reserved Renderer variable name.");
		}

		if (is_array($value) || $value instanceof Fzb\Input) {
			foreach ($value as $key => $var) {
				$this->render_vars[$name][$key] = htmlspecialchars((string) $var);
			}
		} else {
			$this->render_vars[$name] = htmlspecialchars((string) $value);
		}
	}

	// flattens an associative array and assigns to render vars with key name as var name
	public function assign_all($arr)
	{
		if ($arr instanceof Fzb\Input) {
			$this->assign_inputs($arr);
		} else if (is_array($arr)) {
			foreach ($arr as $name => $value) {
				if (is_int($name)) {
					throw new RendererException("assign_all: must pass an associative array or input object");
				}
				$this->assign($name, $value);
			}
		}
	}

	// assigns Fzb\InputObjects contained with in an Fzb\Input object to the renderer
	//  extracting errors to separate variables for easy checking within a template
	public function assign_inputs($input)
	{
		if ($input instanceof Fzb\Input) {
			$this->assign('input_required_error', $input->required_inputs_missing());
			$this->assign('input_validation_error', $input->inputs_invalid());

			foreach ($input as $name => $input) {
				$this->assign($name, (string) $input);
				$this->assign($name."_submitted_value", $input->submitted_value());
				$this->assign($name."_is_required", $input->is_required());
				$this->assign($name."_is_missing", $input->is_missing());
				$this->assign($name."_is_invalid", $input->is_invalid());
			}
		} else {
			throw new RendererException("assign_inputs did not receive a valid Fzb\Input object.");
		}
	}


	// renders and displays a specified page
	public function display($page)
	{
		//global $settings;

		// start output buffering
		//if ($settings->get_value('use_gzip')) {
		//	ob_start('ob_gzhandler');
		//} else {
			ob_start();
		//}

		// do rendering
		$this->render($page);

		// send buffered output to the browser
		ob_end_flush();
	}

	// internal function for the rendering of pages.  do not call this function directly!  use display()
	private function render($page)
	{
		//$bm = new Benchmark('rendering');

		// send nifty no cache headers
		// probably change this
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', FALSE);
		header('Pragma: no-cache');

		error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

		$template_file = $this->template_dir.'/'.$page.'.'.$this->template_ext;

		// sandbox the application state to limit rogue template damage
		$this->sandbox_global_state();

		// call helper functions to isolate scope of template from this class
		_load_tpl($template_file, $this->render_vars);

		// restore the state after sandbox
		$this->restore_global_state();
		error_reporting(E_ALL);

		//$bm->end_bench();
	}

	// renders the page and returns output as a string instead of sending to the browser
	public function render_as_string($page)
	{
		ob_start();
		$this->render($page);
		return ob_get_clean();
	}

	public function redirect($location)
	{
		header("Location: $location");
		exit;
	}

	private function sandbox_global_state()
	{
		$this->global_state['GLOBALS']	= $GLOBALS;
		$this->global_state['_SERVER']	= $_SERVER;
		$this->global_state['_GET']		= $_GET;
		$this->global_state['_POST']	= $_POST;
		$this->global_state['_FILES']	= $_FILES;
		$this->global_state['_COOKIE']	= $_COOKIE;
		$this->global_state['_SESSION'] = $_SESSION;
		$this->global_state['_REQUEST'] = $_REQUEST;
		$this->global_state['_ENV'] 	= $_ENV;

		//unset($GLOBALS);
		foreach ($GLOBALS as $global => $val) {
			unset($GLOBALS[$global]);
		}
		unset($_SERVER);
		unset($_GET);
		unset($_POST);
		unset($_FILES);
		unset($_COOKIE);
		unset($_SESSION);
		unset($_REQUEST);
		unset($_ENV);
	}

	private function restore_global_state()
	{
		GLOBAL $_ENV;
		GLOBAL $_REQUEST;
		GLOBAL $_SESSION;
		GLOBAL $_COOKIE;
		GLOBAL $_FILES;
		GLOBAL $_POST;
		GLOBAL $_GET;
		GLOBAL $_SERVER;
		GLOBAL $GLOBALS;

		$_ENV     = $this->global_state['_ENV'];
		$_REQUEST = $this->global_state['_REQUEST'];
		$_SESSION = $this->global_state['_SESSION'];
		$_COOKIE  = $this->global_state['_COOKIE'];
		$_FILES	  = $this->global_state['_FILES'];
		$_POST    = $this->global_state['_POST'];
		$_GET     = $this->global_state['_GET'];
		$_SERVER  = $this->global_state['_SERVER'];
		//$GLOBALS  = $this->global_state['GLOBALS'];
		foreach ($this->global_state['GLOBALS'] as $global => $val) {
			$GLOBALS[$global] = $val;
		}

		$this->global_state = null;
	}

	// SELECT, CHECK, RADIO AUTOCOMPLETION
	public function define_select($name)
	{
		$this->selects[$name] = array();
	}

	public function add_select_items($name, $array)
	{
		foreach ($array as $value => $text) {
			$this->selects[$name][$value] = array($text, 0);
		}
	}

	public function add_select_item($name, $value, $text, $selected=0)
	{
		$this->selects[$name][$value] = array($text, $selected);
	}

	public function add_text_item($name, $value)
	{
		$this->texts[$name] = $value;
	}

	public function set_selected($name, $selected)
	{
		if (is_array($selected)) {
			foreach($selected as $x) {
				if (isset($this->selects[$name][$x])) {
					$this->selects[$name][$x][1] = 1;
				}
			}
		}
		else {
			if (isset($this->selects[$name][$selected])) {
				$this->selects[$name][$selected][1] = 1;
			}
		}
	}

	/*function autoselect($name, $selected, $query)
	{
		global $db;

		$this->defineselect($name);

		$sth = $db->prepare($query);
		$sth->execute();

		while ($cols = $sth->fetchrow_array())
		{
			$this->addselectitem($name, $cols[0], $cols[1], ($selected == $cols[0] ? 1 : 0));
		}
	}*/

	private function select_box($name, $size=0, $multiple=0, $extraparams='')
	{
		if (isset($this->selects[$name])) {
			echo '<select name="'.htmlspecialchars($name).'"';
			if ($multiple) {
				echo ' multiple';
			}
			if ($size) {
				echo ' size='.$size;
			}
			echo " $extraparams>\n";

			foreach($this->selects[$name] as $value => $data) {
				list($text, $selected) = $data;
				echo '<option value="'.htmlspecialchars($value).'"';
				if ($selected) {
					echo ' selected';
				}
				echo '>'.htmlspecialchars($text)."</option>\n";
			}
			echo "</select>\n";
		}
	}

	private function text_box($name, $size=20, $extraparams='')
	{
		if (isset($this->texts[$name])) {
			$text = $this->texts[$name];
		} else {
			$text = '';
		}

		echo '<input type="text" name="'.$name.'" size="'.$size.'" value="'.$text.'" '.$extraparams.'>';
	}

	private function text_area($name, $cols=40, $rows=5, $extraparams='')
	{
		if (isset($this->texts[$name])) {
			$text = $this->texts[$name];
		} else {
			$text = '';
		}

		echo '<textarea name="'.$name.'" rows="'.$rows.'" cols="'.$cols.'" '.$extraparams.'>';
		echo $text;
		echo '</textarea>';
	}

	private function checkbox_checked($value) {
		if ($value) {
			echo "checked";
		}
	}
}

// helper function to isolate the template scope from the rest of the class
function _load_tpl($template_file, $_vars)
{
	// create a local variable for each render var
	extract($_vars, EXTR_SKIP);
	unset($_vars);

	if (file_exists($template_file)) {
		require_once($template_file);
	} else {
		throw new RendererException("Renderer could not find the specified template file.");
	}
}