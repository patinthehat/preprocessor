<?php

/*
 * Demo App
 */

#ifdef PHPPREPROCESSOR
  #message "* PHP PreProcessor is running.\n\n"
#endif

#inline myfunc,testfile.php

#opt warnings on
#opt defineconsts on
#opt notemessages off
#define test1 "hello"

#define foo "bar"
#undef foo


function myfunc()
{  
  echo "hello from ";
  echo __FUNCTION__ . PHP_EOL;
}

function myfunc2() {  
  echo "hello from ".__FUNCTION__."()\n";
}

#message "testing ifdef/else/endif block..."

#ifdef foo
   echo "hallo!\n";
   myfunc();
   myfunc2();
#else
   echo "--else--\n";
   myfunc();
   myfunc2();
#endif
   
$myvar1 = "one two three";

#message "done testing ifdef block; $myvar1=1234; echo PHP_VERSION test $myvar1"
#include "testinclude.txt"

 echo date("\nY-M-d H:i:s \n");
 myfunc();
 myfunc2();

#if OPERATING_SYSTEM == "LINUX"
#warning "this is a test warning message LINUX!"
echo "PHPPREPROCESSOR = " . constant('PHPPREPROCESSOR') . PHP_EOL;
echo 'PPPDEFS=' . constant('PPPDEFS') . PHP_EOL;
#endif
#--------
#message php version = #$PHP_VERSION #$OPERATING_SYSTEM $test1 = #$test1
#message pppdefs = #$FILENAME
#$test1 

#$PHPPREPROCESSOR

#php version is 
#$PHP_VERSION
#--------
echo "PHP_MAJOR_VERSION = ". PHP_MAJOR_VERSION . PHP_EOL;
echo "--- END OF ".basename(__FILE__)." --- \n";
?>