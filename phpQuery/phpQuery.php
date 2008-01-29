<?php 
/**
 * jQuery port to PHP.
 * phpQuery is chainable DOM selector & manipulator.
 * Compatible with jQuery 1.2 (work in progress).
 * 
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @link http://code.google.com/p/phpquery/
 * @link http://meta20.net/phpQuery
 * @link http://jquery.com
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @version 0.8
 */

class phpQueryClass implements Iterator {
	public static $debug = false;
	protected static $documents = array();
	public static $lastDocID = null;
	public static $defaultDoctype = 'html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"';
	public $docID = null;
	protected $DOM = null;
	protected $XPath = null;
	protected $elementsBackup = array();
	protected $elements = array();
	protected $previous = null;
	protected $root = array();
	/**
	 * Iterator helpers
	 */
	protected $elementsInterator = array();
	protected $valid = false;
	protected $current = null;
	/**
	 * Other helpers
	 */
	protected $regexpChars = array('^','*','$');

	/**
	 * Multi-purpose function.
	 * Use phpQuery() or _() as shortcut.
	 * 
	 * 1. Create new DOM:
	 * _('file.htm')
	 * _('<div/>', true)		// accepts text nodes at beginning of input string
	 * _('http://wikipedia.org', false)
	 * 2. Import HTML into existing DOM:
	 * _('<div/>')				// DOESNT accept text nodes at beginning of input string !
	 * _('<div/>', docID)
	 * 3. Run query:
	 * _('div.myClass')
	 * _('div.myClass', 'myFile.htm')
	 * _('div.myClass', _('div.anotherClass') )
	 * 
	 * @return	phpQueryClass|false			phpQueryClass object or false in case of error.
	 */
	public static function phpQuery() {
		$input = func_get_args();
		/**
		 * Create new DOM:
		 * _('file.htm')
		 * _('<div/>', true)
		 */
		if ( ($isHtmlFile = self::isHtmlFile($input[0])) || ( isset($input[1]) && gettype($input[1]) === 'boolean'/* && self::isHTML($input[0])*/ )) {
			$rawInput = isset($input[1]) && $input[1] === true;
			// set document ID
			$ID = $rawInput
				? md5(microtime())
				: $input[0];
			// check if already loaded
			if ( $isHtmlFile && isset( self::$documents[ $ID ] ) )
				return new phpQueryClass($ID);
			// create document
			self::$documents[ $ID ]['document'] = new DOMDocument();
			$DOM =& self::$documents[ $ID ];
			// load
			$isLoaded = $rawInput
				? @$DOM['document']->loadHTML($input[0])
				: @$DOM['document']->loadHTMLFile($ID);
			if (! $isLoaded ) {
				throw new Exception("Can't load '{$ID}'");
				return false;
			}
			$DOM['xpath'] = new DOMXPath(
				$DOM['document']
			);
			// remember last document
			self::$lastDocID = $ID;
			// we ready to create object
			return new phpQueryClass($ID);
		} else if ( is_object($input[0]) && get_class($input[0]) == 'DOMElement' ) {
			throw new Exception('DOM nodes not supported');
		/**
		 * Import HTML:
		 * _('<div/>')
		 */
		} else if ( self::isHtml($input[0]) ) {
			$docID = isset($input[1]) && $input[1]
				? $input[1]
				: self::$lastDocID;
			$phpQuery = new phpQueryClass($docID);
			$phpQuery->importHtml($input[0]);
			return $phpQuery;
		/**
		 * Run query:
		 * _('div.myClass')
		 * _('div.myClass', 'myFile.htm')
		 * _('div.myClass', _('div.anotherClass') )
		 */
		} else {
			$last = count($input)-1;
			$ID = isset( $input[$last] ) && self::isHtmlFile( $input[$last] )
				? $input[$last]
				: self::$lastDocID;
			$phpQuery = new phpQueryClass($ID);
			return $phpQuery->find(
				$input[0],
				isset( $input[1] )
				&& is_object( $input[1] )
				&& ($input[1] instanceof self)
					? $input[1]
					: null
			);
		}
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function getDocID() {
		return $this->docID;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function unload() {
		unset( self::$documents[ $this->docID ] );
	}
	public static function unloadDocuments( $path = null ) {
		if ( $path )
			unset( self::$documents[ $path ] );
		else
			unset( self::$documents );
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function __construct($docPath) {
		if (! isset(self::$documents[$docPath] ) ) {
			throw new Exception("Doc path '{$docPath}' isn't loaded.");
			return;
		}
		$this->docID = $docPath;
		$this->DOM = self::$documents[ $docPath ]['document'];
		$this->XPath = self::$documents[ $docPath ]['xpath'];
		$this->root = $this->DOM->documentElement;
		$this->findRoot();
	}
	protected function debug($in) {
		if (! self::$debug )
			return;
		print('<pre>');
		print_r($in);
		// file debug
//		file_put_contents(dirname(__FILE__).'/phpQuery.log', print_r($in, true)."\n", FILE_APPEND);
		// quite handy debug trace
//		if ( is_array($in))
//			print_r(array_slice(debug_backtrace(), 3));
		print("</pre>\n");
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function findRoot() {
		$this->elements = array( $this->DOM->documentElement );
		return $this;
	}
	protected function isRegexp($pattern) {
		return in_array(
			$pattern[ strlen($pattern)-1 ],
			$this->regexpChars
		);
	}
	protected static function isHtmlFile( $filename ) {
		return is_string($filename) && (
			substr( $filename, -5 ) == '.html'
				||
			substr( $filename, -4 ) == '.htm'
		);
	}
	/**
	 * Determines if $char is really a char.
	 *
	 * @param string $char
	 * @return bool
	 * @todo rewrite me to charcode range ! ;)
	 */
	protected function isChar($char) {
		return preg_match('/\w/', $char);
	}
	protected function parseSelector( $query ) {
		// clean spaces
		// TODO include this inside parsing
		$query = trim(
			preg_replace('@\s+@', ' ',
				preg_replace('@\s*(>|\\+|~)\s*@', '\\1', $query)
			)
		);
		$queries = array(array());
		$return =& $queries[0];
		$specialChars = array('>','+','~',' ');
//		$specialCharsMapping = array('/' => '>');
		$specialCharsMapping = array();
		$strlen = strlen($query);
		$classChars = array('.', '-');
		$pseudoChars = array('-');
		// it works, but i dont like it...
		$i = 0;
		while( $i < $strlen) {
			$c = $query[$i];
			$tmp = '';
			// TAG
			if ( $this->isChar($c) || $c == '*' ) {
				while( isset($query[$i]) && ($this->isChar($query[$i]) || $query[$i] == '*')) {
					$tmp .= $query[$i];
					$i++;
				}
				$return[] = $tmp;
			// IDs
			} else if ( $c == '#' ) {
				$i++;
				while( isset($query[$i]) && $this->isChar($query[$i])) {
					$tmp .= $query[$i];
					$i++;
				}
				$return[] = '#'.$tmp;
			// SPECIAL CHARS
			} else if (in_array($c, $specialChars)) {
				$return[] = $c;
				$i++;
			// MAPPED SPECIAL MULTICHARS
//			} else if ( $c.$query[$i+1] == '//' ) {
//				$return[] = ' ';
//				$i = $i+2;
			// MAPPED SPECIAL CHARS
			} else if ( isset($specialCharsMapping[$c]) ) {
				$return[] = $specialCharsMapping[$c];
				$i++;
			// COMMA
			} else if ( $c == ',' ) {
				$queries[] = array();
				$return =& $queries[ count($queries)-1 ];
				$i++;
				while( isset($query[$i]) && $query[$i] == ' ')
					$i++;
			// CLASSES
			} else if ($c == '.') {
				while( isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $classChars))) {
					$tmp .= $query[$i];
					$i++;
				}
				$return[] = $tmp;
			// ATTRS
			} else if ($c == '[') {
				$stack = 1;
				$tmp .= $c;
				while( isset($query[++$i]) ) {
					$tmp .= $query[$i];
					if ( $query[$i] == '[' ) {
						$stack++;
					} else if ( $query[$i] == ']' ) {
						$stack--;
						if (! $stack )
							break;
					}
				}
				$return[] = $tmp;
				$i++;
			// PSEUDO CLASSES
			} else if ($c == ':') {
				$stack = 1;
				$tmp .= $query[$i++];
				while( isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $pseudoChars))) {
					$tmp .= $query[$i];
					$i++;
				}
				// with arguments ?
				if ( isset($query[$i]) && $query[$i] == '(' ) {
					$tmp .= $query[$i];
					$stack = 1;
					while( isset($query[++$i]) ) {
						$tmp .= $query[$i];
						if ( $query[$i] == '(' ) {
							$stack++;
						} else if ( $query[$i] == ')' ) {
							$stack--;
							if (! $stack )
								break;
						}
					}
					$return[] = $tmp;
					$i++;
				} else {
					$return[] = $tmp;
				}
			} else {
				$i++;
			}
		}
		foreach($queries as $k =>$q ) {
			if ( isset($q[0]) && $q[0] != '>' )
				array_unshift($queries[$k], ' ');
		}
		return $queries;
	}
	/**
	 * Returns new instance of actual class.
	 *
	 * @param array $newStack Optional. Will replace old stack with new and move old one to history.
	 * @return phpQueryClass
	 */
	protected function newInstance($newStack = null) {
		$new = new phpQueryClass($this->docID);
		$new->previous = $this;
		if ( $newStack ) {
			$new->elements = $newStack;
		} else {
			$new->elements = $this->elements;
			$this->elements = $this->elementsBackup;
		}
		return $new;
	}
	
