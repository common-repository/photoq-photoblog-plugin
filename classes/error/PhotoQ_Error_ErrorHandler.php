<?php 

/**
 * This class helps ErrorStack in handling errors
 * @author manu
 *
 */
class PhotoQ_Error_ErrorHandler
{
	
	var $_defaultMsgCallback;
	
	function __construct($defaultMsgCallback){
		$this->_defaultMsgCallback = $defaultMsgCallback;
	}
	
    function errorMessageCallback($stack, $err)
    {
 		$message = '<li>';
 		$message .= call_user_func_array($this->_defaultMsgCallback, array($stack, $err));
		$message .= '</li>';
		return $message;
    }
    
    /**
     * Called statically. Shows all errors that accumulated on the stack
     * @return unknown_type
     */
    function showAllErrors($stack, $print = true){
    	$msg = PhotoQ_Error_ErrorHandler::showAllErrorsExcept($stack, array(), false);
    	if($print) echo $msg;
  		return $msg;
    }
    
    /**
     * Similar to showAllErrors but let's us exclude the error codes given
     * in the array exclude
     * @param $stack
     * @param $exclude array	Error codes to exclude
     * @param $print
     * @return unknown_type
     */
	function showAllErrorsExcept($stack, $exclude = array(), $print = true){
    	$msg = PhotoQ_Error_ErrorHandler::showErrorsByLevel($stack, 'info', 'updated fade', $exclude, false);
    	$msg .= PhotoQ_Error_ErrorHandler::showErrorsByLevel($stack, 'error', 'error', $exclude, false);
    	
    	if($print) echo $msg;
  		
  		return $msg;
    }
    
    /**
     * Show only errors of a given level, excluding error codes given in other parameter
     * @param $stack
     * @param $level
     * @param $cssClass
     * @param $exclude
     * @param $print
     * @return unknown_type
     */
	function showErrorsByLevel($stack, $level = 'error', $cssClass = 'error', $exclude = array(), $print = true){
    	//show errors if any
    	$msg = '';
    	if ($stack->hasErrors($level)) {
    		$errMsgs = '';
    		//with purging it doesn't work -> bug in errorStack under PHP5
    		foreach ($stack->getErrors(false, $level) as $err){
    			if(!in_array($err['code'],$exclude))
    				$errMsgs .= $err['message'];
    		}
    		if($errMsgs)
    			$msg .= '<div class="'.$cssClass.'"><ul>'.$errMsgs.'</ul></div>';
    	}
		    	
  		if($print) echo $msg;
  		
  		return $msg;
    }
    
    /**
     * Push callback used to disable PHOTOQ_PHOTO_NOT_FOUND error types.
     * @param $err
     * @return unknown_type
     */
    function mutePHOTOQ_PHOTO_NOT_FOUND($err){
    	return PhotoQ_Error_ErrorHandler::muteError($err, PHOTOQ_PHOTO_NOT_FOUND);
    }
    
	
	/**
     * Push callback used to disable PHOTOQ_FIELD_EXISTS error types.
     * @param $err
     * @return unknown_type
     */
    function mutePHOTOQ_FIELD_EXISTS($err){ 	
    	return PhotoQ_Error_ErrorHandler::muteError($err, PHOTOQ_FIELD_EXISTS);
    }
    
    function muteError($err, $errCode){
    	if ($err['code'] == $errCode){
    		return PEAR_ERRORSTACK_IGNORE;
    	}
    }

}