<?php
Yii::import("application.components.Explain");

/**
 * MonitorDbCommand class file
 * @author xiaofeng
 */
class MonitorDbCommand extends CDbCommand
{
    /* @var PDOStatement $_statement_explain */
    private $_statement_explain;

    /* @var MonitorDbConnection $_statement_explain */
    private $_connection;

    /* @var Closure $explainFilter */
    private $explainFilter;

    /* @var ReflectionObject $reft */
    private $reft;

    /* @var array $reftCached */
    private $reftCached;

    /* @var bool $toExplain */
    private $toExplain = false;

    public function __construct(MonitorDbConnection $connection, $query=null)
    {
		parent::__construct($connection, $query);
        $this->_connection = $connection;
		$this->reft = new ReflectionClass("CDbCommand");
        $this->explainFilter = Explain::createNoticeFunc($connection->monitorFilter);
    }

    /**
     * get parent private property
     * @param $k
     * @return mixed
     */
    private function getP($k)
    {
        $_k = "property.$k";
        if(!isset($this->reftCached[$_k])) {
            $this->reftCached[$_k] = $this->reft->getProperty($k);
            /* @var ReflectionProperty $rp */
            $rp = $this->reftCached[$_k];
            $rp->setAccessible(true);
        } else {
            $rp = $this->reftCached[$_k];
        }

        /* @var ReflectionProperty $rp */
        return $rp->getValue($this);
    }

    /**
     * set parent private property
     * @param $k
     * @param $v
     */
    private function setP($k, $v)
    {
        $_k = "property.$k";
        if(!isset($this->reftCached[$_k])) {
            /* @var ReflectionProperty $rp */
            $this->reftCached[$_k] = $this->reft->getProperty($k);
            /* @var ReflectionProperty $rp */
            $rp = $this->reftCached[$_k];
            $rp->setAccessible(true);
        } else {
            $rp = $this->reftCached[$_k];
        }

        /* @var ReflectionProperty $rp */
        $rp->setValue($this, $v);
    }

    /**
     * call parent private method
     * args name, arg1,arg2...
     * @return mixed
     */
    private function callP()
    {
        $args = func_get_args();
        $name = array_shift($args);

        $_name = "method.$name";
        if(!isset($this->reftCached[$_name])) {
            $this->reftCached[$_name] = $this->reft->getMethod($name);
            /* @var ReflectionMethod $rm */
            $rm = $this->reftCached[$_name];
            $rm->setAccessible(true);
        } else {
            $rm = $this->reftCached[$_name];
        }

        return $rm->invokeArgs($this, $args);
    }

    protected function log($sql, $bindParams, $explain)
    {
        $logdir = $this->_connection->logdir;
        if(!$logdir) {
            return;
        }

        $sql = !$bindParams ? $sql : "$sql\nBinded with:\n" . var_export($bindParams, true);
        $explainStr = var_export($explain, true);
        $now = date("Y-m-d H:i:s", time()) . "\n";
        $request_uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
        $connectionStr = $this->_connection->connectionString;
        $username = $this->_connection->username;

        $log = "$now\n";
        $log .= "<<REQUEST_URI>>\n$request_uri\n\n";
        $log .= "<<CONNECTION>>\n{$connectionStr};user=$username\n\n";
        $log .= "<<SQL>>\n$sql\n\n<<Explain>>\n$explainStr\n";
        $log .= str_repeat("=", 100) . "\n\n";

        $fname = "sql_monitor_" . str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ["_"], $request_uri);
        if(!file_exists($logdir)) {
            mkdir($logdir);
        }

