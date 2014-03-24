<?php

require '../src/preprocessor.php';
require '../src/streamwrapper.php';

if ($argc > 1 && $argv[1]=='-d') {
  $fn = '/tmp/'. sha1(__DIR__.'/testfile.php').'.php';
  echo "deleting $fn \n";
  unlink($fn);
}
  
/*  */
$x = new \TheSeer\Tools\PreProcessor();
//$res = $x->processString(file_get_contents('testfile.php'));
$res = $x->processFile(('testfile.php'));
echo $res;
/* */

stream_wrapper_register('ppp', '\TheSeer\Tools\PreProcessorStream');
\TheSeer\Tools\PreProcessorStream::setCachePath('/tmp');

include 'ppp://'.__DIR__.'/testfile.php';

/**/