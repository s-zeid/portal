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

?>
