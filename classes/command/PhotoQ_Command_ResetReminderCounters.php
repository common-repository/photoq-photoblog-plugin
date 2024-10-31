<?php
class PhotoQ_Command_ResetReminderCounters implements PhotoQ_Command_Executable
{
	public function execute(){
		update_option('wimpq_posted_since_reminded', 0);
		update_option('wimpq_last_reminder_reset', time());
	}
}