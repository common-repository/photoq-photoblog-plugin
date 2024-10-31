<?php
/**
 * Allows for asynchronous execution of the queued commands via Ajax.
 * Allows to break up commands into batches of duration TIME_LIMIT_MS
 * each of which is executed through an Ajax call. This let's us avoid
 * the PHP execution limit if we have procedures that are time consuming.
 * @author flury
 *
 */
class PhotoQ_Batch_BatchProcessor
{
	/**
	 * Execution time limit in milliseconds. BatchProcessor tries to
	 * split commands into chunks of this duration.
	 */
	const TIME_LIMIT_MS = 500;
	
	private $_id;
	private $_db;
	private $_queuedCommands;
	
	public function __construct($id = NULL){
		$this->_id = $id;
		$this->_db = PhotoQDB::getInstance();
		$this->_initializeCommands();
		register_shutdown_function(array($this, 'makeBatchPersistent'));
	}
	
	private function _initializeCommands(){
		if($this->isRegisteredWithDB())
			$this->_queuedCommands = 
				$this->_db->getQueuedBatchCommands($this->_id);
		else
			$this->_queuedCommands = new PhotoQ_Command_BatchMacro();
	}
	
	/**
	 * Indicates whether this BatchProcessor already registered with the
	 * database and got a valid id in return. 
	 * @return boolean
	 */
	public function isRegisteredWithDB(){
		return !is_null($this->_id);
	}
	
	/**
	 * Write queued commands to database. We need it to be persistent such 
	 * that execution of batch operations can continue at next execution.
	 */
	public function makeBatchPersistent(){
		if($this->isRegisteredWithDB()){
			$this->_db->updateBatch($this->_id, $this->_queuedCommands);
		}
	}
	
	/**
	 * Queue a new command to be executed asynchronously via Ajax.
	 * @param PhotoQ_Command_Batchable $command
	 * @return boolean
	 */
	public function queueCommand(PhotoQ_Command_Batchable $command){
		$this->_queuedCommands->addCommand($command);
		if(!$this->isRegisteredWithDB()){
			return $this->_registerWithDB();
		}
		return true;
	}
	
	private function _registerWithDB(){
		if($id = $this->_db->insertBatch())
			$this->_id = $id;
		else{
			$errStack = PEAR_ErrorStack::singleton('PhotoQ');
			$errStack->push(PHOTOQ_BATCH_REGISTER_FAILED, 'error');
			return false;
		}
		return true;
	}
	
	/**
	 * Processes the queued commands until the timer expires.
	 * @return float percentage of commands completed
	 */
	public function process(){
		$timer = PhotoQ_Util_Timers::getInstance();	
		while($timer->read('batchProcessing') < self::TIME_LIMIT_MS){
			$this->_queuedCommands->execute();
		}
		if(!$this->_queuedCommands->hasCommands()){
			$this->_delete();
		}
		return $this->_queuedCommands->getPercentageDone();
	}
	
	private function _delete(){
		$this->_db->deleteBatch($this->_id);
		$this->_id = NULL;
	}
	
	public function getId(){
		return $this->_id;
	}

}