<?php
/*
 * 
 * The MIT License
 * 
 * Copyright (c) 2009, ZX, Ferry Boender
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * Modified by Scott Zeid <s@srwz.us> to fix Template::templateFromString().
 * 
*/

define("TEMPLUM_VERSION", "0.4.0-sz-1");

/**
 * @brief Templum errors.
 * 
 * This exception is thrown by the Templum class when errors occur
 * during instantiation or when loading and parsing templates.
 */
class TemplumError extends Exception {

	/**
	 * @brief Create a new TemplumError instance
	 * @param $message (string) The error message.
	 * @param $code (int) The error code
	 */
	public function TemplumError($message, $code = 0) {
		parent::__construct($message, $code);
	}

}

/**
 * @brief TemplumTemplate errors.
 * 
 * This exception is thrown by the TemplumTemplate class when errors occur
 * during the execution of templates. PHP errors, warnings and notices that
 * occur during the template execution are captured by the TemplumTemplate class and
 * are thrown as TemplumTemplateError exceptions.
 */
class TemplumTemplateError extends Exception {
	
	protected $template = NULL; /**< The TemplumTemplate instance causing the error. */

	/**
	 * @brief Create a new TemplumTemplateError instance
	 * @param $message (string) The error message.
	 * @param $code (int) The error code
	 * @param $template (TemplumTemplate) The template containing the error.
	 */
	public function TemplumTemplateError($message, $code = 0, $template = NULL) {
		$this->template = $template;
		parent::__construct($message, $code);
	}

	/**
	 * @brief Return the TemplumTemplate instance that contains the error.
	 * @return (TemplumTemplate) The template containing the error or NULL if not available.
	 */
	public function getTemplate() {
		return($this->template);
	}

}

/**
 * @brief Templum Templating Engine.
 * 
 * This is the main Templum class. It takes care of retrieval, caching and
 * compiling of (translated) templates.
 */
class Templum {
	/**
	 * @brief Create a new Templum instance.
	 * @param $templatePath (string) The full or relative path to the template directory.
	 * @param $varsUniversal (array) An array of key/value pairs that will be exported to every template retrieved using this template engine instance.
	 * @param $locale (string) The locale for the templates to retrieve. If a file with the suffix noted in $locale is available, it will be returned instead of the default .tpl file.
	 * @throw TemplumError if the $templatePath can't be found or isn't a directory.
	 */
	public function Templum($templatePath, $varsUniversal = array(), $locale = NULL) {
		if (!file_exists($templatePath)) {
			throw new TemplumError("No such file or directory: $templatePath", 1);
		}
		if (!is_dir($templatePath)) {
			throw new TemplumError("Not a directory: $templatePath", 2);
		}
		$this->templatePath = rtrim(realpath($templatePath), '/');
		$this->varsUniversal = $varsUniversal;
		$this->locale = $locale;
		$this->autoEscape = True;
		$this->cache = array();
	}

	/**
	 * @brief Set a universal variable which will available in each template created with this Templum instance.
	 * @param $varName (string) The name of the variable. This will become available in the template as $VARNAME.
	 * @param $varValue (mixed) The value of the variable.
	 */
	public function setVar($varName, $varValue) {
		$this->varsUniversal[$varName] = $varValue;
	}

	/**
	 * @brief Turn the auto escape on or off. If on, all content rendered using {{ and }} will automatically be escaped with htmlspecialchars().
	 * @param $escape (boolean) True of False. If True, auto escaping is turned on (this is the default). If False, it is turned off for templates retrieved with this Templum engine.
	 * @note Auto escaping can be overridden by passing the $autoEscape option to the template() and templateFromString() methods.
	 */
	public function setAutoEscape($escape = True) {
		$this->autoEscape = $escape;
	}

	/**
	 * @brief Set the locale for templates.
	 * @param $locale (string) The locale for the templates to retrieve. If a file with the suffix noted in $locale is available, it will be returned instead of the default .tpl file.
	 */
	public function setLocale($locale) {
		$this->locale = $locale;
	}

	/**
	 * @brief Retrieve a template by from disk (caching it in memory for the duration of the Templum instance lifetime) or from cache.
	 * @param $path (string) TemplumTemplate path, without the .tpl extension, relative to the templatePath.
	 * @param $varsGlobal (array) Array of key/value pairs that will be exported to the returned template and all templates included by that template.
	 * @param $autoEscape (boolean) Whether to auto escape {{ and }} output with htmlspecialchars()
	 * @throw TemplumError if the template couldn't be read.
	 */
	public function template($path, $varsGlobal = array(), $autoEscape = NULL) {
		$fpath = $this->templatePath . '/' . trim($path, '/').'.tpl';
		if ($autoEscape === NULL) {
			$autoEscape = $this->autoEscape;
		}

		// Check for translated version of this template.
		if (!empty($this->locale)) {
			// Check if the translated template exists in the cache. If it
			// does, returned the cached result. Otherwise check the disk for
			// the translated template.
			$fpathTrans = realpath($fpath.'.'.$this->locale);
			if ($fpathTrans !== False) {
				if (array_key_exists($fpathTrans, $this->cache)) {
					return($this->cache[$fpathTrans]);
				} else {
					if (file_exists($fpathTrans)) {
						$fpath = $fpathTrans;
					}
				}
			}
		// Check the non-translated version of this template
		} else {
			// Check the cache for the non-translated template. 
			$rpath = realpath($fpath);
			if($rpath === False) {
				throw new TemplumError("Template not found or not a file: $fpath", 3);
			}
			if (array_key_exists($rpath, $this->cache)) {
				return($this->cache[$rpath]);
			}
			$fpath = $rpath;
		}

		// Check if the template exists. 
		if (!is_file($fpath)) {
			throw new TemplumError("Template not found or not a file: $fpath", 3);
		}
		if (!is_readable($fpath)) {
			throw new TemplumError("Template not readable: $fpath", 4);
		}

		// Load the base or translated template.
		$template = new TemplumTemplate(
				$this,
				$fpath,
				$this->compile(file_get_contents($fpath), $autoEscape), 
				array_merge($this->varsUniversal, $varsGlobal)
			);
		$this->cache[$fpath] = $template;
		return($template);
	}
	
	/**
	 * @brief Create a TemplumTemplate from a string.
	 * 
	 * Create a TemplumTemplate instance using $contents as the template contents.
	 * This severely limited what you can do with the TemplumTemplate. There will be
	 * no including from the template, no translations, no caching, etc.
	 *
	 * @param $contents (string) The template contents.
	 * @param $autoEscape (boolean) Whether to auto escape {{ and }} output with htmlspecialchars()
	 * @returns (TemplumTemplate) TemplumTemplate class instance.
	 */
	public static function templateFromString($contents, $autoEscape = True) {
		//if ($autoEscape === Null) {
		//	$autoEscape = $this->autoEscape;
		//}

		// Load the base or translated template.
		$template = new TemplumTemplate(
				NULL,
				"FROM_STRING",
				self::compile($contents, $autoEscape), 
				array()
			);
		return($template);
	}

	/**
	 * @brief Compile a template string to PHP code.
	 * @param $contents (string) String to compile to PHP code.
	 * @param $autoEscape (boolean) Whether to auto escape {{ and }} output with htmlspecialchars()
	 * @note This method is used by the Templum class itself, and shouldn't be called directly yourself. Use templateFromString() instead.
	 */
	private function compile($contents, $autoEscape = True) {
		// Parse custom short-hand tags to PHP code.
		$contents = preg_replace(
			array(
				"/{{/", 
				"/}}\n/", 
				"/}}/", 
				"/\[\[/", 
				"/\]\]/",
				'/^\s*@(.*)$/m',
				'/\[:\s*block\s(.*)\s*:\](.*)\[:\s*endblock\s*:\]/Usm',
				),
			array(
				$autoEscape ? "<?php echo(htmlspecialchars(" : "<?php echo(", 
				$autoEscape ? ")); ?>\n\n" : "); ?>\n\n",
				$autoEscape ? ")); ?>" : "); ?>",
				"<?php ",
				" ?>",
				"<?php \\1 ?>",
				"<?php if (array_key_exists('\\1', \$this->inheritBlocks)) { print(\$this->inheritBlocks['\\1']); } else if (\$this->inheritFrom === NULL) { ?>\\2<?php } else { ob_start(); ?>\\2<?php \$this->inheritBlocks['\\1'] = ob_get_contents(); ob_end_clean(); } ?>",
				),
			$contents
		);
		return($contents);
	}
}

/**
 * @brief Template class
 *
 * This is the TemplumTemplate class. It represents a template and handles the
 * actual rendering of the template, as well as catching errors during
 * rendering. It also contains helper methods which can be used in templates.
 */
class TemplumTemplate {
	/**
	 * @brief Create a new TemplumTemplate instance. You'd normally get an instance from a Templum class instance.
	 * @param $templum (Templum instance) The Templum class instance that generated this TemplumTemplate instance.
	 * @param $filename (string) The filename of this template.
	 * @param $contents (string) The compiled contents of this template.
	 * @param $varsGlobal (array) An array of key/value pairs which represent the global variables for this template and the templates it includes.
	 */
	public function TemplumTemplate($templum, $filename, $contents, $varsGlobal = array()) {
		$this->templum = $templum;
		$this->filename = $filename;
		$this->contents = $contents;
		$this->varsGlobal = $varsGlobal;
		$this->inheritFrom = NULL; 
		$this->inheritBlocks = array();
	}

