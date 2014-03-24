<?php
/**
 * PreProcessorException
 */

namespace TheSeer\Tools {
  
  /**
   * Exception class for PreProcessor
   *
   * @author     Arne Blankerts <arne@blankerts.de>
   * @copyright  Arne Blankerts <arne@blankerts.de>, All rights reserved.
   */
  class PreProcessorException extends \Exception {
    const NotFound              = 1;
    const NoRedefine            = 2;
    const NoUndefineProtected   = 3;
  }

}