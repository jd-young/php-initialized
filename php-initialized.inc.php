<?php
if (!isset($trace)) $trace = false;


/**
 * A class that allows safe access to tokens, to attempt to avoid nasty 'Fatal error: Unsupported operand types in ...'
 * errors that stop the php-initialised execution.
 */
class Token
{
    private $_id;
    private $_name;
    private $_line_nr;
    private $_char;

    public function __construct($token)
    {
        if (is_array($token))
        {
            // An array where [0] is the ID, ->name() is the name, and [2] is the line number of the token.
            $this->_id = $token[0];
            $this->_name = trim($token[1]);
            $this->_line_nr = $token[2];
            $this->_char = '';
        }
        else
        {
            $this->_id = 0;
            $this->_name = '';
            $this->_line_nr = 0;
            $this->_char = $token;
        }        
    }
    
    public function id()        { return $this->_id; }
    public function char()      { return $this->_char; }
    public function name()      { return $this->_name; }
    public function line_nr()   { return $this->_line_nr; }
    public function token_str()
    {
        return $this->_id > 0 
                    ? token_name($this->_id) . " " . $this->_name 
                    : $this->_char;
    }
}


/** Print usage of uninitialized variables
 * @param string $filename      name of the processed file
 * @param array [$initialized]  initialized variables in keys
 * @param string [$function]    inside a function definition
 * @param string [$class]       inside a class definition
 * @param bool [$in_string]     inside a " string
 * @param array [$tokens]       result of token_get_all() without whitespace, computed from $filename if null
 * @param int [$i]              position in $tokens
 * @param int [$single_command] parse only single command, number is current count($function_calls)
 * @return mixed $initialized in the end of code, $i in the end of block.
 *
 * @link http://code.google.com/p/php-initialized/
 * @author Jakub Vrana, http://www.vrana.cz/
 * @copyright 2008 Jakub Vrana
 * 
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0.
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other).
 */