	/**
	 * Enter description here...
	 *
	 * In the future, when PHP will support XLS 2.0, then we would do that this way:
	 * contains(tokenize(@class, '\s'), "something")
	 * @param unknown_type $class
	 * @param unknown_type $node
	 * @return boolean
	 */
	protected function matchClasses( $class, $node ) {
		// multi-class
		if ( strpos($class, '.', 1) ) {
			$classes = explode('.', substr($class, 1));
			$classesCount = count( $classes );
			$nodeClasses = explode(' ', $node->getAttribute('class') );
			$nodeClassesCount = count( $nodeClasses );
			if ( $classesCount > $nodeClassesCount )
				return false;
			$diff = count(
				array_diff(
					$classes,
					$nodeClasses
				)
			);
			if (! $diff )
				return true;
		// single-class
		} else {
			return in_array(
				// strip leading dot from class name
				substr($class, 1),
				// get classes for element as array
				explode(' ', $node->getAttribute('class') )
			);
		}
	}
	protected function runQuery( $XQuery, $selector = null, $compare = null ) {
		if ( $compare && ! method_exists($this, $compare) )
			return false;
		$stack = array();
		if (! $this->elements )
			$this->debug('Stack empty, skipping...');
		foreach( $this->elements as $k => $stackNode ) {
			$remove = false;
			// to work on detached nodes we need temporary place them somewhere
			// thats because context xpath queries sucks ;]
			if (! $stackNode->parentNode && ! $this->isRoot($stackNode) ) {
				$this->root->appendChild($stackNode);
				$remove = true;
			}
			$xpath = $this->getNodeXpath($stackNode);
			$query = $xpath.$XQuery;
			$this->debug("XPATH: {$query}");
			// run query, get elements
			$nodes = $this->XPath->query($query);
			$this->debug("QUERY FETCHED");
			if (! $nodes->length )
				$this->debug('Nothing found');
			foreach( $nodes as $node ) {
				$matched = false;
				if ( $compare ) {
					self::$debug ?
						$this->debug("Found: ".$this->whois( $node ).", comparing with {$compare}()")
						: null;
					if ( call_user_method($compare, $this, $selector, $node) )
						$matched = true;
				} else {
					$matched = true;
				}
				if ( $matched ) {
					self::$debug
						? $this->debug("Matched: ".$this->whois( $node ))
						: null;
					$stack[] = $node;
				}
			}
			if ( $remove )
				$stackNode = $this->root->removeChild( $this->root->lastChild );
		}
		$this->elements = $stack;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function find( $selectors, $context = null ) {
		// backup last stack /for end()/
		$this->elementsBackup = $this->elements;
		// allow to define context
		if ( $context ) {
			$DOMElement = 'DOMElement';
			if (! is_array($context) && $context instanceof $DOMElement )
				$this->elements = array($context);
			else if ( is_array($context) ) {
				$this->elements = array();
				foreach ($context as $e)
					if ( $c instanceof $DOMElement )
						$this->elements[] = $c;
				
			} else if ( $context instanceof self )
				$this->elements = $context->elements;  
		}
		$spaceBefore = false;
		$queries = $this->parseSelector( $selectors );
		$this->debug(array('FIND',$selectors,$queries));
		$XQuery = '';
		// remember stack state because of multi-queries
		$oldStack = $this->elements;
		// here we will be keeping found elements
		$stack = array();
		foreach( $queries as $selector ) {
			$this->elements = $oldStack;
			foreach( $selector as $s ) {
				// TAG
				if ( preg_match('@^\w+$@', $s) || $s == '*' ) {
					$XQuery .= $s;
				} else if ( $s[0] == '#' ) {
					// id
					if ( $spaceBefore )
						$XQuery .= '*';
					$XQuery .= "[@id='".substr($s, 1)."']";
				// ATTRIBUTES
				} else if ( $s[0] == '[' ) {
					if ( $spaceBefore )
						$XQuery .= '*';
					// strip side brackets
					$attr = trim($s, '][');
					$execute = false;
					// attr with specifed value
					if ( strpos( $s, '=' ) ) {
						list( $attr, $value ) = explode('=', $attr);
						$value = trim($value, "'\"'");
						if ( $this->isRegexp($attr) ) {
							// cut regexp character
							$attr = substr($attr, 0, -1);
							$execute = true;
							$XQuery .= "[@{$attr}]";
						} else {
							$XQuery .= "[@{$attr}='{$value}']";
						}
					// attr without specified value
					} else {
						$XQuery .= "[@{$attr}]";
					}
					if ( $execute ) {
						$this->runQuery($XQuery, $s, 'is');
						$XQuery = '';
						if (! $this->length() )
							break;
					}
				// CLASSES
				} else if ( $s[0] == '.' ) {
					if ( $spaceBefore )
						$XQuery .= '*';
					$XQuery .= '[@class]';
					$this->runQuery($XQuery, $s, 'matchClasses');
					$XQuery = '';
					if (! $this->length() )
						break;
				// PSEUDO CLASSES
				} else if ( $s[0] == ':' ) {
					// TODO optimization for :first :last
					if ( $XQuery ) {
						$this->runQuery($XQuery);
						$XQuery = '';
					}
					if (! $this->length() )
						break;
					$this->filterPseudoClasses( $s );
					if (! $this->length() )
						break;
				} else if ( $s == '>' ) {
					// direct descendant
					$XQuery .= '/';
				} else {
					$XQuery .= '//';
				}
				if ( $s == ' ' )
					$spaceBefore = true;
				else
					$spaceBefore = false;
			}
			// run query if any
			if ( $XQuery && $XQuery != '//' ) {
				$this->runQuery($XQuery);
				$XQuery = '';
//				if (! $this->length() )
//					break;
			}
			foreach( $this->elements as $node )
				if (! $this->elementsContainsNode($node, $stack) )
					$stack[] = $node;
		}
		$this->elements = $stack;
		return $this->newInstance();
	}
	
	/**
	 * @todo create API for classes with pseudoselectors
	 */
	protected function filterPseudoClasses( $class ) {
		// TODO clean args parsing ?
		$class = ltrim($class, ':');
		$haveArgs = strpos($class, '(');
		if ( $haveArgs !== false ) {
			$args = substr($class, $haveArgs+1, -1);
			$class = substr($class, 0, $haveArgs);
		}
		switch( $class ) {
			case 'even':
			case 'odd':
				$stack = array();
				foreach( $this->elements as $i => $node ) {
					if ( $class == 'even' && $i % 2 == 0 )
						$stack[] = $node;
					else if ( $class == 'odd' && $i % 2 )
						$stack[] = $node;
				}
				$this->elements = $stack;
				break;
			case 'eq':
				$k = intval($args);
				$this->elements = isset( $this->elements[$k] )
					? array( $this->elements[$k] )
					: array();
				break;
			case 'gt':
				$this->elements = array_slice($this->elements, $args+1);
				break;
			case 'lt':
				$this->elements = array_slice($this->elements, 0, $args+1);
				break;
			case 'first':
				if ( isset( $this->elements[0] ) )
					$this->elements = array( $this->elements[0] );
				break;
			case 'last':
				if ( $this->elements )
					$this->elements = array( $this->elements[ count($this->elements)-1 ] );
				break;
			case 'parent':
				$stack = array();
				foreach( $this->elements as $node ) {
					if ( $node->childNodes->length )
						$stack[] = $node;
				}
				$this->elements = $stack;
				break;
			case 'contains':
				$text = trim($args, "\"'");
				$stack = array();
				foreach( $this->elements as $node ) {
					if ( strpos( $node->textContent, $text ) === false )
						continue;
					$stack[] = $node;
				}
				$this->elements = $stack;
				break;
			case 'not':
				$query = trim($args, "\"'");
				$stack = $this->elements;
				$newStack = array();
				foreach( $stack as $node ) {
					$this->elements = array($node);
					if (! $this->is($query) )
						$newStack[] = $node;
				}
				$this->elements = $newStack;
				break;
			case 'has':
				$selector = trim($args, "\"'");
				$stack = array();
				foreach( $this->elements as $el ) {
					if ( $this->find($selector, $el)->length() )
						$stack[] = $el;
				}
				$this->elements = $stack;
				break;
			default:
				$this->debug("Unknown pseudoclass '{$class}', skipping...");
		}
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function is( $selector, $_node = null ) {
		$this->debug(array("Is:", $selector));
		if (! $selector)
			return false;
		$oldStack = $this->elements;
		if ( $_node )
			$this->elements = array($_node);
		$this->filter($selector, true);
		$match = (bool)$this->length();
		$this->elements = $oldStack;
		return $match;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function filter( $selectors, $_skipHistory = false ) {
		if (! $_skipHistory )
			$this->elementsBackup = $this->elements;
		$notSimpleSelector = array(' ', '>', '~', '+', '/');
		$selectors = $this->parseSelector( $selectors );
		if (! $_skipHistory )
			$this->debug(array("Filtering:", $selectors));
		$stack = array();
		foreach ( $selectors as $selector ) {
			if (! $selector )
				break;
			// avoid first space or /
			if (in_array( $selector[0], $notSimpleSelector ) )
				$selector = array_slice($selector, 1);
			// PER NODE selector chunks
			foreach( $this->elements as $node ) {
				$break = false;
				foreach( $selector as $s ) {
					// ID
					if ( $s[0] == '#' ) {
						if ( $node->getAttribute('id') != substr($s, 1) )
							$break = true;
					// CLASSES
					} else if ( $s[0] == '.' ) {
						if (! $this->matchClasses( $s, $node ) )
							$break = true;
					// ATTRS
					} else if ( $s[0] == '[' ) {
						// strip side brackets
						$attr = trim($s, '[]');
						if ( strpos($attr, '=') ) {
							list( $attr, $val ) = explode('=', $attr);
							if ( $this->isRegexp($attr)) {
								// switch last character
								switch( substr($attr, -1) ) {
									case '^':
										$pattern = '^'.preg_quote($val, '@');
										break;
									case '*':
										$pattern = '.*'.preg_quote($val, '@').'.*';
										break;
									case '$':
										$pattern = preg_quote($val, '@').'$';
										break;
								}
								// cut last character
								$attr = substr($attr, 0, -1);
								if (! preg_match("@{$pattern}@", $node->getAttribute($attr)))
									$break = true;
							} else if ( $node->getAttribute($attr) != $val )
								$break = true;
						} else if (! $node->hasAttribute($attr) )
							$break = true;
					// PSEUDO CLASSES
					} else if ( $s[0] == ':' ) {
						// skip
					// TAG
					} else if ( trim($s) ) {
						if ( $s != '*' ) {
							if ( isset($node->tagName) ) {
								if ( $node->tagName != $s )
									$break = true;
							} else if ( $s == 'html' && ! $this->isRoot($node) )
								$break = true;
						}
					// AVOID NON-SIMPLE SELECTORS
					} else if ( in_array($s, $notSimpleSelector)) {
						$break = true;
						$this->debug(array('Skipping non simple selector', $selector));
					}
					if ( $break )
						break;
				}
				// if element passed all chunks of selector - add it to new stack
				if (! $break )
					$stack[] = $node;
			}
			$this->elements = $stack;
			// PER ALL NODES selector chunks
			foreach($selector as $s)
				// PSEUDO CLASSES
				if ( $s[0] == ':' )
					$this->filterPseudoClasses($s);
		}
		return $_skipHistory
			? $this
			: $this->newInstance();
	}
	
	protected function isRoot( $node ) {
		$DOMDocument = "DOMDocument";
		return $node instanceof $DOMDocument || $node->tagName == 'html';
	}

	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function css() {
		// TODO
	}
	
	protected function importHtml($html) {
		$this->elementsBackup = $this->elements;
		$this->elements = array();
		$DOM = new DOMDocument();
		@$DOM->loadHtml( $html );
		foreach($DOM->documentElement->firstChild->childNodes as $node)
			$this->elements[] = $this->DOM->importNode( $node, true );
	}
	/**
	 * Wraps elements with $before and $after.
	 * In case when there's no $after, $before should be a tag name (without <>).
	 *
	 * @return phpQueryClass
	 */
	public function wrap($before, $after = null) {
		if (! $after ) {
			$after = "</{$before}>";
			$before = "<{$before}>";
		}
		// safer...
		$each = clone $this;
		foreach( $each as $node ) {
			$wrap = self::phpQuery($before."<div id='__wrap'/>".$after)
				->insertAfter($node);
			$wrap->find('#__wrap')
					->replace($node);
		}
		return $this;
	}

	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function eq($num) {
		$oldStack = $this->elements;
		$this->elementsBackup = $this->elements;
		$this->elements = array();
		if ( isset($oldStack[$num]) )
			$this->elements[] = $oldStack[$num];
		return $this->newInstance();
	}

	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function size() {
		return $this->length();
	}

	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function length() {
		return count( $this->elements );
	}

	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function end() {
//		$this->elements = array_pop( $this->history );
//		return $this;
		$this->previous->DOM = $this->DOM;
		$this->previous->XPath = $this->XPath;
		return $this->previous
			? $this->previous
			: $this;
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function select($selector) {
		return $this->is($selector)
			? $this->filter($selector)
			: $this->find($selector);
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function each($callabck) {
		$this->elementsBackup = $this->elements;
		foreach( $this->elementsBackup as $node ) {
			$this->elements = array($node);
			if ( is_array( $callabck ) ) {
				if ( is_object( $callabck[0] ) )
					$callabck[0]->{$callabck[1]}( $this->newInstance() );
				else
					eval("{$callabck[0]}::{$callabck[1]}( \$this->newInstance() );");
			} else {
				$callabck( $this->newInstance() );
			}
		}
		return $this;
	}

	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function _clone() {
		$newStack = array();
		//pr(array('copy... ', $this->whois()));
		//$this->dumpHistory('copy');
		$this->elementsBackup = $this->elements;
		foreach( $this->elements as $node ) {
			$newStack[] = $node->cloneNode(true);
		}
		$this->elements = $newStack;
		return $this;
	}

	/**
	 * Replaces current element with $content and changes stack to new element(s) (except text nodes).
	 * Can be reverted with end().
	 *
	 * @param string|phpQueryClass $with
	 * @return phpQueryClass
	 */
	public function replace($content) {
		$stack = array();
		// safer...
		$each = clone $this;
		foreach( $each as $node ) {
			$prev = $node->before($content)->_prev();
			if ( $this->isHtml($content) )
				$stack[] = $prev->elements;
		}
		$stack = $this->before($content);
		$this->remove();
		$this->elementsBackup = $this->elements;
		$this->elements = $stack;
		return $this->newInstance();
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function replacePHP($code) {
		return $this->replace("<php>{$code}</php>");
	}

	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function remove() {
		foreach( $this->elements as $node ) {
			if (! $node->parentNode )
				continue;
			$this->debug("Removing '{$node->tagName}'");
			$node->parentNode->removeChild( $node );
		}
		return $this;
	}

	/**
	 * Checks if $input is HTML string, which has to start with '<'.
	 * @todo this has to be done better, so check & rethink & refactor. Thought 1 - phpQuery::parseHtml()
	 *
	 * @param unknown_type $input
	 * @return unknown
	 */
	protected function isHtml($input) {
		return substr(trim($input), 0, 1) == '<';
//			|| is_object($html) && is_a($html, 'DOMElement');
	}

	public function html($html = null) {
		if (! is_null($html) ) {
			$this->debug("Inserting data with 'html'");
			if ( $this->isHtml( $html ) ) {
				$toInserts = array();
				$DOM = new DOMDocument();
				@$DOM->loadHtml( $html );
				foreach($DOM->documentElement->firstChild->childNodes as $node)
					$toInserts[] = $this->DOM->importNode( $node, true );
			} else {
				$toInserts = array($this->DOM->createTextNode( $html ));
			}
			$this->_empty();
			// i dont like brackets ! python rules ! ;)
			foreach( $toInserts as $toInsert ) {
				foreach( $this->elements as $alreadyAdded => $node ) {
					$node->appendChild( $alreadyAdded
						? $toInsert->cloneNode()
						: $toInsert
					);
				}
			}
			$this->dumpStack();
			print($this->elements[0]->textContent);
			return $this;
		} else {
			if ( $this->length() == 1 && $this->isRoot( $this->elements[0] ) )
				return $this->save();
			$DOM = new DOMDocument();
			foreach( $this->elements as $node ) {
				foreach( $node->childNodes as $child ) {
					$DOM->appendChild(
						$DOM->importNode( $child, true )
					);
				}
			}
			return $this->save($DOM);
		}
	}
	/**
	 * Enter description here...
	 * 
	 * @return String
	 */
	public function htmlOuter() {
		if ( $this->length() == 1 && $this->isRoot( $this->elements[0] ) )
			return $this->save();
		$DOM = new DOMDocument();
		$this->dumpStack();
		foreach( $this->elements as $node ) {
			$DOM->appendChild(
				$DOM->importNode( $node, true )
			);
		}
		return $this->save($DOM);
	}
	protected function save($DOM = null){
		if (! $DOM)
			$DOM = $this->DOM;
		$DOM->formatOutput = false;
		$DOM->preserveWhiteSpace = true;
		$doctype = isset($DOM->doctype) && is_object($DOM->doctype)
			? $DOM->doctype->publicId
			: self::$defaultDoctype;
		return stripos($doctype, 'xhtml') !== false
			? $this->saveXHTML( $DOM->saveXML() )
			: $DOM->saveHTML();
	}
	protected function saveXHTML($content){
		return
			// TODO find out what and why it is. maybe it has some relations with extra new lines ?
			str_replace(array('&#13;','&#xD;'), '',
			// strip non-commented cdata
			str_replace(']]]]><![CDATA[>', ']]>',
			preg_replace('@(<script[^>]*>\s*)<!\[CDATA\[@', '\1',
			preg_replace('@\]\]>(\s*</script>)@', '\1',
			// textarea can't be short tagged
			preg_replace('!<textarea([^>]*)/>!', '<textarea\1></textarea>',
				// cut first line xml declaration
				implode("\n",
					array_slice(
						explode("\n", $content),
						1
		)))))));
	}
	public function __toString() {
		return $this->htmlOuter();
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function php($code) {
		return $this->html("<php>".trim($code)."</php>");
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function phpPrint($var) {
		return $this->php("print {$var};");
	}
	/**
	 * Fills elements selected by $selector with $content using html(), and roll back selection.
	 * 
	 * @param string	Selector
	 * @param string	Content
	 * 
	 * @return phpQueryClass
	 */
	public function fill($selector, $content) {
		$this->find($selector)
			->html($content);
		return $this;
	}
	/**
	 * Fills elements selected by $selector with $code using php(), and roll back selection.
	 * 
	 * @param string	Selector
	 * @param string	Valid PHP Code
	 * 
	 * @return phpQueryClass
	 */
	public function fillPhp($selector, $code) {
		$this->find($selector)
			->php($code);
		return $this;
	}
	protected function dumpHistory($when) {
		foreach( $this->history as $nodes ) {
			$history[] = array();
			foreach( $nodes as $node ) {
				$history[ count($history)-1 ][] = $this->whois( $node );
			}
		}
		//pr(array("{$when}/history", $history));
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function children( $selector = null ) {
		$tack = array();
		foreach( $this->elements as $node ) {
			foreach( $node->getElementsByTagName('*') as $newNode ) {
				if ( $selector && ! $this->is($selector, $newNode) )
					continue;
				$stack[] = $newNode;
			}
		}
		$this->elementsBackup = $this->elements;
		$this->elements = $stack;
		return $this->newInstance();
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function unwrapContent() {
		foreach( $this->elements as $node ) {
			if (! $node->parentNode )
				continue;
			$childNodes = array();
			// any modification in DOM tree breaks childNodes iteration, so cache them first
			foreach( $node->childNodes as $chNode )
				$childNodes[] = $chNode;
			foreach( $childNodes as $chNode )
//				$node->parentNode->appendChild($chNode);
				$node->parentNode->insertBefore($chNode, $node);
			$node->parentNode->removeChild($node);
		}
		return $this->newInstance();
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function ancestors( $selector = null ) {
		return $this->children( $selector );
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function append( $content ) {
		return $this->insert($content, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function appendPHP( $content ) {
		return $this->insert("<php>{$content}</php>", 'append');
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function appendTo( $seletor ) {
		return $this->insert($seletor, __FUNCTION__);
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function prepend( $content ) {
		return $this->insert($content, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function prependPHP( $content ) {
		return $this->insert("<php>{$content}</php>", 'prepend');
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function prependTo( $seletor ) {
		return $this->insert($seletor, __FUNCTION__);
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function before( $content ) {
		return $this->insert($content, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function beforePHP( $content ) {
		return $this->insert("<php>{$content}</php>", 'before');
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function insertBefore( $seletor ) {
		return $this->insert($seletor, __FUNCTION__);
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function after( $content ) {
		return $this->insert($content, __FUNCTION__);
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function afterPHP( $content ) {
		return $this->insert("<php>{$content}</php>", 'after');
	}
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function insertAfter( $seletor ) {
		return $this->insert($seletor, __FUNCTION__);
	}
	protected function insert( $target, $type ) {
		$this->debug("Inserting data with '{$type}'");
		$to = false;
		switch( $type ) {
			case 'appendTo':
			case 'prependTo':
			case 'insertBefore':
			case 'insertAfter':
				$to = true;
		}
		switch(gettype( $target )) {
			case 'string':
				if ( $to ) {
					$insertFrom = $this->elements;
					// insert into created element
					if ( $this->isHtml( $target ) ) {
						$DOM = new DOMDocument();
						@$DOM->loadHtml($target);
						$i = count($this->tmpNodes);
						$this->tmpNodes[] = array();
						foreach($DOM->documentElement->firstChild->childNodes as $node) {
							$this->tmpNodes[$i][] = $this->DOM->importNode( $node, true );
						}
						// XXX needed ?!
					//	$this->tmpNodes[$i] = array_reverse($this->tmpNodes[$i]);
						$insertTo =& $this->tmpNodes[$i];
					// insert into selected element
					} else {
						$thisStack = $this->elements;
						$this->findRoot();
						$insertTo = $this->find($target)->elements;
						$this->elements = $thisStack;
					}
				} else {
					$insertTo = $this->elements;
					// insert created element
					if ( $this->isHtml( $target ) ) {
						$DOM = new DOMDocument();
						@$DOM->loadHtml($target);
						$insertFrom = array();
						foreach($DOM->documentElement->firstChild->childNodes as $node) {
							$insertFrom[] = $this->DOM->importNode($node, true);
						}
						// XXX needed ?!
					//	$insertFrom = array_reverse($insertFrom);
					// insert selected element
					} else {
						$insertFrom = array(
							$this->DOM->createTextNode( $target )
						);
					}
				}
				break;
			case 'object':
				$insertFrom = $insertTo = array();
				if ( is_a($target, get_class($this)) ){
					if ( $to ) {
						$insertTo = $target->elements;
						if ( $this->size() == 1 && $this->isRoot($this->elements[0]) )
							$loop = $this->find('body>*')->elements;
						else
							$loop = $this->elements;
						foreach( $loop as $node )
							$insertFrom[] = $target->DOM->importNode($node, true);
					} else {
						$insertTo = $this->elements;
						if ( $target->size() == 1 && $this->isRoot($target->elements[0]) )
							$loop = $target->find('body>*')->elements;
						else
							$loop = $target->elements;
						foreach( $loop as $node ) {
							$insertFrom[] = $this->DOM->importNode($node, true);
						}
					}
				}
				break;
		}
		foreach( $insertTo as $toNode ) {
			// we need static relative elements in some cases
			switch( $type ) {
				case 'prependTo':
				case 'prepend':
					$firstChild = $toNode->firstChild;
					break;
				case 'insertAfter':
				case 'after':
					$nextSibling = $toNode->nextSibling;
					break;
			}
			foreach( $insertFrom as $fromNode ) {
				switch( $type ) {
					case 'appendTo':
					case 'append':
//						$toNode->insertBefore(
//							$fromNode,
//							$toNode->lastChild->nextSibling
//						);
						$toNode->appendChild($fromNode);
						break;
					case 'prependTo':
					case 'prepend':
						$toNode->insertBefore(
							$fromNode,
							$firstChild
						);
						break;
					case 'insertBefore':
					case 'before':
						$toNode->parentNode->insertBefore(
							$fromNode,
							$toNode
						);
						break;
					case 'insertAfter':
					case 'after':					
						$toNode->parentNode->insertBefore(
							$fromNode,
							$nextSibling
						);
						break;
				}
			}
		}
		return $this;
	}

	/**
	 * @todo Returns JSON representation of stacked HTML elements and it's children.
	 * Compatible with @link http://programming.arantius.com/dollar-e 
	 *
	 * @return string
	 */
	public function toJSON() {
		$json = '';
		foreach( $this->elements as $node ) {
			
		}
		return $json;
	}
	protected function _toJSON($node) {
		$json = '';
		switch( $node->type ) {
			case 3:
				break;
		}
		return $json;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $start
	 * @param unknown_type $end
	 * 
	 * @return phpQueryClass
	 * @testme
	 */
	public function slice($start, $end = null) {
//		$last = count($this->elements)-1;
//		$end = $end
//			? min($end, $last)
//			: $last;
//		if ($start < 0)
//			$start = $last+$start;
//		if ($start > $last)
//			return array();
		return $this->newInstance(
			array_slice($this->elements, $start, $end)
		);
	}	
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function reverse() {
		$this->elementsBackup = $this->elements;
		$this->elements = array_reverse($this->elements);
	}

	public function text() {
		$return = '';
		foreach( $this->elements as $node ) {
			$return .= $node->textContent;
		}
		return $return;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function _next( $selector = null ) {
		return $this->newInstance(
			$this->getElementSiblings('nextSibling', $selector, true)
		);
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function _prev( $selector = null ) {
		return $this->newInstance(
			$this->getElementSiblings('previousSibling', $selector, true)
		);
	}
	
	/**
	 * @return phpQueryClass
	 * @todo
	 */
	public function prevAll( $selector = null ) {
		return $this->newInstance(
			$this->getElementSiblings('previousSibling', $selector)
		);
	}
	
	/**
	 * @return phpQueryClass
	 * @todo
	 */
	public function nextAll( $selector = null ) {
		return $this->newInstance(
			$this->getElementSiblings('nextSibling', $selector)
		);
	}
	
	/**
	 * Number of prev siblings
	 * @return int
	 * @todo
	 */
	public function index() {
		return $this->prevSiblings()->size();
	}
	
	protected function getElementSiblings($direction, $selector, $limitToOne = false) {
		$stack = array();
		$count = 0;
		foreach( $this->elements as $node ) {
			$test = $node;
			while( isset($test->{$direction}) && $test->{$direction} ) {
				$test = $test->{$direction};
				if ( $selector )
					if ( $this->is( $selector, $test ) )
						$stack[] = $test;
				else
					$stack[] = $test;
				if ($limitToOne && $stack)
					return $stack;
			}
		}
		return $stack;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function siblings( $selector = null ) {
		$stack = array();
		foreach( $this->elements as $node ) {
			if ( $selector ) {
				if ( $this->is( $selector ) && ! $this->elementsContainsNode($node, $stack) )
					$stack[] = $node;
			} else if (! $this->elementsContainsNode($node, $stack) )
				$stack[] = $node;
		}
		$this->elementsBackup = $this->elements;
		$this->elements = $stack;
		return $this->newInstance();
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function not( $selector = null ) {
		$stack = array();
		foreach( $this->elements as $node ) {
			if (! $this->is( $selector, $node ) )
				$stack[] = $node;
		}
		$this->elementsBackup = $this->elements;
		$this->elements = $stack;
		return $this->newInstance();
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function add( $selector = null ) {
		$stack = array();
		$this->elementsBackup = $this->elements;
		$found = $this->find($selector);
		$this->merge($found->elements);
		return $this->newInstance();
	}
	
	protected function merge() {
		foreach( get_func_args() as $nodes )
			foreach( $nodes as $newNode )
				if (! $this->elementsContainsNode($newNode) )
					$this->elements[] = $newNode;
	}
	
	protected function elementsContainsNode($nodeToCheck, $elementsStack = null) {
		$loop = ! is_null($elementsStack)
			? $elementsStack
			: $this->elements;
		foreach( $loop as $node ) {
			if ( $node->isSameNode( $nodeToCheck ) )
				return true;
		}
		return false;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function parent( $selector = null ) {
		$stack = array();
		foreach( $this->elements as $node )
			if ( $node->parentNode && ! $this->elementsContainsNode($node->parentNode, $stack) )
				$stack[] = $node->parentNode;
		$this->elementsBackup = $this->elements;
		$this->elements = $stack;
		if ( $selector )
			$this->filter($selector, true);
		return $this->newInstance();
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function parents( $selector = null ) {
		$stack = array();
		if (! $this->elements )
			$this->debug('parents() - stack empty');
		foreach( $this->elements as $node ) {
			$test = $node;
			while( $test->parentNode ) {
				$test = $test->parentNode;
				if ( is_a($test, 'DOMDocument') )
					break;
				if ( $selector ) {
					if ( $this->is( $selector, $test ) && ! $this->elementsContainsNode($test, $stack) ) {
						$stack[] = $test;
						continue;
					}
				} else if (! $this->elementsContainsNode($test, $stack) ) {
					$stack[] = $test;
					continue;
				}
			}
		}
		return $this->newInstance($stack);
	}
	
	/**
	 * Attribute method.
	 * Accepts * for all attributes (for setting and getting)
	 *
	 * @param unknown_type $attr
	 * @param unknown_type $value
	 * @return string|array
	 */
	public function attr( $attr = null, $value = null ) { 
		foreach( $this->elements as $node ) {
			if (! is_null( $value )) {
				$loop = $attr == '*'
					? $this->getNodeAttrs($node)
					: array($attr);
				foreach( $loop as $a ) {
					if ( $value )
						$node->setAttribute($a, $value);
					else
						$node->removeAttribute($a);
				}
			} else if ( $attr == '*' ) {
				$return = array();
				foreach( $node->attributes as $n => $v)
					$return[$n] = $v->value;
				return $return;
			} else
				return $node->getAttribute($attr);
		}
		return $this;
	}
	
	protected function getNodeAttrs($node) {
		$return = array();
		foreach( $node->attributes as $n => $o)
			$return[] = $n;
		return $return;
	}
	
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function attrPHP( $attr, $value ) { 
		foreach( $this->elements as $node ) {
			$node->setAttribute($attr, "<?php {$value} ?>");
		}
		return $this;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function removeAttr( $attr ) {
		foreach( $this->elements as $node ) {
			$loop = $attr == '*'
				? $this->getNodeAttrs($node)
				: array($attr);
			foreach( $loop as $a )
				$node->removeAttribute($a);
		}
		return $this;
	}
	
	/**
	 * Enter description here...
	 * 
	 * @todo val()
	 */
	public function val() {
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function addClass( $className ) {
		foreach( $this->elements as $node ) {
			if (! $this->is( $node, '.'.$className))
				$node->setAttribute(
					'class',
					$node->getAttribute('class').' '.$className
				);
		}
		return $this;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param	string	$className
	 * @return	bool
	 */
	public function hasClass( $className ) {
		foreach( $this->elements as $node ) {
			if ( $this->is( $node, '.'.$className))
				return true;
		}
		return false;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function removeClass( $className ) {
		foreach( $this->elements as $node ) { 
			$classes = explode( ' ', $node->getAttribute('class'));
			if ( in_array($className, $classes) ) {
				$classes = array_diff($classes, array($className));
				if ( $classes )
					$node->setAttribute('class', implode(' ', $classes));
				else
					$node->removeAttribute('class');
			}
		}
		return $this;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return phpQueryClass
	 */
	public function toggleClass( $className ) {
		foreach( $this->elements as $node ) {
			if ( $this->is( $node, '.'.$className ))
				$this->removeClass($className);
			else 
				$this->addClass($className);
		}
		return $this;
	}
	
	/**
	 * Removes all child nodes from the set of matched elements.
	 *  
	 * Example:
	 * _("p")._empty()
	 *  
	 * HTML:
	 * <p>Hello, <span>Person</span> <a href="#">and person</a></p>
	 *  
	 * Result:
	 * [ <p></p> ]
	 * 
	 * @return phpQueryClass
	 */
	public function _empty() {
		foreach( $this->elements as $node ) {
			// many thx to 'dave at dgx dot cz' :)
			$node->nodeValue = '';
		}
		return $this;
	}
	

	// ITERATOR INTERFACE
	public function rewind(){
		$this->debug('interating foreach');
		$this->elementsBackup = $this->elements;
		$this->elementsInterator = $this->elements;
		$this->valid = isset( $this->elements[0] )
			? 1
			: 0;
		$this->elements = $this->valid
			? array($this->elements[0])
			: array();
		$this->current = 0;
	}

	public function current(){
		return $this;
	}

	public function key(){
		return $this->current;
	}

	public function next(){
		$this->current++;
		$this->valid = isset( $this->elementsInterator[ $this->current ] )
			? true
			: false;
		if ( $this->valid )
			$this->elements = array(
				$this->elementsInterator[ $this->current ]
			);
	}
	public function valid(){
		return $this->valid;
	}

	protected function getNodeXpath( $oneNode = null ) {
		$DOMDocument = "DOMDocument"; $DOMElement = 'DOMElement';
		$return = array();
		$loop = $oneNode
			? array($oneNode)
			: $this->elements;
		foreach( $loop as $node ) {
			if ($node instanceof $DOMDocument) {
				$return[] = '';
				continue;
			}				
			$xpath = array();
			while(! ($node instanceof $DOMDocument) ) {
				$i = 1;
				$sibling = $node;
				while( $sibling->previousSibling ) {
					$sibling = $sibling->previousSibling;
					$isElement = $sibling instanceof $DOMElement;
					if ( $isElement && $sibling->tagName == $node->tagName )
						$i++;
				}
				$xpath[] = "{$node->tagName}[{$i}]";
				$node = $node->parentNode;
			}
			$xpath = join('/', array_reverse($xpath));
			$return[] = '/'.$xpath;
		}
		return $oneNode
			? $return[0]
			: $return;
	}

	public function whois($oneNode = null) {
		$return = array();
		$loop = $oneNode
			? array( $oneNode )
			: $this->elements;
		foreach( $loop as $node ) {
			$return[] = isset($node->tagName)
				? $node->tagName
					.($node->getAttribute('id')
						? '#'.$node->getAttribute('id'):'')
					.($node->getAttribute('class')
						? '.'.join('.', split(' ', $node->getAttribute('class'))):'')
				: get_class($node);
		}
		return $oneNode
			? $return[0]
			: $return;
	}
	
	// HELPERS

	public function dumpStack() { 
		$i = 1;
		foreach( $this->elements as $node ) {
			$this->debug("Node {$i} ".$this->whois($node));
			$i++;
		}
	}
	
	public function dumpSource( $node = null ) {
		$return = array();
		$loop = $node
			? array( $node )
			: $this->elements;
		foreach( $loop as $node ) {
			$DOM = new DOMDocument();
			$DOM->appendChild(
				$DOM->importNode( $node, true )
			);
			$return[] = $DOM->saveHTML();
		}
		return $return;
	}
}

/**
 * Shortcut to <code>new phpQueryClass($arg1, $arg2, ...)</code>
 *
 * @return phpQueryClass
 */
function phpQuery() {
	$args = func_get_args();
	return call_user_func_array(
		array('phpQueryClass', 'phpQuery'),
		$args
	);
}

if (! function_exists('_')) {
	/**
	 * Handy phpQuery shortcut.
	 * Optional, because conflicts with gettext extension.
	 * @link http://php.net/_
	 *
	 * @return phpQueryClass
	 */
	function _() {
		$args = func_get_args();
		return call_user_func_array('phpQuery', $args);
	}
}
?>