	/**
	 * @brief Add an global variable. The global variable will be available to this templates and all the templates it includes.
	 * @param $varName (string) The name of the variable.
	 * @param $varValue (mixed) The value of the variable.
	 */
	public function setVar($varName, $varValue) {
		$this->varsGlobal[$varName] = $varValue;
	}

	/**
	 * @brief Render the contents of the template and return it as a string.
	 * @param $varsLocal (array) An array of key/value pairs which represent the local variables for this template. 
	 * @return (string) The rendered contents of the template.
	 */
	public function render($varsLocal = array()) {
		// Extract the Universal (embedded in global), Global and
		// Localvariables into the current scope.
		extract($this->varsGlobal);
		extract($varsLocal);

		// Start output buffering so we can capture the output of the eval.
		ob_start();

		// Temporary set the error handler so we can show the faulty template
		// file. Render the contents, reset the error handler and return the
		// rendered contents.
		$this->errorHandlerOrig = set_error_handler(array($this, 'errorHandler'));
		eval("?>" . $this->contents);
		restore_error_handler();

		// Stop output buffering and return the contents of the buffer
		// (rendered template).
		$result = ob_get_contents();
		ob_end_clean();

		if ($this->inheritFrom !== NULL) {
			$this->inheritFrom->inheritBlocks = $this->inheritBlocks;
			$result = $this->inheritFrom->render();
		}

		return($result);
	}

	/**
	 * @brief The error handler that handles errors during the parsing of the template. 
	 * @param $nr (int) Error code
	 * @param $string (string) Error message
	 * @param $file (string) Filename of the file in which the erorr occurred.
	 * @param $line (int) Linenumber of the line on which the error occurred.
	 * @note Do not call this yourself. It is used internally by Templum but must be public.
	 */
	public function errorHandler($nr, $string, $file, $line) {
		// We can restore the old error handler, otherwise this error handler
		// will stay active because we throw an exception below.
		restore_error_handler();

		// If this is reached, it means we were still in Output Buffering mode.
		// Stop output buffering, or we'll end up with template text on the
		// Stdout.
		ob_end_clean();

		// Throw the exception
		throw new TemplumTemplateError("$string (file: {$this->filename}, line $line)", 1, $this);
	}

	/**
	 * @brief Include another template.
	 * @param $template (string) The template to include.
	 * @param $varsLocal (array) An array of key/value pairs which represent the local variables for this template. 
	 */
	public function inc($template, $varsLocal = array()) {
		if (!isset($this->templum)) {
			throw new TemplumTemplateError("Cannot include templates in a TemplumTemplate instance created from a string.", 2, $this);
		}
		$t = $this->templum->template($template, $varsLocal);
		print($t->render());
	}

	/**
	 * @brief Inherit from a parent template.
	 * @param $template (string) The template to inherit from.
	 */
	public function inherit($template) {
		$this->inheritFrom = $this->templum->template($template);
	}
}



/**
   * Spyc -- A Simple PHP YAML Class
   * @version 0.5
   * @author Vlad Andersen <vlad.andersen@gmail.com>
   * @author Chris Wanstrath <chris@ozmm.org>
   * @link http://code.google.com/p/spyc/
   * @copyright Copyright 2005-2006 Chris Wanstrath, 2006-2011 Vlad Andersen
   * @license http://www.opensource.org/licenses/mit-license.php MIT License
   * @package Spyc
   */

if (!function_exists('spyc_load')) {
  /**
   * Parses YAML to array.
   * @param string $string YAML string.
   * @return array
   */
  function spyc_load ($string) {
    return Spyc::YAMLLoadString($string);
  }
}

if (!function_exists('spyc_load_file')) {
  /**
   * Parses YAML to array.
   * @param string $file Path to YAML file.
   * @return array
   */
  function spyc_load_file ($file) {
    return Spyc::YAMLLoad($file);
  }
}

/**
   * The Simple PHP YAML Class.
   *
   * This class can be used to read a YAML file and convert its contents
   * into a PHP array.  It currently supports a very limited subsection of
   * the YAML spec.
   *
   * Usage:
   * <code>
   *   $Spyc  = new Spyc;
   *   $array = $Spyc->load($file);
   * </code>
   * or:
   * <code>
   *   $array = Spyc::YAMLLoad($file);
   * </code>
   * or:
   * <code>
   *   $array = spyc_load_file($file);
   * </code>
   * @package Spyc
   */
class Spyc {

  // SETTINGS

  const REMPTY = "\0\0\0\0\0";

  /**
   * Setting this to true will force YAMLDump to enclose any string value in
   * quotes.  False by default.
   * 
   * @var bool
   */
  public $setting_dump_force_quotes = false;

  /**
   * Setting this to true will forse YAMLLoad to use syck_load function when
   * possible. False by default.
   * @var bool
   */
  public $setting_use_syck_is_possible = false;



  /**#@+
  * @access private
  * @var mixed
  */
  private $_dumpIndent;
  private $_dumpWordWrap;
  private $_containsGroupAnchor = false;
  private $_containsGroupAlias = false;
  private $path;
  private $result;
  private $LiteralPlaceHolder = '___YAML_Literal_Block___';
  private $SavedGroups = array();
  private $indent;
  /**
   * Path modifier that should be applied after adding current element.
   * @var array
   */
  private $delayedPath = array();

  /**#@+
  * @access public
  * @var mixed
  */
  public $_nodeId;

/**
 * Load a valid YAML string to Spyc.
 * @param string $input
 * @return array
 */
  public function load ($input) {
    return $this->__loadString($input);
  }

 /**
 * Load a valid YAML file to Spyc.
 * @param string $file
 * @return array
 */
  public function loadFile ($file) {
    return $this->__load($file);
  }

  /**
     * Load YAML into a PHP array statically
     *
     * The load method, when supplied with a YAML stream (string or file),
     * will do its best to convert YAML in a file into a PHP array.  Pretty
     * simple.
     *  Usage:
     *  <code>
     *   $array = Spyc::YAMLLoad('lucky.yaml');
     *   print_r($array);
     *  </code>
     * @access public
     * @return array
     * @param string $input Path of YAML file or string containing YAML
     */
  public static function YAMLLoad($input) {
    $Spyc = new Spyc;
    return $Spyc->__load($input);
  }

  /**
     * Load a string of YAML into a PHP array statically
     *
     * The load method, when supplied with a YAML string, will do its best 
     * to convert YAML in a string into a PHP array.  Pretty simple.
     *
     * Note: use this function if you don't want files from the file system
     * loaded and processed as YAML.  This is of interest to people concerned
     * about security whose input is from a string.
     *
     *  Usage:
     *  <code>
     *   $array = Spyc::YAMLLoadString("---\n0: hello world\n");
     *   print_r($array);
     *  </code>
     * @access public
     * @return array
     * @param string $input String containing YAML
     */
  public static function YAMLLoadString($input) {
    $Spyc = new Spyc;
    return $Spyc->__loadString($input);
  }

  /**
     * Dump YAML from PHP array statically
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.  Pretty simple.  Feel free to
     * save the returned string as nothing.yaml and pass it around.
     *
     * Oh, and you can decide how big the indent is and what the wordwrap
     * for folding is.  Pretty cool -- just pass in 'false' for either if
     * you want to use the default.
     *
     * Indent's default is 2 spaces, wordwrap's default is 40 characters.  And
     * you can turn off wordwrap by passing in 0.
     *
     * @access public
     * @return string
     * @param array $array PHP array
     * @param int $indent Pass in false to use the default, which is 2
     * @param int $wordwrap Pass in 0 for no wordwrap, false for default (40)
     */
  public static function YAMLDump($array,$indent = false,$wordwrap = false) {
    $spyc = new Spyc;
    return $spyc->dump($array,$indent,$wordwrap);
  }


  /**
     * Dump PHP array to YAML
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.  Pretty simple.  Feel free to
     * save the returned string as tasteful.yaml and pass it around.
     *
     * Oh, and you can decide how big the indent is and what the wordwrap
     * for folding is.  Pretty cool -- just pass in 'false' for either if
     * you want to use the default.
     *
     * Indent's default is 2 spaces, wordwrap's default is 40 characters.  And
     * you can turn off wordwrap by passing in 0.
     *
     * @access public
     * @return string
     * @param array $array PHP array
     * @param int $indent Pass in false to use the default, which is 2
     * @param int $wordwrap Pass in 0 for no wordwrap, false for default (40)
     */
  public function dump($array,$indent = false,$wordwrap = false) {
    // Dumps to some very clean YAML.  We'll have to add some more features
    // and options soon.  And better support for folding.

    // New features and options.
    if ($indent === false or !is_numeric($indent)) {
      $this->_dumpIndent = 2;
    } else {
      $this->_dumpIndent = $indent;
    }

    if ($wordwrap === false or !is_numeric($wordwrap)) {
      $this->_dumpWordWrap = 40;
    } else {
      $this->_dumpWordWrap = $wordwrap;
    }

    // New YAML document
    $string = "---\n";

    // Start at the base of the array and move through it.
    if ($array) {
      $array = (array)$array; 
      $previous_key = -1;
      foreach ($array as $key => $value) {
        if (!isset($first_key)) $first_key = $key;
        $string .= $this->_yamlize($key,$value,0,$previous_key, $first_key, $array);
        $previous_key = $key;
      }
    }
    return $string;
  }