        file_put_contents("$logdir/$fname.log", $log, FILE_APPEND);
        // Yii::log($log, CLogger::LEVEL_INFO, $this->_connection->logdir);
    }

    private function explain(array $params = [], $duration)
    {
        try {
            if($params === [] ) {
                $this->_statement_explain->execute();
            } else {
                $this->_statement_explain->execute($params);
            }
        } catch (Exception $e) {
            Yii::log('Error in execute Explain SQL: '.$this->getText(),CLogger::LEVEL_ERROR,'system.db.CDbCommand');
            return;
        }

        $explain = $this->_statement_explain->fetchAll(PDO::FETCH_ASSOC);
        $explain = $explain ? $explain[0] : [];

        // explain 扩充 duration
        $explain["duration"] = $duration;

        $explainFilter = $this->explainFilter;
        if($explainFilter($explain)) {
            $sql = $this->getText();
            $bindParams = array_merge($this->getP("_paramLog"), $params);
            $this->log($sql, $bindParams, $explain);
        }
    }

    public function getPdoExplainStatement()
    {
        return $this->_statement_explain;
    }

    public function __sleep()
    {
        $this->_statement_explain = null;
        $this->toExplain = false;
        return parent::__sleep();
    }

    public function reset()
    {
        $this->_statement_explain = null;
        $this->toExplain = false;
        return parent::reset();
    }

    public function cancel()
    {
        $this->_statement_explain = null;
        $this->toExplain = false;
        parent::cancel();
    }

    public function prepare()
    {
        parent::prepare();

        $this->toExplain = strncasecmp(ltrim($this->getText()), "select", strlen("select")) === 0;
        if($this->toExplain && $this->_statement_explain == null) {
            try {
                $this->_statement_explain=$this->getConnection()->getPdoInstance()->prepare("EXPLAIN " . $this->getText());
            } catch(Exception $e) {
                Yii::log('Error in preparing Explain SQL: '.$this->getText(),CLogger::LEVEL_ERROR,'system.db.CDbCommand');
                $errorInfo=$e instanceof PDOException ? $e->errorInfo : null;
                throw new CDbException(Yii::t('yii','CDbCommand failed to prepare the SQL statement: {error}',
                    array('{error}'=>$e->getMessage())),(int)$e->getCode(),$errorInfo);
            }
        }
    }

    public function bindParam($name, &$value, $dataType=null, $length=null, $driverOptions=null)
    {
        $this->prepare();
        if($this->toExplain) {
            if($dataType === null)
                $this->_statement_explain->bindParam($name,$value,$this->getP("_connection")->getPdoType(gettype($value)));
            elseif($length === null)
                $this->_statement_explain->bindParam($name,$value,$dataType);
            elseif($driverOptions === null)
                $this->_statement_explain->bindParam($name,$value,$dataType,$length);
            else
                $this->_statement_explain->bindParam($name,$value,$dataType,$length,$driverOptions);
        }

        parent::bindParam($name, $value, $dataType, $length, $driverOptions);
        return $this;
    }

    public function bindValue($name, $value, $dataType=null)
    {
        $this->prepare();
        if($this->toExplain) {
            if($dataType === null)
                $this->_statement_explain->bindValue($name,$value,$this->getP("_connection")->getPdoType(gettype($value)));
            else
                $this->_statement_explain->bindValue($name,$value,$dataType);
        }

        parent::bindValue($name, $value, $dataType);
        return $this;
    }

    public function bindValues($values)
    {
        $this->prepare();
        if($this->toExplain) {
            foreach($values as $name=>$value) {
                $this->_statement_explain->bindValue($name,$value, $this->getP("_connection")->getPdoType(gettype($value)));
            }
        }

        parent::bindValues($values);
        return $this;
    }

	public function execute($params = [])
	{
        $_start = microtime(true);
        $n = parent::execute($params);
        $cost = microtime(true) - $_start;
        if($this->toExplain) {
            $this->explain($params, $cost);
        }
        return $n;
	}

	private function queryInternal($method, $mode, $params = [])
	{
        $_start = microtime(true);
        $dr = $this->callP("queryInternal", $method, $mode, $params);
        $cost = microtime(true) - $_start;
        if($this->toExplain) {
            $this->explain($params, $cost);
        }
        return $dr;
	}

    public function query($params=array())
    {
        return $this->queryInternal('',0,$params);
    }

    public function queryAll($fetchAssociative =true,$params=array())
    {
        return $this->queryInternal('fetchAll',$fetchAssociative ? $this->getP("_fetchMode") : PDO::FETCH_NUM, $params);
    }

    public function queryRow($fetchAssociative=true,$params=array())
    {
        return $this->queryInternal('fetch',$fetchAssociative ? $this->getP("_fetchMode") : PDO::FETCH_NUM, $params);
    }

    public function queryScalar($params=array())
    {
        $result=$this->queryInternal('fetchColumn',0,$params);
        if(is_resource($result) && get_resource_type($result)==='stream')
            return stream_get_contents($result);
        else
            return $result;
    }

    public function queryColumn($params=array())
    {
        return $this->queryInternal('fetchAll',array(PDO::FETCH_COLUMN, 0),$params);
    }
}