function check_variables($filename, 
                         $initialized = array(), 
                         $function = "", 
                         $class = "", 
                         $in_string = false, 
                         $tokens = NULL, 
                         $i = 0, 
                         $single_command = null)
{
    global $trace;

    // The global variables: a hash of '[class::]name' against boolean (true = initialised).
	static $globals;
	
	// A hash of function names against a hash of either a boolean (true if it has been initialised, false otherwise),
	// or a string (a warning string containing the filename / line number of where the uninitialised variable was 
	// found).
	static $function_globals;
	
    // A stack of lists of parameters that are passed by reference.
	static $function_parameters;
	
    // A stack of function parameters.  Every time we hit a function call, we push the function parameters onto the 
    // stack.
	static $function_calls;

    // ???
	static $extends;
	
	// A hash of declared class fields (format is class::$name).
	static $fields;

	if (func_num_args() < 2) 
    {
		$globals = array('$php_errormsg' => true,
		                 '$_SERVER' => true,
		                 '$_GET' => true,
		                 '$_POST' => true,
		                 '$_COOKIE' => true,
		                 '$_FILES' => true, 
		                 '$_ENV' => true, 
		                 '$_REQUEST' => true, 
		                 '$_SESSION' => true); // not $GLOBALS
		$function_globals = array();
		$function_parameters = array();
		$function_calls = array();
		$extends = array();
		$fields = array();
	}

	if ($tokens == NULL) 
	{
		$tokens = _read_tokens($filename);
	}

    if ($trace)
    {	
    	echo "check_variables($filename, (initialized), $function , $class, $in_string, (tokens), $i, $single_command)\n";
    	print_r($initialized);
	}
	
	$in_list = false;
	$shortcircuit = array();
	for (; $i < count($tokens); $i++)
	{
		$token = $tokens[$i];
        if ($trace) echo "Token $i: " . $token->token_str() . "\n";
		
		if ($token->char() === ')' || $token->char() === ';' || $token->char() === ',')
		{
			while ($shortcircuit && end($shortcircuit) >= count($function_calls))
			{
				array_pop($shortcircuit);
			}
			foreach ($initialized as $key => $val)
			{
			    // TODO: Is this so as not to report the same variable over and over?
				$initialized[$key] = true; // confirm assignment
			}
		}
		
		// variables
		if ($token->id() === T_VARIABLE)
		{
			$variable = $token->name();
            if ($trace)
            {
			    echo "VARIABLE = '$variable': class = '$class' INITIALIZED\n";
			    print_r($initialized);
//    			echo "GLOBALS\n";
//    			print_r($globals);
    		    echo "Function '$function' parameters:\n";
    		    print_r($function_parameters);
    		    echo "Fields...\n";
    		    print_r($fields);
            }
            
			if ($variable == '$GLOBALS' && 
			    $tokens[$i+1]->char() === '[' && 
			    $tokens[$i+2]->id() === T_CONSTANT_ENCAPSED_STRING && 
			    $tokens[$i+3]->char() === ']') 
			{
				$variable = _strip_str($tokens[$i+2]->name());
				if (isset($function_globals[$function]['$' . $variable])) {
					$variable = '$' . $variable;
				} else {
					$function_globals[$function][$variable] = false;
				}
				$i += 3;
			}
			
            if ($class && $variable === '$this' && !empty($initialized['$this']))
			{
			    if ($tokens[$i + 1]->id() === T_OBJECT_OPERATOR && $tokens[$i + 2]->id() == T_STRING)
			    {
			        // Function call or reference a field?
			        $func_or_field = $tokens[$i + 2]->name();
			        if (!isset($function_parameters["$class::$func_or_field"]))
			        {
			            $field= $func_or_field;
                        if (empty($fields["$class::\$$field"])) 
    			        {
    			            $line_nr = $token->line_nr();
        					echo "Uninitialized field $class::\$$field in $filename on line $line_nr\n";
    			        }
    			    }
			    }
			}
			
			// JY: I don't like looking backwards, shouldn't this already be dealt with?
			elseif ($tokens[$i-1]->id() === T_DOUBLE_COLON || $variable == '$GLOBALS') 
			{
				// ignore static properties and complex globals
				if ($trace) echo "Ignore static properties and complex globals\n";
			}
			elseif (isset($function_globals[$function][$variable]))
            {
				if (!$function_globals[$function][$variable]) 
				{
				    $line_nr = $token->line_nr();
					$function_globals[$function][$variable] = ($in_list || $tokens[$i+1]->char() === '=' 
					                                                ? true 
					                                                : "in $filename on line $line_nr");
				}
			}
			elseif ($in_list || $tokens[$i+1]->char() === '=' || !empty($function_calls[count($function_calls) - 1][0]))
			{
			    if ($trace) 
			    {
    			    $next_tok = $tokens[$i+1]->char() === '=';
    			    if (count($function_calls) > 0)
    			    {
        			    $peeked = $function_calls[count($function_calls) - 1];
        			    echo "peeked\n";
        			    print_r($peeked);
        			    echo "peeked is $peeked[0]\n";
        			}
        			else
        			{
        			    echo "function call stack is empty\n";
        			}
        	    }
				if (!$shortcircuit && !isset($initialized[$variable]))
				{
					$initialized[$variable] = false;
				}
			}
			elseif (empty($initialized[$variable]) && !isset($globals[$variable]))
			{
			    if ($trace) 
			    {
    			    echo "Check function_parameters for '$function':\n";
    			    print_r($function_parameters);
    			}
				if (isset($function_parameters[$function][$variable]))
				{
					$function_parameters[$function][$variable] = false;
				}
				else
				{
				    $line_nr = $token->line_nr();
					echo "Uninitialized variable $variable in $filename on line $line_nr\n";
					$initialized[$variable] = true;
				}
			}
		}
		elseif ($token->id() === T_LIST || $token->id() === T_UNSET) 
		{
			$in_list = true;
		}
		
		// foreach
		elseif ($token->id() === T_AS)
		{
			$locals = array();
			do
			{
				$i++;
				if ($tokens[$i]->id() === T_VARIABLE)
				{
					$locals[$tokens[$i]->name()] = true;
				}
			} while ($tokens[$i]->char() !== ')');
			array_pop($function_calls);
			$size = count($function_calls);
//			echo "foreach: calling check_variables with single_command = $size\n";
			$i = check_variables($filename, $initialized + $locals, $function, $class, $in_string, $tokens, $i+1, count($function_calls));
//		    echo "foreach check_variables returned $i\n";
		}
		
		// catch
		elseif ($token->id() === T_CATCH)
		{
			$locals = array();
			do
			{
				$i++;
				if ($tokens[$i]->id() === T_VARIABLE)
				{
					$locals[$tokens[$i]->name()] = true;
				}
			} while ($tokens[$i+1]->char() !== '{');
			array_pop($function_calls);
			$i = check_variables($filename, $initialized + $locals, $function, $class, $in_string, $tokens, $i+2);
		} 
		
		// global
		elseif ($token->id() === T_GLOBAL && $function)
		{
			do
			{
				$i++;
				if ($tokens[$i]->id() === T_VARIABLE)
				{
					$function_globals[$function][$tokens[$i]->name()] = false;
				}
			} while ($tokens[$i]->char() !== ';');
				}
		
		// static
		elseif ($token->id() === T_STATIC && $tokens[$i+1]->id() !== T_FUNCTION && $tokens[$i+2]->id() !== T_FUNCTION) 
		{
			do 
			{
				$i++;
				if ($function && $tokens[$i]->id() === T_VARIABLE)
				{
					$initialized[$tokens[$i]->name()] = true;
				}
			} while ($tokens[$i]->char() !== ';');
				}
		
		// function definition
		elseif ($token->id() === T_FUNCTION)
		{
			if (in_array(T_ABSTRACT, array($tokens[$i-1]->id(), $tokens[max(0, $i-2)]->id(), $tokens[max(0, $i-3)]->id()), true))
			{
				do
				{
					$i++;
				} while ($tokens[$i+1]->char() !== ';');
			}
			else
			{
				$locals = $class && $tokens[$i-1]->id() !== T_STATIC && $tokens[$i-2]->id() !== T_STATIC
				                ? array('$this' => true) 
				                : array();
				$i++;
				if ($tokens[$i]->char() === '&')
				{
					$i++;
				}
				$name = $tokens[$i]->char() === '(' 
				            ? '' 
				            : ($class ? "$class::" : "") . $tokens[$i]->name();
				if ($name) 
				{
					$function_parameters[$name] = array();
				}
				do
				{
					$i++;
					if ($tokens[$i]->id() === T_VARIABLE)
					{
        				if ($name) 
        				{
        				    $var_name = $tokens[$i]->name();
        				    $is_ref = $tokens[$i-1]->char() === '&';
    						$function_parameters[$name][$var_name] = $is_ref;
    						if ($trace) echo "function_parameters[$name][$var_name] = $is_ref\n";
    				    }
						if ($tokens[$i-1]->char() !== '&')
						{
							$locals[$tokens[$i]->name()] = true;
						}
					}
				} while ($tokens[$i+1]->char() !== '{');
				if ($trace) 
    			{
        			if (isset($function_parameters[$name]))
        			{
        			    echo "function_parameters[$name]:\n";
        			    print_r($function_parameters[$name]);
						}
                    else
                    {
        			    echo "function_parameters[$name]: not set\n";
					}
				}
				$i = check_variables($filename, $locals, $name, ($function ? "" : $class), $in_string, $tokens, $i+2);
			}
		}
		
		// function call
		elseif ($token->id() === T_STRING && $tokens[$i+1]->char() === '(')
		{
			$name = $token->name();
			$class_name = "";
			if (($tokens[$i-1]->id() === T_DOUBLE_COLON    && $tokens[$i-2]->name() === 'self') ||
			    ($tokens[$i-1]->id() === T_OBJECT_OPERATOR && $tokens[$i-2]->name() === '$this')) 
			{
				$class_name = $class;
			    if ($trace) echo "'self::' or '\$this' -> $class_name\n";
			}
			elseif ($tokens[$i-1]->id() === T_DOUBLE_COLON && $tokens[$i-2]->name() === 'parent')
			{
				$class_name = $extends[$class];
			    if ($trace) echo "'parent::' -> $class_name\n";
			}
			elseif ($tokens[$i-1]->id() === T_DOUBLE_COLON && $tokens[$i-2]->id() === T_STRING)
			{
				$class_name = $tokens[$i-2]->name();
			    if ($trace) echo "named class -> $class_name'\n";
            }
			elseif (!strcasecmp($name, "define") && 
			        $tokens[$i+2]->id() === T_CONSTANT_ENCAPSED_STRING &&
			        $tokens[$i+3]->char() === ',')
			{
			    // constant definition
				$globals[_strip_str($tokens[$i+2]->name())] = true;
			}
			elseif (!strcasecmp($name, "session_start")) 
			{
				$globals["SID"] = true;
			}
			$i++;
			if ($trace) echo "class_name = '$class_name'\n";
			if ($class_name ? method_exists($class_name, $name) : function_exists($name)) {
			    if ($trace) echo "Use reflection: $name\n";
				$reflection = $class_name 
				                    ? new ReflectionMethod($class_name, $name) 
				                    : new ReflectionFunction($name);
				$parameters = array();
				foreach ($reflection->getParameters() as $parameter)
				{
					$parameters[] = $parameter->isPassedByReference() 
					                    ? ($parameter->isVariadic()
					                        ? '$...'
					                        : '$' . $parameter->getName())
					                    : '';
				}
				
				$function_calls[] = $parameters;
				if ($trace)
				{
				    echo "function_calls[] = \$parameters\n";
				    print_r($parameters);
				}
			}
			else
			{
				if ($class_name)
				{
					while ($class_name && 
					       !isset($function_parameters["$class_name::$name"]) && 
					       isset($extends[$class_name])) 
                    {
						$class_name = $extends[$class_name];
					}
					$name = "$class_name::$name";
				}
                if ($trace) 
    			{
        			if (isset($function_parameters[$name]))
        			{
        			    echo "function_parameters[$name]:\n";
        			    print_r($function_parameters[$name]);
        		    }
                    else
                    {
        			    echo "function_parameters[$name]: not set\n";
                    }
    				echo "function stack (before)\n";
    				print_r($function_calls);
                }
				$function_calls[] = (isset($function_parameters[$name]) 
				                        ? array_values($function_parameters[$name]) 
				                        : array());
				if ($trace) 
				{
    				echo "function stack (after)\n";
    				print_r($function_calls);
                }

				if (!$function && isset($function_globals[$name])) {
					foreach ($function_globals[$name] as $variable => $info) {
						if ($info === true) {
							$initialized[$variable] = true;
						} elseif (is_string($info) && !isset($initialized[$variable])) {
						    $line_nr = $token->line_nr();
							echo "Uninitialized global $variable $info: called from $filename:$line_nr\n";
						}
					}
				}
			}
		
		// strings
		} elseif ($token->char() === '"') {
			$in_string = !$in_string;
		}

        // namespaces (pretty much ignored)
        elseif ($token->id() === T_NAMESPACE || $token->id() === T_USE) 
        {
            $i = _skip_to(';', $i, $tokens);
        }
		
		// constants
		elseif (!$in_string && $token->id() === T_STRING &&
		        !in_array($tokens[$i-1]->id(), array(T_OBJECT_OPERATOR, T_NEW, T_INSTANCEOF), true) &&
		        $tokens[$i+1]->id() !== T_DOUBLE_COLON)
		{
		    // not properties and classes
			$name = $token->name();
		    if ($trace) echo "Constants name = '$name'\n";
		    if ($tokens[$i-1]->id() === T_CONST)
		    {
				$globals[($class ? "$class::" : "") . $name] = true;
				}
    		else
    		{
				if ($tokens[$i-1]->id() === T_DOUBLE_COLON)
				{
					$name = (!strcasecmp($tokens[$i-2]->name(), "self")
					        ? $class 
					        : $tokens[$i-2]->name()) . "::$name"; //! extends
				}
				if (!defined($name) && !isset($globals[$name]))
				{
				    //! case-insensitive constants
				    $line_nr = $token->line_nr();
					echo "Uninitialized constant $name in $filename on line $line_nr\n";
				}
				}
			}
		
		// class
		elseif ($token->id() === T_CLASS) 
		{
			$i++;
			$token = $tokens[$i];
			while ($tokens[$i+1]->char() !== '{') 
			{
				if ($tokens[$i]->id() === T_EXTENDS)
				{
					$extends[$tokens[$i-1]->name()] = $tokens[$i+1]->name();
				}
				$i++;
			}
			$i = check_variables($filename, array(), $function, $token->name(), $in_string, $tokens, $i+2);
		}
		
		// Fields
		elseif (in_array($token->id(), array(T_VAR, T_PUBLIC, T_PRIVATE, T_PROTECTED), true) &&
		        $tokens[$i+1]->id() === T_VARIABLE)
		{
		    if ($class)     // We should be in a class, otherwise it's a compilation problem (not ours).
		    {
		        // Add fields as initialised...
		        $field = $tokens[$i+1]->name();
		        $fields["$class::$field"] = true;
            }
            $i = _skip_to(';', $i, $tokens);
		}
		
		// include
		elseif (in_array($token->id(), array(T_INCLUDE, T_REQUIRE, T_INCLUDE_ONCE, T_REQUIRE_ONCE), true))
		{
			// Figure out the included filename
			$ret = get_include_file($i, $tokens, $filename);
			$i = $ret['index'];
			$path = $ret['path'];
			$include = $ret['include'];
			
//			echo "index = $i, path = '$path', include = '$include'\n";
			
			if ($include)
			{
                if (!$path && !preg_match('~^(|\.|\.\.)[/\\\\]~', $include)) 
                {
					// can use stream_resolve_include_path() since PHP 5.3.2
					$include_path = explode(PATH_SEPARATOR, get_include_path());
					foreach (array_merge($include_path, array(dirname($filename), ".")) as $val)
					{
					    // should respect set_include_path()
						if (is_readable("$val/$include"))
						{
							$path = "$val/";
							break;
						}
					}
				}
				$initialized += check_variables($path . $include, $initialized, $function, $class);
			}
        }
		
		// interface
		elseif ($token->id() === T_INTERFACE)
		{
			while ($tokens[$i+1]->char() !== '}')
			{
				$i++;
			}
        }
        		
		// halt_compiler
		elseif ($token->id() === T_HALT_COMPILER)
		{
		    if ($trace)
		    {
		        echo "T_HALT_COMPILER: initialized...\n";
		        print_r ($initialized);
		    }
			return $initialized;
		}
		
		// blocks
		elseif ($token->char() === '(')
		{
		    if ($trace) echo "Hit '(': new function call stack\n";
			$function_calls[] = array();
		}
		elseif ($token->char() === ')')
		{
		    if ($trace) echo "Hit ')': pop function call stack\n";
			$in_list = false;
			array_pop($function_calls);
		}
		elseif ($token->char() === ',' && $function_calls)
		{
            if ($trace) echo "Hit ',': checking function call parameters\n";
			if ($function_calls[count($function_calls) - 1] &&
			    $function_calls[count($function_calls) - 1][0] !== '$...') 
			{
				array_shift($function_calls[count($function_calls) - 1]);
			}
		}
		elseif ($token->char() === '{' || $token->id() === T_CURLY_OPEN || $token->id() === T_DOLLAR_OPEN_CURLY_BRACES)
		{
//		    echo "Hit open-curly: calling check_variable()\n";
			$i = check_variables($filename, $initialized, $function, $class, $in_string, $tokens, $i+1);
//			echo "Returned from calling check_variable() i = $i\n";
		}
		elseif ($token->char() === '}' || 
		        in_array($token->id(), array(T_ENDDECLARE, T_ENDFOR, T_ENDFOREACH, T_ENDIF, T_ENDSWITCH, T_ENDWHILE), true))
		{
			return $i;
		}
		elseif (isset($tokens[$i+1]) && 
		        in_array($tokens[$i+1]->id(), 
		                 array(T_DECLARE, T_SWITCH, T_IF, T_ELSE, T_ELSEIF, T_WHILE, T_DO, T_FOR), true)) 
		{
		    // T_FOREACH in T_AS
			$i = check_variables($filename, 
			                     $initialized, 
			                     $function, 
			                     $class, 
			                     $in_string, 
			                     $tokens, 
			                     $i+1, 
			                     count($function_calls));
		}
		elseif (count($function_calls) === $single_command && $token->char() === ':')
		{
			$i = check_variables($filename, $initialized, $function, $class, $in_string, $tokens, $i+1);
		}
		elseif (in_array($token->id(), array(T_LOGICAL_OR, T_BOOLEAN_OR, T_LOGICAL_AND, T_BOOLEAN_AND), true) ||
		        $token->char() === '?')
        {
			$shortcircuit[] = count($function_calls);
		}
		
		if (count($function_calls) === $single_command && 
		    ($tokens[$i]->char() === '}' || $tokens[$i]->char() === ';') &&
		    !(isset($tokens[$i+1]) && 
		    in_array($tokens[$i+1]->id(), array(T_ELSE, T_ELSEIF, T_CATCH), true)))
		{
		    if ($trace) 
		    {
    		    $t = $tokens[$i]->char();
    		    echo "Hit $t: Returning $i\n";
            }
			return $i;
		}
	}
	
    if ($trace)
    {
        echo "All tokens processed: initialized...\n";
        print_r ($initialized);
    }

	return $initialized;
}