  /**
     * Attempts to convert a key / value array item to YAML
     * @access private
     * @return string
     * @param $key The name of the key
     * @param $value The value of the item
     * @param $indent The indent of the current node
     */
  private function _yamlize($key,$value,$indent, $previous_key = -1, $first_key = 0, $source_array = null) {
    if (is_array($value)) {
      if (empty ($value))
        return $this->_dumpNode($key, array(), $indent, $previous_key, $first_key, $source_array);
      // It has children.  What to do?
      // Make it the right kind of item
      $string = $this->_dumpNode($key, self::REMPTY, $indent, $previous_key, $first_key, $source_array);
      // Add the indent
      $indent += $this->_dumpIndent;
      // Yamlize the array
      $string .= $this->_yamlizeArray($value,$indent);
    } elseif (!is_array($value)) {
      // It doesn't have children.  Yip.
      $string = $this->_dumpNode($key, $value, $indent, $previous_key, $first_key, $source_array);
    }
    return $string;
  }

  /**
     * Attempts to convert an array to YAML
     * @access private
     * @return string
     * @param $array The array you want to convert
     * @param $indent The indent of the current level
     */
  private function _yamlizeArray($array,$indent) {
    if (is_array($array)) {
      $string = '';
      $previous_key = -1;
      foreach ($array as $key => $value) {
        if (!isset($first_key)) $first_key = $key;
        $string .= $this->_yamlize($key, $value, $indent, $previous_key, $first_key, $array);
        $previous_key = $key;
      }
      return $string;
    } else {
      return false;
    }
  }

  /**
     * Returns YAML from a key and a value
     * @access private
     * @return string
     * @param $key The name of the key
     * @param $value The value of the item
     * @param $indent The indent of the current node
     */
  private function _dumpNode($key, $value, $indent, $previous_key = -1, $first_key = 0, $source_array = null) {
    // do some folding here, for blocks
    if (is_string ($value) && ((strpos($value,"\n") !== false || strpos($value,": ") !== false || strpos($value,"- ") !== false ||
      strpos($value,"*") !== false || strpos($value,"#") !== false || strpos($value,"<") !== false || strpos($value,">") !== false || strpos ($value, '  ') !== false ||
      strpos($value,"[") !== false || strpos($value,"]") !== false || strpos($value,"{") !== false || strpos($value,"}") !== false) || strpos($value,"&") !== false || strpos($value, "'") !== false || strpos($value, "!") === 0 ||
      substr ($value, -1, 1) == ':')
    ) {
      $value = $this->_doLiteralBlock($value,$indent);
    } else {
      $value  = $this->_doFolding($value,$indent);
    }

    if ($value === array()) $value = '[ ]';
    if (in_array ($value, array ('true', 'TRUE', 'false', 'FALSE', 'y', 'Y', 'n', 'N', 'null', 'NULL'), true)) {
       $value = $this->_doLiteralBlock($value,$indent);
    }
    if (trim ($value) != $value)
       $value = $this->_doLiteralBlock($value,$indent);

    if (is_bool($value)) {
       $value = ($value) ? "true" : "false";
    }
    
    if ($value === null) $value = 'null';
    if ($value === "'" . self::REMPTY . "'") $value = null;

    $spaces = str_repeat(' ',$indent);

    //if (is_int($key) && $key - 1 == $previous_key && $first_key===0) {
    if (is_array ($source_array) && array_keys($source_array) === range(0, count($source_array) - 1)) {
      // It's a sequence
      $string = $spaces.'- '.$value."\n";
    } else {
      // if ($first_key===0)  throw new Exception('Keys are all screwy.  The first one was zero, now it\'s "'. $key .'"');
      // It's mapped
      if (strpos($key, ":") !== false || strpos($key, "#") !== false) { $key = '"' . $key . '"'; }
      $string = rtrim ($spaces.$key.': '.$value)."\n";
    }
    return $string;
  }

  /**
     * Creates a literal block for dumping
     * @access private
     * @return string
     * @param $value
     * @param $indent int The value of the indent
     */
  private function _doLiteralBlock($value,$indent) {
    if ($value === "\n") return '\n';
    if (strpos($value, "\n") === false && strpos($value, "'") === false) {
      return sprintf ("'%s'", $value);
    }
    if (strpos($value, "\n") === false && strpos($value, '"') === false) {
      return sprintf ('"%s"', $value);
    }
    $exploded = explode("\n",$value);
    $newValue = '|';
    $indent  += $this->_dumpIndent;
    $spaces   = str_repeat(' ',$indent);
    foreach ($exploded as $line) {
      $newValue .= "\n" . $spaces . ($line);
    }
    return $newValue;
  }

  /**
     * Folds a string of text, if necessary
     * @access private
     * @return string
     * @param $value The string you wish to fold
     */
  private function _doFolding($value,$indent) {
    // Don't do anything if wordwrap is set to 0

    if ($this->_dumpWordWrap !== 0 && is_string ($value) && strlen($value) > $this->_dumpWordWrap) {
      $indent += $this->_dumpIndent;
      $indent = str_repeat(' ',$indent);
      $wrapped = wordwrap($value,$this->_dumpWordWrap,"\n$indent");
      $value   = ">\n".$indent.$wrapped;
    } else {
      if ($this->setting_dump_force_quotes && is_string ($value) && $value !== self::REMPTY)
        $value = '"' . $value . '"';
    }


    return $value;
  }

// LOADING FUNCTIONS

  private function __load($input) {
    $Source = $this->loadFromSource($input);
    return $this->loadWithSource($Source);
  }

  private function __loadString($input) {
    $Source = $this->loadFromString($input);
    return $this->loadWithSource($Source);
  }

  private function loadWithSource($Source) {
    if (empty ($Source)) return array();
    if ($this->setting_use_syck_is_possible && function_exists ('syck_load')) {
      $array = syck_load (implode ('', $Source));
      return is_array($array) ? $array : array();
    }

    $this->path = array();
    $this->result = array();

    $cnt = count($Source);
    for ($i = 0; $i < $cnt; $i++) {
      $line = $Source[$i];
      
      $this->indent = strlen($line) - strlen(ltrim($line));
      $tempPath = $this->getParentPathByIndent($this->indent);
      $line = self::stripIndent($line, $this->indent);
      if (self::isComment($line)) continue;
      if (self::isEmpty($line)) continue;
      $this->path = $tempPath;

      $literalBlockStyle = self::startsLiteralBlock($line);
      if ($literalBlockStyle) {
        $line = rtrim ($line, $literalBlockStyle . " \n");
        $literalBlock = '';
        $line .= $this->LiteralPlaceHolder;
        $literal_block_indent = strlen($Source[$i+1]) - strlen(ltrim($Source[$i+1]));
        while (++$i < $cnt && $this->literalBlockContinues($Source[$i], $this->indent)) {
          $literalBlock = $this->addLiteralLine($literalBlock, $Source[$i], $literalBlockStyle, $literal_block_indent);
        }
        $i--;
      }

      while (++$i < $cnt && self::greedilyNeedNextLine($line)) {
        $line = rtrim ($line, " \n\t\r") . ' ' . ltrim ($Source[$i], " \t");
      }
      $i--;



      if (strpos ($line, '#')) {
        if (strpos ($line, '"') === false && strpos ($line, "'") === false)
          $line = preg_replace('/\s+#(.+)$/','',$line);
      }

      $lineArray = $this->_parseLine($line);

      if ($literalBlockStyle)
        $lineArray = $this->revertLiteralPlaceHolder ($lineArray, $literalBlock);

      $this->addArray($lineArray, $this->indent);

      foreach ($this->delayedPath as $indent => $delayedPath)
        $this->path[$indent] = $delayedPath;

      $this->delayedPath = array();

    }
    return $this->result;
  }

  private function loadFromSource ($input) {
    if (!empty($input) && strpos($input, "\n") === false && file_exists($input))
    return file($input);

    return $this->loadFromString($input);
  }

  private function loadFromString ($input) {
    $lines = explode("\n",$input);
    foreach ($lines as $k => $_) {
      $lines[$k] = rtrim ($_, "\r");
    }
    return $lines;
  }

  /**
     * Parses YAML code and returns an array for a node
     * @access private
     * @return array
     * @param string $line A line from the YAML file
     */
  private function _parseLine($line) {
    if (!$line) return array();
    $line = trim($line);
    if (!$line) return array();

    $array = array();

    $group = $this->nodeContainsGroup($line);
    if ($group) {
      $this->addGroup($line, $group);
      $line = $this->stripGroup ($line, $group);
    }

    if ($this->startsMappedSequence($line))
      return $this->returnMappedSequence($line);

    if ($this->startsMappedValue($line))
      return $this->returnMappedValue($line);

    if ($this->isArrayElement($line))
     return $this->returnArrayElement($line);

    if ($this->isPlainArray($line))
     return $this->returnPlainArray($line); 
     
     
    return $this->returnKeyValuePair($line);

  }

