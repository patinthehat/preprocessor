<?php
/**
 * Copyright (c) 2010 Arne Blankerts <arne@blankerts.de>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *
 *   * Neither the name of Arne Blankerts nor the names of contributors
 *     may be used to endorse or promote products derived from this software
 *     without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT  * NOT LIMITED TO,
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER ORCONTRIBUTORS
 * BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    PreProcessor
 * @author     Arne Blankerts <arne@blankerts.de>
 * @copyright  Arne Blankerts <arne@blankerts.de>, All rights reserved.
 * @license    BSD License
 * @link       http://github.com/theseer/preprocessor
 */

namespace TheSeer\Tools {
  
  require('PreProcessorException.php');

   /**
    * Implements a c++-alike preprocessor for php scripts
    *
    * @author     Arne Blankerts <arne@blankerts.de>
    * @copyright  Arne Blankerts <arne@blankerts.de>, All rights reserved.
    */
   class PreProcessor {

      /**
       * List of values set by "#define"
       *
       * @var array
       */
      protected $defines     = array();

      /**
       * List of internal defines that cannot be undefined
       *
       * @var array
       */
      protected $protectedDefines = array();      
      

      /**
       * List of function names to inline during procesing.
       *
       * @var array
       */
      protected $inlinedFunctions = array();
      protected $inlinedFuncData = array();
      
      /**
       * List of internal preprocessor options
       *
       * @var array
       */
      protected $options = array();

      
      /**
       * Internal flag to signalize wether or not to skip over a section
       *
       * @var boolean
       */
      protected $skipOver    = false;

      /**
       * Output buffer
       *
       * @var string
       */
      protected $output      = '';

      
      public function __construct() {
         $this->internalInitialize();
         $this->getAllDefines();
      }
      
      public function getAllDefines() {
        $ds = '';
        $i=0;
        foreach($this->defines as $n=>$v) {
          $ds .= "$n, ";
          $i++;
        }
        if (!defined('PPPDEFS'))
          eval("define('PPPDEFS',  \"$ds\");");
        return $this->defines;
        
      }
           
      public function processFile($path) {
         if (!file_exists($path)) {
            throw new PreProcessorException("'$path' not found.", PreProcessorException::NotFound);
         }
         $this->internalDefine('FILENAME', $path, TRUE);
         return $this->processString(file_get_contents($path));
      }

      /**
       * Apply Preprocessing on given src string
       *
       * @param string $src
       *
       * @return string
       */
      public function processString($src) {
         $this->output  = '';
         $token   = token_get_all($src);
         $buffer  = '';
         $lastIdent = "";
         
         $ident = "";
         //$o = array();
         //$r = 0;
         //exec('pcregrep -M -N LF -e "function [^\}]{1,}\}" testfile.php | head -n 2 | tail -n -1',$o,$r);
         //$funcbody = trim(implode("\n", $o));
         
         
         foreach($token as &$tok) {
          //print_r($tok);
           if ($tok[0] == T_FUNCTION) {
             $lastIdent = $ident;
             $ident = $tok[1];  
             //print "$ident $lastIdent \n";
           }
           
            if ($tok[0] == T_STRING ) {
              $lastIdent = $ident;
              $ident = $tok[1];
              
              if (in_array($ident, $this->inlinedFunctions)) {
                if ($lastIdent != "function") {
              //if ($ident == "myfunc" && $lastIdent != "function") {
                  $tok[1] = $this->inlinedFuncData[$ident] . " //";
                  echo "* Inlined call to $ident \n";
                }
              }
              //print "$ident $lastIdent \n";
  
            }
            
            if (is_array($tok) && ($tok[0]==T_COMMENT) && ($tok[1][0]=='#')) {
              if (isset($tok[1][0]) && ($tok[1][1]=='$')) {
                if ($ret = $this->handleVariable($tok[1])) {
                  $tok[1] = "#$ret"; 
                  //continue;  
                }
              } else {
                //default behavior
                if ($this->handleDirective($tok[1])) continue;
              }
            }

            if ($this->skipOver) {
               continue;
            }
            $this->output .= is_array($tok) ? $tok[1] : $tok;
         }
         return $this->output;
      }

      /**
       * Parse and handle a found directive
       *
       * @param string $dir Directive to handle
       *
       * @return boolean
       */
      protected function handleDirective($dir) {
         $candidate = substr(trim($dir),1);
         $parts = explode(' ', $candidate, 2);
         $method = strtolower($parts[0]).'Directive';
         
         if (method_exists($this,$method)) {
            return $this->$method( isset($parts[1]) ? $parts[1] : null );
         }
         return false;
      }
      
