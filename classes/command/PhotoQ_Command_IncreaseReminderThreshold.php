<?php
class PhotoQ_Command_IncreaseReminderThreshold implements PhotoQ_Command_Executable
{
	public function execute(){
		$secondsPerDay = 86400;
		$reminderThreshold = get_option('wimpq_reminder_threshold');
		$then = get_option('wimpq_last_reminder_reset');
		if(time() - $then > $secondsPerDay)
			$reminderThreshold *= 2; //don't bother guys who donated too often, exponential increase
		update_option('wimpq_reminder_threshold', $reminderThreshold);
	}
}