  /**
     * Finds the type of the passed value, returns the value as the new type.
     * @access private
     * @param string $value
     * @return mixed
     */
  private function _toType($value) {
    if ($value === '') return null;
    $first_character = $value[0];
    $last_character = substr($value, -1, 1);

    $is_quoted = false;
    do {
      if (!$value) break;
      if ($first_character != '"' && $first_character != "'") break;
      if ($last_character != '"' && $last_character != "'") break;
      $is_quoted = true;
    } while (0);

    if ($is_quoted)
      return strtr(substr ($value, 1, -1), array ('\\"' => '"', '\'\'' => '\'', '\\\'' => '\''));
    
    if (strpos($value, ' #') !== false && !$is_quoted)
      $value = preg_replace('/\s+#(.+)$/','',$value);

    if (!$is_quoted) $value = str_replace('\n', "\n", $value);

    if ($first_character == '[' && $last_character == ']') {
      // Take out strings sequences and mappings
      $innerValue = trim(substr ($value, 1, -1));
      if ($innerValue === '') return array();
      $explode = $this->_inlineEscape($innerValue);
      // Propagate value array
      $value  = array();
      foreach ($explode as $v) {
        $value[] = $this->_toType($v);
      }
      return $value;
    }

    if (strpos($value,': ')!==false && $first_character != '{') {
      $array = explode(': ',$value);
      $key   = trim($array[0]);
      array_shift($array);
      $value = trim(implode(': ',$array));
      $value = $this->_toType($value);
      return array($key => $value);
    }
    
    if ($first_character == '{' && $last_character == '}') {
      $innerValue = trim(substr ($value, 1, -1));
      if ($innerValue === '') return array();
      // Inline Mapping
      // Take out strings sequences and mappings
      $explode = $this->_inlineEscape($innerValue);
      // Propagate value array
      $array = array();
      foreach ($explode as $v) {
        $SubArr = $this->_toType($v);
        if (empty($SubArr)) continue;
        if (is_array ($SubArr)) {
          $array[key($SubArr)] = $SubArr[key($SubArr)]; continue;
        }
        $array[] = $SubArr;
      }
      return $array;
    }

    if ($value == 'null' || $value == 'NULL' || $value == 'Null' || $value == '' || $value == '~') {
      return null;
    }

    if ( is_numeric($value) && preg_match ('/^(-|)[1-9]+[0-9]*$/', $value) ){
      $intvalue = (int)$value;
      if ($intvalue != PHP_INT_MAX)
        $value = $intvalue;
      return $value;
    }

    if (in_array($value,
                 array('true', 'on', '+', 'yes', 'y', 'True', 'TRUE', 'On', 'ON', 'YES', 'Yes', 'Y'))) {
      return true;
    }

    if (in_array(strtolower($value),
                 array('false', 'off', '-', 'no', 'n'))) {
      return false;
    }

    if (is_numeric($value)) {
      if ($value === '0') return 0;
      if (rtrim ($value, 0) === $value)
        $value = (float)$value;
      return $value;
    }
    
    return $value;
  }

  /**
     * Used in inlines to check for more inlines or quoted strings
     * @access private
     * @return array
     */
  private function _inlineEscape($inline) {
    // There's gotta be a cleaner way to do this...
    // While pure sequences seem to be nesting just fine,
    // pure mappings and mappings with sequences inside can't go very
    // deep.  This needs to be fixed.

    $seqs = array();
    $maps = array();
    $saved_strings = array();

    // Check for strings
    $regex = '/(?:(")|(?:\'))((?(1)[^"]+|[^\']+))(?(1)"|\')/';
    if (preg_match_all($regex,$inline,$strings)) {
      $saved_strings = $strings[0];
      $inline  = preg_replace($regex,'YAMLString',$inline);
    }
    unset($regex);

    $i = 0;
    do {

    // Check for sequences
    while (preg_match('/\[([^{}\[\]]+)\]/U',$inline,$matchseqs)) {
      $seqs[] = $matchseqs[0];
      $inline = preg_replace('/\[([^{}\[\]]+)\]/U', ('YAMLSeq' . (count($seqs) - 1) . 's'), $inline, 1);
    }

    // Check for mappings
    while (preg_match('/{([^\[\]{}]+)}/U',$inline,$matchmaps)) {
      $maps[] = $matchmaps[0];
      $inline = preg_replace('/{([^\[\]{}]+)}/U', ('YAMLMap' . (count($maps) - 1) . 's'), $inline, 1);
    }

    if ($i++ >= 10) break;

    } while (strpos ($inline, '[') !== false || strpos ($inline, '{') !== false);

    $explode = explode(', ',$inline);
    $stringi = 0; $i = 0;

    while (1) {

    // Re-add the sequences
    if (!empty($seqs)) {
      foreach ($explode as $key => $value) {
        if (strpos($value,'YAMLSeq') !== false) {
          foreach ($seqs as $seqk => $seq) {
            $explode[$key] = str_replace(('YAMLSeq'.$seqk.'s'),$seq,$value);
            $value = $explode[$key];
          }
        }
      }
    }

    // Re-add the mappings
    if (!empty($maps)) {
      foreach ($explode as $key => $value) {
        if (strpos($value,'YAMLMap') !== false) {
          foreach ($maps as $mapk => $map) {
            $explode[$key] = str_replace(('YAMLMap'.$mapk.'s'), $map, $value);
            $value = $explode[$key];
          }
        }
      }
    }


    // Re-add the strings
    if (!empty($saved_strings)) {
      foreach ($explode as $key => $value) {
        while (strpos($value,'YAMLString') !== false) {
          $explode[$key] = preg_replace('/YAMLString/',$saved_strings[$stringi],$value, 1);
          unset($saved_strings[$stringi]);
          ++$stringi;
          $value = $explode[$key];
        }
      }
    }

    $finished = true;
    foreach ($explode as $key => $value) {
      if (strpos($value,'YAMLSeq') !== false) {
        $finished = false; break;
      }
      if (strpos($value,'YAMLMap') !== false) {
        $finished = false; break;
      }
      if (strpos($value,'YAMLString') !== false) {
        $finished = false; break;
      }
    }
    if ($finished) break;

    $i++;
    if ($i > 10) 
      break; // Prevent infinite loops.
    }

    return $explode;
  }

  private function literalBlockContinues ($line, $lineIndent) {
    if (!trim($line)) return true;
    if (strlen($line) - strlen(ltrim($line)) > $lineIndent) return true;
    return false;
  }

  private function referenceContentsByAlias ($alias) {
    do {
      if (!isset($this->SavedGroups[$alias])) { echo "Bad group name: $alias."; break; }
      $groupPath = $this->SavedGroups[$alias];
      $value = $this->result;
      foreach ($groupPath as $k) {
        $value = $value[$k];
      }
    } while (false);
    return $value;
  }

  private function addArrayInline ($array, $indent) {
      $CommonGroupPath = $this->path;
      if (empty ($array)) return false;
      
      foreach ($array as $k => $_) {
        $this->addArray(array($k => $_), $indent);
        $this->path = $CommonGroupPath;
      }
      return true;
  }

  private function addArray ($incoming_data, $incoming_indent) {

   // print_r ($incoming_data);

    if (count ($incoming_data) > 1)
      return $this->addArrayInline ($incoming_data, $incoming_indent);
    
    $key = key ($incoming_data);
    $value = isset($incoming_data[$key]) ? $incoming_data[$key] : null;
    if ($key === '__!YAMLZero') $key = '0';

    if ($incoming_indent == 0 && !$this->_containsGroupAlias && !$this->_containsGroupAnchor) { // Shortcut for root-level values.
      if ($key || $key === '' || $key === '0') {
        $this->result[$key] = $value;
      } else {
        $this->result[] = $value; end ($this->result); $key = key ($this->result);
      }
      $this->path[$incoming_indent] = $key;
      return;
    }


    
    $history = array();
    // Unfolding inner array tree.
    $history[] = $_arr = $this->result;
    foreach ($this->path as $k) {
      $history[] = $_arr = $_arr[$k];
    }

    if ($this->_containsGroupAlias) {
      $value = $this->referenceContentsByAlias($this->_containsGroupAlias);
      $this->_containsGroupAlias = false;
    }


    // Adding string or numeric key to the innermost level or $this->arr.
    if (is_string($key) && $key == '<<') {
      if (!is_array ($_arr)) { $_arr = array (); }

      $_arr = array_merge ($_arr, $value);
    } else if ($key || $key === '' || $key === '0') {
      if (!is_array ($_arr))
        $_arr = array ($key=>$value);
      else
        $_arr[$key] = $value;
    } else {
      if (!is_array ($_arr)) { $_arr = array ($value); $key = 0; }
      else { $_arr[] = $value; end ($_arr); $key = key ($_arr); }
    }

    $reverse_path = array_reverse($this->path);
    $reverse_history = array_reverse ($history);
    $reverse_history[0] = $_arr;
    $cnt = count($reverse_history) - 1;
    for ($i = 0; $i < $cnt; $i++) {
      $reverse_history[$i+1][$reverse_path[$i]] = $reverse_history[$i];
    }
    $this->result = $reverse_history[$cnt];

    $this->path[$incoming_indent] = $key;

    if ($this->_containsGroupAnchor) {
      $this->SavedGroups[$this->_containsGroupAnchor] = $this->path;
      if (is_array ($value)) {
        $k = key ($value);
        if (!is_int ($k)) {
          $this->SavedGroups[$this->_containsGroupAnchor][$incoming_indent + 2] = $k;
        }
      }
      $this->_containsGroupAnchor = false;
    }

  }

  private static function startsLiteralBlock ($line) {
    $lastChar = substr (trim($line), -1);
    if ($lastChar != '>' && $lastChar != '|') return false;
    if ($lastChar == '|') return $lastChar;
    // HTML tags should not be counted as literal blocks.
    if (preg_match ('#<.*?>$#', $line)) return false;
    return $lastChar;
  }