      /**
       * Parse and handle a found variable
       *
       * @param string $var variable to handle
       *
       * @return boolean
       */
      protected function handleVariable($var) {
        $candidate = substr(trim($var),1);
        $variable = trim(substr($candidate,1));
        $ret = (isset($this->defines[$variable]) ? $this->defines[$variable] : null);
        if (!$ret)
          $ret = (defined($variable) ? constant($variable) : null);
        return $ret;  
      }      

      /**
       * Handling code for '#define' statements
       *
       * @param string $payload Payload value for statement if given
       *
       * @return boolean
       */
      protected function defineDirective($payload) {
         if (!$this->skipOver) {
            if (is_null($payload)) return false;
            $def = explode(' ', $payload);
            if (isset($this->defines[$def[0]])) {
               throw new PreProcessorException("'{$def[0]}' cannot be redefined..", PreProcessorException::NoRedefine);
            }
            $this->defines[$def[0]] = substr($def[1],1,-1);
         }
         return true;
      }

      protected function inlineDirective($payload) {
        if (!$this->skipOver) {
          if (is_null($payload)) return false;
          $p = explode(",", $payload);
          
          if (isset($this->inlinedFunctions[$p[0]])) {
            throw new PreProcessorException("inline already defined for '".$p[0]."'.");
            return false;
          }
          
          $this->inlinedFunctions[] = $p[0];
          $o = array();
          $r = 0;
          //grab the body of the function with grep.  not ideal.
          //TODO implement a better way to get the function body.
          exec('pcregrep -M -N LF -e "function '.$p[0].'\b[^\}]{1,}\}" "'.$p[1].'" ',$o,$r);

         $i = 0;
          while ($i <= count($o)) {
            if (strpos($o[$i], "{")!==FALSE)
              break; 
            $o[$i] = "// ".$o[$i];
            $i++;
          }
          if (strpos($o[0],"{")!==FALSE) {
            $o[0] = "";
          }
          for($i = 0; $i < count($o); $i++) {
            $o[$i] = "  " .trim($o[$i]);
            if (strncmp($o[$i], "  // ", 5)==0) {
              $o[$i] = "";
            }
            if ($o[$i] == "  {") 
              $o[$i]  = "";
          }
          $o[count($o)-1] = "";
          
          $body = trim( implode("\n", $o) );
          $body  = str_replace("__FUNCTION__", '"'.$p[0].'"', $body);
          
          $this->inlinedFuncData[$p[0]] = $body;

        }
        return true;
      }      

      /**
       * Handling code for '#undef' statements
       *
       * @param string $payload Payload value for statement if given
       *
       * @return boolean
       */
      protected function undefDirective($payload) {
        if (!$this->skipOver) {
          if (is_null($payload)) return false;
          $def = explode(' ', $payload);
          if (isset($this->protectedDefines[$def[0]])) {
            throw new PreProcessorException("'{$def[0]}' is an internal define and cannot be undefined.", PreProcessorException::NoUndefineProtected);
          }
          if (isset($this->defines[$def[0]])) {
            unset($this->defines[$def[0]]);
          }
          //$this->defines[$def[0]] = substr($def[1],1,-1);
        }
        return true;
      }      
      
      protected function undefineDirective($payload) {
        return $this->undefDirective($payload);
      }
      
      /**
       * Handling code for '#include' statements, replacing directive by file contents
       *
       * @param string $payload Payload value with filename to embed
       *
       * @return boolean
       */
      protected function includeDirective($payload) {
         if (!$this->skipOver) {
            if (is_null($payload)) return false;
            $this->output .= file_get_contents(substr($payload,1,-1));
         }
         return true;
      }

      /**
       * Handling code for '#elif' (else if) statements
       *
       * @param string $payload Payload value for statement if given
       *
       * @return boolean
       */
      protected function elifDirective($payload) {
         if (!$this->skipOver) {
            $this->skipOver = true;
            return true;
         }
         return $this->if($payload);
      }

      /**
       * Handling code for '#if' statements, using eval to allow custom php level code for evaluation
       *
       * @param string $payload Payload value for statement
       *
       * @return boolean
       */
      protected function ifDirective($payload) {
         if (is_null($payload)) return false;
         $this->skipOver = @eval('return (' . substr($payload,1,-1) . ') ? false : true;');
         return true;
      }

