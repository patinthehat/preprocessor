<?php

/*
 * Demo App
 */
#define foo "bar"
# undef foo

#ifdef foo
   echo "hallo!\n";
#else
   echo "--else--\n";
#endif

#warning "this is a test warning message"

echo "--- END OF ".basename(__FILE__)." --- \n";
?>