  private static function greedilyNeedNextLine($line) {
    $line = trim ($line);
    if (!strlen($line)) return false;
    if (substr ($line, -1, 1) == ']') return false;
    if ($line[0] == '[') return true;
    if (preg_match ('#^[^:]+?:\s*\[#', $line)) return true;
    return false;
  }

  private function addLiteralLine ($literalBlock, $line, $literalBlockStyle, $indent = -1) {
    $line = self::stripIndent($line, $indent);
    if ($literalBlockStyle !== '|') {
        $line = self::stripIndent($line);
    }
    $line = rtrim ($line, "\r\n\t ") . "\n";
    if ($literalBlockStyle == '|') {
      return $literalBlock . $line;
    }
    if (strlen($line) == 0)
      return rtrim($literalBlock, ' ') . "\n";
    if ($line == "\n" && $literalBlockStyle == '>') {
      return rtrim ($literalBlock, " \t") . "\n";
    }
    if ($line != "\n")
      $line = trim ($line, "\r\n ") . " ";
    return $literalBlock . $line;
  }

   function revertLiteralPlaceHolder ($lineArray, $literalBlock) {
     foreach ($lineArray as $k => $_) {
      if (is_array($_))
        $lineArray[$k] = $this->revertLiteralPlaceHolder ($_, $literalBlock);
      else if (substr($_, -1 * strlen ($this->LiteralPlaceHolder)) == $this->LiteralPlaceHolder)
	       $lineArray[$k] = rtrim ($literalBlock, " \r\n");
     }
     return $lineArray;
   }

  private static function stripIndent ($line, $indent = -1) {
    if ($indent == -1) $indent = strlen($line) - strlen(ltrim($line));
    return substr ($line, $indent);
  }

  private function getParentPathByIndent ($indent) {
    if ($indent == 0) return array();
    $linePath = $this->path;
    do {
      end($linePath); $lastIndentInParentPath = key($linePath);
      if ($indent <= $lastIndentInParentPath) array_pop ($linePath);
    } while ($indent <= $lastIndentInParentPath);
    return $linePath;
  }


  private function clearBiggerPathValues ($indent) {


    if ($indent == 0) $this->path = array();
    if (empty ($this->path)) return true;

    foreach ($this->path as $k => $_) {
      if ($k > $indent) unset ($this->path[$k]);
    }

    return true;
  }


  private static function isComment ($line) {
    if (!$line) return false;
    if ($line[0] == '#') return true;
    if (trim($line, " \r\n\t") == '---') return true;
    return false;
  }

  private static function isEmpty ($line) {
    return (trim ($line) === '');
  }


  private function isArrayElement ($line) {
    if (!$line) return false;
    if ($line[0] != '-') return false;
    if (strlen ($line) > 3)
      if (substr($line,0,3) == '---') return false;
    
    return true;
  }

  private function isHashElement ($line) {
    return strpos($line, ':');
  }

  private function isLiteral ($line) {
    if ($this->isArrayElement($line)) return false;
    if ($this->isHashElement($line)) return false;
    return true;
  }


  private static function unquote ($value) {
    if (!$value) return $value;
    if (!is_string($value)) return $value;
    if ($value[0] == '\'') return trim ($value, '\'');
    if ($value[0] == '"') return trim ($value, '"');
    return $value;
  }

  private function startsMappedSequence ($line) {
    return ($line[0] == '-' && substr ($line, -1, 1) == ':');
  }

  private function returnMappedSequence ($line) {
    $array = array();
    $key         = self::unquote(trim(substr($line,1,-1)));
    $array[$key] = array();
    $this->delayedPath = array(strpos ($line, $key) + $this->indent => $key);
    return array($array);
  }

  private function returnMappedValue ($line) {
    $array = array();
    $key         = self::unquote (trim(substr($line,0,-1)));
    $array[$key] = '';
    return $array;
  }

  private function startsMappedValue ($line) {
    return (substr ($line, -1, 1) == ':');
  }
  
  private function isPlainArray ($line) {
    return ($line[0] == '[' && substr ($line, -1, 1) == ']');
  }
  
  private function returnPlainArray ($line) {
    return $this->_toType($line); 
  }  

  private function returnKeyValuePair ($line) {
    $array = array();
    $key = '';
    if (strpos ($line, ':')) {
      // It's a key/value pair most likely
      // If the key is in double quotes pull it out
      if (($line[0] == '"' || $line[0] == "'") && preg_match('/^(["\'](.*)["\'](\s)*:)/',$line,$matches)) {
        $value = trim(str_replace($matches[1],'',$line));
        $key   = $matches[2];
      } else {
        // Do some guesswork as to the key and the value
        $explode = explode(':',$line);
        $key     = trim($explode[0]);
        array_shift($explode);
        $value   = trim(implode(':',$explode));
      }
      // Set the type of the value.  Int, string, etc
      $value = $this->_toType($value);
      if ($key === '0') $key = '__!YAMLZero';
      $array[$key] = $value;
    } else {
      $array = array ($line);
    }
    return $array;

  }


  private function returnArrayElement ($line) {
     if (strlen($line) <= 1) return array(array()); // Weird %)
     $array = array();
     $value   = trim(substr($line,1));
     $value   = $this->_toType($value);
     $array[] = $value;
     return $array;
  }


  private function nodeContainsGroup ($line) {    
    $symbolsForReference = 'A-z0-9_\-';
    if (strpos($line, '&') === false && strpos($line, '*') === false) return false; // Please die fast ;-)
    if ($line[0] == '&' && preg_match('/^(&['.$symbolsForReference.']+)/', $line, $matches)) return $matches[1];
    if ($line[0] == '*' && preg_match('/^(\*['.$symbolsForReference.']+)/', $line, $matches)) return $matches[1];
    if (preg_match('/(&['.$symbolsForReference.']+)$/', $line, $matches)) return $matches[1];
    if (preg_match('/(\*['.$symbolsForReference.']+$)/', $line, $matches)) return $matches[1];
    if (preg_match ('#^\s*<<\s*:\s*(\*[^\s]+).*$#', $line, $matches)) return $matches[1];
    return false;

  }

  private function addGroup ($line, $group) {
    if ($group[0] == '&') $this->_containsGroupAnchor = substr ($group, 1);
    if ($group[0] == '*') $this->_containsGroupAlias = substr ($group, 1);
    //print_r ($this->path);
  }

  private function stripGroup ($line, $group) {
    $line = trim(str_replace($group, '', $line));
    return $line;
  }
}

// Enable use of Spyc from command line
// The syntax is the following: php spyc.php spyc.yaml

define ('SPYC_FROM_COMMAND_LINE', false);

do {
  if (!SPYC_FROM_COMMAND_LINE) break;
  if (empty ($_SERVER['argc']) || $_SERVER['argc'] < 2) break;
  if (empty ($_SERVER['PHP_SELF']) || $_SERVER['PHP_SELF'] != 'spyc.php') break;
  $file = $argv[1];
  printf ("Spyc loading file: %s\n", $file);
  print_r (spyc_load_file ($file));
} while (0);

/* is_mobile()
 * Shitty mobile device detection based on shitty user agent strings.
 * 
 * Copyright (C) 2009-2012 Scott Zeid
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * Except as contained in this notice, the name(s) of the above copyright holders
 * shall not be used in advertising or otherwise to promote the sale, use or
 * other dealings in this Software without prior written authorization.
 */

/**
 * Determine whether the user agent represents a mobile device.
 * 
 * The user can use the following query string parameters to override this
 * function's output:
 *   * !mobile, nomobile - Cause this function to return False.
 *   * mobile - Cause this function to return True.
 *   * mobile=[...], device=[...] - Override the device type.
 * 
 * device=[...] takes precedence over mobile=[...].
 * 
 * Valid device types are firefox-tablet, firefox, chrome-tablet, chrome,
 * android-tablet, android, webos, tablet, unknown, apple, and apple-tablet
 * (listed in descending order of the author's personal preference).  Android
 * tablets and iPads are not considered to be mobile devices, but is_mobile()
 * will still return a device name ending in "-tablet", as appropriate for the
 * device in question.
 *
 * If the user is running Firefox Mobile, the device name would be "firefox",
 * or "firefox-tablet" if it is a tablet.  The same is true for users using
 * Chrome, except (obviously) "firefox" would be replaced with "chrome".
 * Although it has been discontinued for a while, support for the HP Touchpad
 * may be added in the future; its device name would be "webos-tablet".
 * 
 * @param bool $return_device Return a string representing the type of device.
 * @param bool $use_get Allow overriding default behavior using query strings.
 * @return mixed If $return_device is false, returns a boolean value.
 */