      /**
       * Handling code for '#ifdef' statements, testing if $payload is set, either as php constant or by #define earlier
       *
       * @param string $payload Payload value containing name of "constant" to test for
       *
       * @return boolean
       */
      protected function ifdefDirective($payload)  {
         if (is_null($payload)) return false;
         $this->skipOver = !(isset($this->defines[$payload]) || defined($payload));
         return true;
      }

      /**
       * Handling code for '#ifndef' statements, testing if $payload is NOT set, either as php constant or by #define earlier
       *
       * @param string $payload Payload value containing name of "constant" to test for
       *
       * @return boolean
       */
      protected function ifndefDirective($payload) {
         if (is_null($payload)) return false;
         $this->skipOver = (isset($this->defines[$payload]) || defined($payload));
         return true;
      }

      /**
       * Handling code for '#else' statements
       *
       * @param string $payload Additional values specified, though not used here
       *
       * @return boolean
       */
      protected function elseDirective($payload) {
         $this->skipOver = !$this->skipOver;
         return true;
      }

      /**
       * Handling code for '#endif' statements, ending a previously opened #if*
       *
       * @param string $payload Additional values specified, though not used here
       *
       * @return boolean
       */
      protected function endifDirective($payload) {
         $this->skipOver = false;
         return true;
      }

      /**
       * Echos out a warning message during preprocessing only.
       * The message will not display when running a cached file. 
       * @param string $payload
       * @return boolean
       */
      protected function warningDirective($payload) {
        if (!$this->skipOver) {
          $this->output .=  "#[PPP] * Preprocessor Warning: $payload\n";
        }
        return true;
      }      
      

      /**
       * Echos out an informational message during preprocessing only.
       * The message will not display when running a cached file. 
       * @param string $payload
       * @return boolean
       */
       protected function messageDirective(&$payload) {
         if (is_null($payload)) return false;
         if (!$this->skipOver) {
          $msg = $payload;
          $parts = explode(' ', $msg);
          $n=0;
          foreach($parts as &$p) {
            if (substr($p,0,2)=='#$')
              $p = $this->handleVariable(trim($p));
          }
          $msg = implode(' ', $parts);
          //$payload  = "";
          $this->output .= "#[PPP] * Preprocessor Message: $msg\n";
        }
        return true;
      }
        
      protected function optDirective($payload) {
        if (!$this->skipOver) {
          $opts = explode(' ', $payload);
          if ($opts[1] == 'on') $opts[1] = 1;
          if ($opts[1] == '+') $opts[1] = 1;
          if ($opts[1] == 'off') $opts[1] = 0;
          if ($opts[1] == '-') $opts[1] = 0;
          
          $this->options[$opts[0]] = intval($opts[1]);
        }
        return true;
      }      
      
      /**
       * internal define function, allows overwriting existing defines
       * @param string $name
       * @param mixed $value
       * @return boolean
       */
      protected function internalDefine($name, $value = 1, $protectedDefine = false) {
        $this->defines[$name] = $value;
        if ($protectedDefine)
          $this->protectedDefines[$name] = 1;
        return true;
      } 
      
      
      /**
       * Registers default defines for the preprocessor, such as the OS, PHP version, etc.
       * 
       * @param boolean $registerEvalDefines
       */
      protected function defineDefaults($registerEvalDefines = FALSE) {
        $this->internalDefine('PHPPREPROCESSOR', 1, TRUE);
        if ($registerEvalDefines && !defined('PHPPREPROCESSOR')) 
          define('PHPPREPROCESSOR', 1);
        
        $this->internalDefine(strtoupper(PHP_OS), 1, TRUE); //Linux,FreeBSD,WINNT,etc.
        //if ($registerEvalDefines)
          //eval("define('".strtoupper(PHP_OS)."', 1);");
        
        $os = strtoupper(explode(' ', php_uname())[0]);
        if ($os=='WINNT') $os = 'WINDOWS';
        $this->internalDefine('OPERATING_SYSTEM', $os); 
        $this->internalDefine('PHP_MAJOR_VERSION', PHP_MAJOR_VERSION); // 5
        $this->internalDefine('PHP_VERSION', PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION);  //5.4
        $this->internalDefine('PHP_VERSION_FULL', PHP_VERSION); //5.4.9-extra
      }
      
      
      /**
       * Initializes internal settings upon object creation.
       */
      protected function internalInitialize() {
        $this->defineDefaults(TRUE);
      }      
   }


}