function _skip_to($to_tok, $index, $tokens)
{
    $len = count($tokens);
	do 
	{
		$index++;
	} 
	while ($index < $len && $tokens[$index]->char() !== $to_tok);
    return $index;
}


/**
 * @return a raw stripped string, for example "this-string" -> this-string.
 */
function _strip_str($str)
{
    return stripslashes(substr($str, 1, -1));
}


/**
 * @return a list of Token objects, each containing information about the token type, name, line number etc.
 */
function _read_tokens($filename)
{
    $tokens = array();
	foreach (token_get_all(@file_get_contents($filename)) as $token)
	{
		if (!in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) 
		{
			$tokens[] = new Token($token);
		}
	}
	return $tokens;
}


/**
 *  Figures out what the include path is:
 *
 *      include "include-file.php"
 *      include ("include-file.php")
 *      include dirname(__FILE__) . "include-file.php";
 *      include (dirname(__FILE__) . "include-file.php");
 *      include __DIR__ . "include-file.php";
 *      include (__DIR__ . "include-file.php");
 *
 * @param index         the current index of one of the T_INCLUDE token.
 * @param tokens        the tokens for the current file.
 * @return an array of new index, path to the include file, and the name of the include file.
 */
function get_include_file($index, $tokens, $filename)
{
    $index++; // skip past the T_INCLUDE
    $expect_bracket = false;
    
    $path = '';
    $include = '';
	if ($tokens[$index]->char() === '(')
	{
	    $index++;
    }

	if ($tokens[$index]->id() === T_STRING && 
	    !strcasecmp($tokens[$index]->name(), "dirname") && 
	    $tokens[$index+1]->char() === '(' && 
	    $tokens[$index+2]->id() === T_FILE && 
	    $tokens[$index+3]->char() === ')' && 
	    $tokens[$index+4]->char() === '.')
    {
		$path = dirname($filename);
		$index += 5;
	}
	elseif (is_array($tokens[$index]) &&
	        !strcasecmp($tokens[$index]->name(), "__DIR__") && 
	        $tokens[$index + 1]->char() === '.') 
	{
		$path = dirname($filename);
		$index += 2;
	}

	if ($tokens[$index]->id() === T_CONSTANT_ENCAPSED_STRING)
	{
	    $include = _strip_str($tokens[$index]->name());
        $index++;
        
	    if ($expect_bracket && $tokens[$index]->char() === ')') 
	        $index++;

        if ($tokens[$index]->char() === ';')
        {
            $index++;
		}
	}

    return array( 'index' => $index,
                  'path'  => $path,
                  'include' => $include);
}