function is_mobile($return_device = False, $use_get = True) {
 # config
 $user_agent = $_SERVER["HTTP_USER_AGENT"];
 $nomobile = False; $forcemobile = False; $forcedevice = "";
 if ($use_get) {
  if (isset($_GET["!mobile"]) || isset($_GET["nomobile"]))
   $nomobile = True;
  elseif (isset($_GET["mobile"])) {
   $forcedevice = strtolower($_GET["mobile"]);
   if (!stristr($forcedevice, "tablet") && $forcedevice != "ipad")
    $forcemobile = True;
   if (!$forcedevice) $forcedevice = "unknown";
  }
  if (!empty($_GET["device"])) {
   $forcedevice = strtolower($_GET["device"]);
   if (!stristr($forcedevice, "tablet") && $forcedevice != "ipad") {
    $forcemobile = True;
    $nomobile = False;
   }
  }
 }
 # is mobile device?
 if (((
     (stristr($user_agent, "Android") && !stristr($user_agent, "Android 3.") &&
      stristr($user_agent, "Mobile")) ||
     stristr($user_agent, "webOS") ||
     ((stristr($user_agent, "Firefox") || stristr($user_agent, "Fennec")) &&
      stristr($user_agent, "Mobile")) ||
     stristr($user_agent, "iPhone") || stristr($user_agent, "iPod")
    ) && !stristr($user_agent, "Tablet") && $nomobile == False) ||
   $forcemobile == True)
  $mobile = True;
 else
  $mobile = False;
 # which mobile device
 $device = "unknown";
 if (stristr($user_agent, "Android")) {
  if (!stristr($user_agent, "Mobile") || stristr($user_agent, "Android 3."))
   $device = "android-tablet";
  else $device = "android";
  if (stristr($user_agent, "Chrome"))
   $device = str_replace("android", "chrome", $device);
 }
 if (stristr($user_agent, "Firefox") || stristr($user_agent, "Fennec")) {
  if (stristr($user_agent, "Tablet")) $device = "firefox-tablet";
  else $device = "firefox";
 }
 if (stristr($user_agent, "webOS")) $device = "webos";
 if (stristr($user_agent, "iPhone") || stristr($user_agent, "iPod"))
  $device = "apple";
 if (stristr($user_agent, "iPad")) $device = "apple-tablet";
 if ($forcedevice != "") $device = $forcedevice;
 if (stristr($forcedevice, "fennec"))
  $device = str_replace("fennec", "firefox", $device);
 if ($forcedevice == "iphone" || $forcedevice == "ipod") $device = "apple";
 if (stristr($forcedevice, "ipad")) $device = "apple-tablet";
 if (((!$mobile && !$forcemobile) || $nomobile || $forcedevice === "") &&
     !stristr($device, "tablet"))
  $device = "";
 # return value
 if ($return_device == False) return $mobile;
 else return $device;
}



 
/* Portal                                                                   {{{1
 * 
 * Copyright (C) 2006-2018 Scott Zeid
 * https://code.s.zeid.me/portal
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * Except as contained in this notice, the name(s) of the above copyright holders
 * shall not be used in advertising or otherwise to promote the sale, use or
 * other dealings in this Software without prior written authorization.
 */

// portal-data path and $debug  {{{1

/* Relative path to the settings folder; default is "portal-data".
 * This should be the same on both the filesystem and in URLs.
 * Use "." for the current directory.
 */
if (!isset($CONFIG_DIR))
 $CONFIG_DIR = "portal-data";

// Set to True to get the $portal array when visiting the portal
// Use only for debugging purposes.
$debug = False;

////////////////////////////////////////////////////////////////////////////////

// Setup  {{{1

// Workaround for templates raising fatal errors in PHP >= 5.4.0 when
// date.timezone is not set.  If that is the case, then this line will
// raise a warning.
date_default_timezone_set(date_default_timezone_get());





// Configuration loading and sanitation  {{{1
$portal = spyc_load(
           str_replace("\r\n", "\n", str_replace("\r", "\n", file_get_contents(
            "$CONFIG_DIR/settings.yaml"
           )))
          );
$portal["CONFIG_DIR"] = $CONFIG_DIR;
$name = $portal["name"] = $portal["name"];
$theme = $portal["theme"] = $portal["theme"];
if (!isset($portal["banner"]))
 $portal["banner"] = array("type" => "text", "content" => $name);
$portal["banner"]["type"] = strtolower($portal["banner"]["type"]);
if (!in_array($portal["banner"]["type"], array("text", "image", "none")))
 $portal["banner"]["type"] = "text";
$use_templum_for_banner_content = isset($portal["banner"]["content"]) &&
                                  $portal["banner"]["type"] != "none";

$openid_enabled = !empty($portal["openid"]["xrds"]) &&
                  ((!empty($portal["openid"]["provider"]) &&
                    !empty($portal["openid"]["local_id"])) ||
                   (!empty($portal["openid"]["server"]) &&
                    !empty($portal["openid"]["delegate"])));

$ga_enabled = !empty($portal["google-analytics"]["account"]) &&
              !empty($portal["google-analytics"]["style"]) &&
              in_array($portal["google-analytics"]["style"],array("new","old"));

$request_uri = (!empty($_SERVER["REQUEST_URI"])) ? $_SERVER["REQUEST_URI"] : "";
$url_scheme = ((!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] != "off")
               || (!empty($_SERVER["HTTP_X_FORWARDED_PROTO"])
                   && $_SERVER["HTTP_X_FORWARDED_PROTO"] == "https")
               || (!empty($_SERVER["HTTP_X_FORWARDED_PROTOCOL"])
                   && $_SERVER["HTTP_X_FORWARDED_PROTOCOL"] == "https")
               || (!empty($_SERVER["HTTP_X_FORWARDED_SSL"])
                   && $_SERVER["HTTP_X_FORWARDED_SSL"] == "on") 
               || (!empty($_SERVER["HTTP_FRONT_END_HTTPS"])
                   && $_SERVER["HTTP_FRONT_END_HTTPS"] == "on")) ? 
              "https" : "http";
if (!empty($portal["url-root"]))
 $url_root = $portal["url-root"] = rtrim($portal["url-root"], "/");
else {
 $url_root = "$url_scheme://{$_SERVER["HTTP_HOST"]}";
 $url_root .= implode("/",explode("/", $_SERVER["PHP_SELF"], -1));
 $portal["url-root"] = $url_root;
}

// Mobile device detection  {{{1
$mobile = is_mobile(False, True);
$device = is_mobile(True, True);

// Template namespace  {{{1
$namespace = array();
$names = explode(",", "CONFIG_DIR,device,ga_enabled,mobile,name,"
          ."openid_enabled,portal,request_uri,url_scheme");
foreach ($names as $n) {
 $namespace[$n] = &$$n;
}

// Debug output  {{{1
if ($debug) {
 header("Content-type: text/plain");
 print_r($portal);
 exit();
}

// JSON output  {{{1
if (isset($_GET["json"])) {
 // Update namespace
 $names = explode(",", "_403,_404,action,highlight,minibar,narrow,orientation,"
           ."request_uri,small,target,theme,url_root");
 foreach ($names as $n) {
  $namespace[$n] = &$$n;
 }
 if ($use_templum_for_banner_content)
  $portal["banner"]["content"] = tpl($portal["banner"]["content"], $namespace);
 if (is_array($portal["sites"])) {
  foreach ($portal["sites"] as $slug => &$site) {
   $keys = array("name", "icon", "url", "desc");
   foreach ($keys as $key) {
    if (!empty($site[$key])) {
     $site[$key] = $v = tpl($site[$key], $namespace);
     if ($key == "url") {
      if (strpos($v, "/") === 0 && strpos($v, "//") !== 0)
       $site[$key] = $v = "$url_scheme://{$_SERVER["HTTP_HOST"]}/$v";
     }
     if ($key == "icon") {
      if (preg_match("/(((http|ftp)s|file|data)?\:|\/\/)/i", $v))
       $site[$key] = $v = array("large" => $v, "small" => $v);
      else if (strpos($v, "/") === 0) {
       $v = "$url_scheme://{$_SERVER["HTTP_HOST"]}/$v";
       $site[$key] = $v = array("large" => $v, "small" => $v);
      } else {
       $site[$key] = $v = array(
        "large" => $url_root."/$CONFIG_DIR/icons/".$v,
        "small" => $url_root."/$CONFIG_DIR/icons/small/".$v
       );
      }
     }
    }
   }
  }
 }
 header("Content-Type: application/json; charset=utf-8");
 $data = array(
  "name"       => $portal["name"],
  "url"        => $portal["url"],
  "url-root"   => $portal["url-root"],
  "config-dir" => $CONFIG_DIR,
  "banner"     => $portal["banner"],
  "sites"      => $portal["sites"]
 );
 if (defined("JSON_PRETTY_PRINT") && defined("JSON_UNESCAPED_SLASHES"))
  echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
 else
  echo json_encode($data);
} // JSON output

