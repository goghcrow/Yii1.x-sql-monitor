<?php
// Yii::import("application.components.MDbConnection");
Yii::import("system.db.CDbCommand");
Yii::import("system.db.CDbConnection");

/**
 * MonitorDbConnection class file
 * @author xiaofeng
 */
class MonitorDbConnection extends /*MDbConnection*/ CDbConnection
{
	/* @var array $monitorFilter 监控过滤器 */
	public $monitorFilter = [];

	/* @var string $logdir 监控过滤器 */
	public $logdir = "";

	public function createCommand($query = null)
	{
        if(!$this->logdir) {
            $this->logdir = sys_get_temp_dir();
        }
        if(!$this->monitorFilter) {
            $this->monitorFilter = [
                "type" => "index_subquery",
                "or" => [
                    // "key" => 1,
                    // "possible_keys" => 1,
                    "select_type" => "PRIMARY, UNCACHEABLE SUBQUERY",
                    "rows" => 2000,
                    "Extra" => "Using filesort,Using temporary",
                    "duration" => 0.5,
                ],
            ];
        }
		parent::createCommand($query);
        return new MonitorDbCommand($this, $query, $this->monitorFilter, $this->logdir);
	}
}


