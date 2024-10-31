<?php
class PhotoQ_Error_Exception extends Exception
{
	private $_errStack;
	
	public function __construct($message=NULL, $code=0){
		parent::__construct($message, $code);
		$this->_errStack = PEAR_ErrorStack::singleton('PhotoQ');
	}
	
	public function __toString(){
		$msg = '<div class="error">';
		$msg .= $this->getMessage();
		$msg .= '</div>';
		return $msg;
	}
	
	public function pushOntoErrorStack(){
		$this->_errStack->push(PHOTOQ_EXCEPTION_MESSAGE, 'error', array(), $this->getMessage());
	}
}