// HTML output {{{1
else if (!isset($_GET["css"]) || !trim($_GET["css"]) != "") {
 
 $action = "index";
 if (isset($_GET["minibar"])) {
  $minibar = True;
  $action = "minibar";
  $highlight = (!empty($_GET["highlight"])) ? $_GET["highlight"] : "";
  $orientation = $portal["minibar-orientation"];
  if (!isset($_GET["horizontal"]) || !isset($_GET["vertical"])) {
   if (isset($_GET["horizontal"])) $orientation = "horizontal";
   elseif (isset($_GET["vertical"])) $orientation = "vertical";
  }
 } else
 $minibar = False;
 
 $target = (!empty($_GET["target"])) ? $_GET["target"] : $portal["link-target"];
 $theme = (!empty($_GET["theme"])) ? $_GET["theme"] : $theme;
 $narrow = (isset($_GET["narrow"])) ? True : $portal["narrow"];
 if (isset($_GET["!narrow"]) || isset($_GET["wide"])) $narrow = False;
 $small = (isset($_GET["small"])) ? True : $portal["small"];
 if (isset($_GET["!small"]) || isset($_GET["large"]) || isset($_GET["big"]))
  $small = False;
 $_403 = isset($_GET["403"]);
 $_404 = isset($_GET["404"]);
 if ($_403 || $_404) {
  $action = "error";
  $request_uri = $portal["url"];
  if      ($_403) header("HTTP/1.0 403 Forbidden");
  else if ($_404) header("HTTP/1.0 404 Not Found");
 }
 
 // Update namespace
 $names = explode(",", "_403,_404,action,highlight,minibar,narrow,orientation,"
           ."request_uri,small,target,theme,url_root");
 foreach ($names as $n) {
  $namespace[$n] = &$$n;
 }
 
 // Template expansion for config values
 if ($use_templum_for_banner_content)
  $portal["banner"]["content"] = tpl($portal["banner"]["content"], $namespace);
 if (isset($portal["custom-head-content"]))
  $portal["custom-head-content"] = tpl($portal["custom-head-content"],
                                       $namespace);
 else
  $portal["custom-head-content"] = "";
 if (isset($portal["custom-footer-content"]))
  $portal["custom-footer-content"] = tpl($portal["custom-footer-content"],
                                         $namespace);
 else
  $portal["custom-footer-content"] = "";
 if (is_array($portal["sites"])) {
  foreach ($portal["sites"] as $slug => &$site) {
   $keys = array("name", "icon", "url", "desc");
   foreach ($keys as $key) {
    if (!empty($site[$key]))
     $site[$key] = tpl($site[$key], $namespace);
   }
  }
 }
 
 // Yadis XRDS header; needs to be sent as a proper header instead of a meta
 // tag in order to validate as HTML5
 if ($openid_enabled)
  header("X-XRDS-Location: ".rawurlencode($portal["openid"]["xrds"]));
 
 // HTML template  {{{1
 echo htmlsymbols(tpl(<<<HTML
<!DOCTYPE html>

<html>
 <head>
  <meta charset="utf-8" />
  <!--
  
   Portal
   
   Copyright (C) 2006-2018 Scott Zeid
   http://code.srwz.us/portal
   
   Permission is hereby granted, free of charge, to any person obtaining a copy
   of this software and associated documentation files (the "Software"), to deal
   in the Software without restriction, including without limitation the rights
   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
   copies of the Software, and to permit persons to whom the Software is
   furnished to do so, subject to the following conditions:
   
   The above copyright notice and this permission notice shall be included in
   all copies or substantial portions of the Software.
   
   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
   THE SOFTWARE.
   
   Except as contained in this notice, the name(s) of the above copyright holders
   shall not be used in advertising or otherwise to promote the sale, use or
   other dealings in this Software without prior written authorization.
  
  -->
  <title>{{\$portal["name"]}}</title>
@if (file_exists("\$CONFIG_DIR/favicon.png")):
  <link rel="shortcut icon" type="image/png" href="{{\$CONFIG_DIR}}/favicon.png" />
@endif
@if (\$_403 || \$_404):
  <base href="{{\$url_root}}/" />
@endif
@if (\$openid_enabled):
@ /* OpenID */
  <!--openid-->
@if (!empty(\$portal["openid"]["provider"])):
   <link rel="openid2.provider" href="{{\$portal["openid"]["provider"]}}" />
@endif
@if (!empty(\$portal["openid"]["local_id"])):
   <link rel="openid2.local_id" href="{{\$portal["openid"]["local_id"]}}" />
@endif
@if (!empty(\$portal["openid"]["server"])):
   <link rel="openid.server" href="{{\$portal["openid"]["server"]}}" />
@endif
@if (!empty(\$portal["openid"]["delegate"])):
   <link rel="openid.delegate" href="{{\$portal["openid"]["delegate"]}}" />
@endif
  <!--/openid-->
@endif // OpenID
  <meta name="generator" content="Portal by Scott Zeid; X11 License; http://code.srwz.us/portal" />
  <link rel="stylesheet" type="text/css" href="{{\$url_scheme}}://fonts.googleapis.com/css?family=Ubuntu:regular,italic,bold,bolditalic" />
  <link rel="stylesheet" type="text/css" href="?css={{\$theme}}&amp;.css" />
@if (\$mobile):
  <meta name="viewport" content="width=532; initial-scale=0.6; minimum-scale: 0.6" />
@endif
@if (\$ga_enabled && \$portal["google-analytics"]["style"] == "new"):
@ /* Google Analytics - New style */
  <script type="text/javascript">
   var _gaq = _gaq || [];
   _gaq.push(['_setAccount', '{{\$portal["google-analytics"]["account"]}}']);
   _gaq.push(['_trackPageview']);
   (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
   })();
  </script>
@endif // Google Analytics - New style
[[if (\$portal["custom-head-content"])
   echo indent(htmlsymbols(trim(\$portal["custom-head-content"], "\r\n")), 2)."\n";]]
 </head>
 <body id="action_{{\$action}}"[[if (\$mobile || \$device) {
]] class="[[if (\$mobile) echo "mobile "; if (\$device) echo "device_\$device";]]"[[
}]]>
@if (\$minibar):
@ /* Minibar */
  <div id="minibar" class="{{\$orientation}}">
   [[

/* Minibar site list */
foreach (\$portal["sites"] as \$slug => &\$site) {
 if ((!isset(\$site["minibar"]) || \$site["minibar"] !== False) &&
     !empty(\$site["url"])) {
  \$code = "";
  if (\$orientation == "vertical") \$code .= "<div>";
  // Link
  \$code .= "<a href=\"".htmlentitiesu8(\$site["url"], True)."\" target=\"_blank\"";
  // Highlight
  if (\$highlight == \$slug) \$code .= ' class="highlight"';
  // Site name
  \$name = str_replace("\n", " ", htmlentitiesu8(strip_tags(\$site["name"]), False));
  \$name = str_replace("&amp;", "&", \$name);
  \$code .= " title=\"".\$name;
  // Site description
  if (isset(\$site["desc"]) && trim(\$site["desc"])) {
   \$desc = str_replace("\n", "&#x0a;",
                        htmlentitiesu8(strip_tags(\$site["desc"]), False));
   \$desc = str_replace("&amp;", "&", \$desc);
   \$code .= " &mdash; ".\$desc;
  }
  // Icon
  \$icon_url = htmlentitiesu8(\$site["icon"], True);
  if (preg_match("/(((http|ftp)s|file|data)?\:|\/\/)/i", \$site["icon"]))
   \$icon_url = \$icon_url;
  else if (strpos(\$site["icon"], "/") === 0)
   \$icon_url = "\$url_scheme://{\$_SERVER["HTTP_HOST"]}/\$icon_url";
  else
   \$icon_url = "\$CONFIG_DIR/icons/small/\$icon_url";
  \$code .= "\"><img src=\"\$icon_url\" alt=\"Icon\" /></a>";
  if (\$orientation == "vertical") \$code .= "</div>";
  echo \$code;
 }
}

]]

  </div>
@//Minibar
@else:
@ /* Normal mode */
@if (\$portal["banner"]["type"] != "none"):
@ /* Banner */
  <div id="header" class="{{\$portal["banner"]["type"]}}[[if (\$small) echo ' small';]]">
   <h1 id="title">
    <span>
     <a href="{{\$request_uri}}">
@if (\$portal["banner"]["type"] == "image"):
@ /* Image banner */
      <img src="[[echo htmlentitiesu8((!empty(\$portal["banner"]["content"]))
                        ? \$portal["banner"]["content"]
                        : "\$CONFIG_DIR/images/banner.png", True
                       );]]" alt="{{\$name}}" />
@else: // Image banner
@ /* Text banner */
[[echo indent(htmlsymbols((!empty(\$portal["banner"]["content"]))
               ? \$portal["banner"]["content"] : \$name), 6)."\n";]]
@endif // Text banner
     </a>
    </span>
   </h1>
  </div>
@endif // Banner
  <div id="body"[[if (\$narrow || \$small) {
]] class="[[if (\$narrow) echo "narrow"; if (\$narrow && \$small) echo " ";
            if (\$small) echo "small";]]"[[
}]]>
@if (\$_403):
   <p>You don't have permission to view this page.</p>
@elseif (\$_404):
   <p>The page you were looking for was not found.</p>
@else:
[[

/* Normal site list */
foreach (\$portal["sites"] as \$slug => &\$site) {
 if (!isset(\$site["index"]) || \$site["index"] !== False) {
  \$code = "";
  \$code .= "<p class=\"site".((!empty(\$site["url"]))?" has-url":"")."\">\n";
  // Link
  if (!empty(\$site["url"])) {
   \$code .= " <a href=\"".htmlentitiesu8(\$site["url"], True)."\"";
  // Link target
  if (\$target) \$code .= " target=\"\$target\"";
  \$code .= ">\n";
  } else
   \$code .= " <span>\n";
  // Image
  \$icon_url = htmlentitiesu8(\$site["icon"], True);
  if (preg_match("/(((http|ftp)s|file|data)?\:|\/\/)/i", \$site["icon"]))
   \$icon_url = \$icon_url;
  else if (strpos(\$site["icon"], "/") === 0)
   \$icon_url = "\$url_scheme://{\$_SERVER["HTTP_HOST"]}/\$icon_url";
  else
   \$icon_url = "\$CONFIG_DIR/icons".((\$small)?"/small":"")."/\$icon_url";
  \$code .= "  <span><img src=\"\$icon_url\" alt=\" \" />";
  // Site name
  \$code .= "<strong class=\"name\">".htmlsymbols(\$site["name"])."</strong></span>";
  // Site description
  if (isset(\$site["desc"]) && trim(\$site["desc"])) {
   \$code .= "<br />\n  <span class=\"desc\">";
   \$code .= str_replace("\n", "&#x0a;", htmlsymbols(\$site["desc"]))."</span>";
  }
  // Close stuff
  \$code .= "\n ".((!empty(\$site["url"])) ? "</a>" : "</span>")."\n</p>";
  echo indent(\$code, 3);
  echo "\n";
 }
}

]]
@endif
  </div>
  <div id="footer" class="footer[[if (\$small) echo " small";]]">
   <p>
    <a href="https://code.s.zeid.me/portal">Portal software</a>
    copyright &copy; [[echo copyright_year(2006);]] <a href="https://s.zeid.me/">Scott Zeid</a>.
   </p>
[[if (\$portal["custom-footer-content"])
   echo indent(htmlsymbols(trim(\$portal["custom-footer-content"], "\r\n")), 3)."\n";]]
@if (\$portal["show-validator-links"]):
@ /* W3C Validator links */
   <p>
    <a href="http://validator.w3.org/check?uri=referer">
     <img src="{{\$CONFIG_DIR}}/images/html5.png" alt="Valid HTML5" class="button_80x15" />
    </a>&nbsp;
    <a href="http://jigsaw.w3.org/css-validator/check/referer?profile=css3">
     <img src="{{\$CONFIG_DIR}}/images/css.png" alt="Valid CSS" class="button_80x15" />
    </a>
   </p>
@endif // W3C Validator links
  </div>
@if (\$ga_enabled && \$portal["google-analytics"]["style"] != "new"):
@ /* Google Analytics - Old style */
  <script type="text/javascript">
   var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
   document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
  </script>
  <script type="text/javascript">
   try{
    var pageTracker = _gat._getTracker("{{\$portal["google-analytics"]["account"]}}");
    pageTracker._trackPageview();
   } catch(err) {}
  </script>
@endif // Google Analytics - Old style
@endif // Normal link listing
 </body>
</html>
HTML
, $namespace));

} // HTML template and output

// CSS output  {{{1
else {
 $theme = $portal["themes"][$_GET["css"]];
 $custom_css = (isset($theme["custom_css"])) ? $theme["custom_css"] : "";
 $theme = tpl_r($theme);

 // Update namespace
 $names = explode(",", "theme");
 foreach ($names as $n) {
  $namespace[$n] = &$$n;
 }
 
 header("Content-type: text/css");

 // CSS template  {{{1
 echo tpl(<<<CSS
/* Portal
 * 
 * Copyright (C) 2006-2018 Scott Zeid
 * http://code.srwz.us/portal
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * 
 * Except as contained in this notice, the name(s) of the above copyright holders
 * shall not be used in advertising or otherwise to promote the sale, use or
 * other dealings in this Software without prior written authorization.
 */

body {
 margin: 0px;
 background: {{\$theme["bg"]}};
 font-family: "Ubuntu", "DejaVu Sans", "Bitstream Vera Sans", "Verdana",
              sans-serif;
 font-size: 1em;
 text-align: center;
 color: {{\$theme["fg"][0]}};
}
:focus {
 outline: none;
}
a {
 color: {{\$theme["fg"][1]}}; text-decoration: none;
}
a:hover, .site.has-url:hover * {
 color: {{\$theme["fg"][2]}};
}
a:active, .site.has-url:active * {
 color: {{\$theme["fg"][3]}};
}
h1, .h1 {
 font-size: 2.5em;
 font-weight: normal;
}
h2, .h2, .name {
 font-size: 1.5em;
 font-weight: normal;
}
img {
 border-style: none;
}
.monospace {
 font-family: "Courier New", "Courier", monospace;
}
.small {
 font-size: .6em;
}

#header {
 margin: 1em;
}
 #header.text #title span {
  background: {{\$theme["logo_bg"]}};
@if (stripos(\$theme["logo_bg"], "transparent") === False):
  border: 1px solid {{\$theme["logo_border"]}};
@endif
  padding: .2em;
 }
 #header a {
  color: {{\$theme["fg"][0]}};
 }
#body {
 width: 500px;
 margin: 1em auto;
}
#body.narrow {
 width: 250px;
}
#body.small {
 width: 312px;
 margin: 0.5em auto;
}
#body.narrow.small {
 width: 156px;
}
 .site {
  margin-top: 1em; margin-bottom: 1em;
  text-align: left;
  background: {{\$theme["link_bg"]}};
 }
 .site.has-url:hover, #minibar a:hover {
  background: {{\$theme["link_bg_h"]}};
 }
 .site.has-url:active, #minibar a:active {
  background: {{\$theme["link_bg_a"]}};
 }
  .site a, .site > span {
   display: block;
  }
  .site img {
   width: 32px; height: 32px;
   margin: 10px;
   vertical-align: top;
   /*background: {{\$theme["ico_bg"]}};*/
  }
  .small .site img {
   width: 16px; height: 16px;
   margin: 6px;
  }
  .site .name {
   display: inline-block;
   width: 436px;
   margin-right: 12px;
   padding: 12px 0;
   vertical-align: middle;
  }
  .narrow .site .name {
   width: 186px;
  }
  .small .site .name {
   width: 276px;
   margin-right: 8px;
   padding: 5px 0 7px 0;
  }
  .narrow.small .site .name {
   width: 120px;
  }
  .site .desc {
   display: block;
   margin-left: 52px; margin-right: 12px;
   padding-bottom: 12px;
   text-align: justify;
  }
  .small .site .desc {
   margin-left: 28px; margin-right: 8px;
   padding-bottom: 8px;
  }
#footer {
 font-size: .6em;
}
#footer.small {
 font-size: .5em;
}
.button_80x15 {
 width: 80px; height: 15px;
}

.mobile {
 background-attachment: scroll;
}
 .mobile #body {
  font-size: 1.5em;
  width: 484px;
 }
 .mobile #body.narrow {
  width: 363px;
 }
 .mobile #body.small {
  width: 363px;
  font-size: 0.9em;
 }
 .mobile #body.narrow.small {
  width: 230px;
 }
  .mobile .site img {
   width: 48px; height: 48px;
  }
  .mobile .small .site img {
   width: 24px; height: 24px;
  }
  .mobile .site .name {
   width: 396px;
  }
  .mobile .narrow .site .name {
   width: 283px;
  }
  .mobile .small .site .name {
   width: 319px;
  }
  .mobile .narrow.small .site .name {
   width: 186px;
  }
  .mobile .site .desc {
   margin-left: 68px;
  }
  .mobile .small .site .desc {
   margin-left: 36px;
  }
  .mobile .button_80x15 {
   width: 120px; height: 22.5px;
  }
 .mobile #footer {
  font-size: 1.2em;
 }
 .mobile.device_apple #footer {
  font-size: 0.75em;
 }

#action_minibar {
 overflow: hidden;
}
#action_minibar.horizontal {
 background-image: none;
}
#minibar div, #minibar.horizontal {
 margin-top: -1px;
}
#minibar a {
 width: 24px; height: 25px;
 margin: 0;
 padding: 4px 4px 0 4px;
}
#minibar.horizontal a {
 height: 26px;
 padding-bottom: 5px;
}
#minibar a.highlight {
 background: {{\$theme["link_bg"]}};
}
 #minibar a img {
  margin-top: 4px;
 }
#action_minibar.mobile {
 font-size: 1em;
}
CSS
, $namespace, False);

if ($custom_css) echo "\n\n".tpl($custom_css, $namespace, False);

} // CSS template and output

// Helper functions  {{{1

function copyright_year($start = Null, $end = Null) {
 if (!$start) $start = date("Y");
 if (!$end) $end = date("Y");
 if ($start == $end) return $start;
 return $start."-".$end;
}

function htmlentitiesu8($s, $encode_twice = False) {
 if ($encode_twice) $s = htmlentitiesu8($s, False);
 return htmlentities($s, ENT_COMPAT, "UTF-8");
}

function htmlspecialcharsu8($s, $encode_twice = False) {
 if ($encode_twice) $s = htmlspecialcharsu8($s, False);
 return htmlspecialchars($s, ENT_COMPAT, "UTF-8");
}

function htmlsymbols($s, $encode_twice = False) {
 return htmlspecialchars_decode(htmlentitiesu8($s, $encode_twice));
}

function indent($s, $n) {
 $s = explode("\n", $s);
 foreach ($s as $i => $l) {
  $s[$i] = str_repeat(" ", $n).$l;
 }
 return implode("\n", $s);
}

function tpl($s, $namespace = Null, $esc = True) {
 global $portal;
 if (is_null($namespace)) $namespace = $portal;
 return Templum::templateFromString($s, $esc)->render($namespace);
}

function tpl_r($s, $namespace = Null, $esc = True) {
 if (is_array($s)) {
  foreach ($s as $k => &$v) {
   if (is_array($v) || is_string($v))
    $s[$k] = tpl_r($v, $namespace, $esc);
  }
  return $s;
 }
 elseif (is_string($s))
  return tpl($s, $namespace, $esc);
 else
  return $s;
}

// Helper functions

// vim: set fdm=marker:  "{{{1
?>
