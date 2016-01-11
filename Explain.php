<?php
// http://dev.mysql.com/doc/refman/5.5/en/explain-output.html
// $types = ["system", "const", "eq_ref", "ref", "fulltext", "ref_or_null",
//	"index_merge", "unique_subquery", "index_subquery", "range", "index", "all"];

/**
 * @author xiaofeng
 * 2060108
 * Class Explain
 */
class Explain
{
    /**
     * 支持检测的explain返回值key
     * 添加sql实际执行时间消耗 duration
     * @var array
     */
    private static $support = ["select_type", "type", "possible_keys", "key", "rows", "extra", "duration"];

    private static function str_trim_lower($str)
    {
        return strtolower(trim(strval($str)));
    }

    private static function array_trim_lower(array $arr)
    {
        return array_map("self::str_trim_lower", $arr);
    }

    private static function str_ncase_in_array($str, array $str_arr)
    {
        return in_array(self::str_trim_lower($str), self::array_trim_lower($str_arr), true);
    }

    /**
	 * 递归转化数组key 为小写
	 * @param array $arr
	 * @return array
	 */
	private static function recursivKeyToLower(array $arr)
	{
        $ret = [];
		if(!$arr) {
            return $ret;
        }

		foreach($arr as $k => $v) {
			if(is_array($v)) {
				$ret[is_int($k) ? $k : self::str_trim_lower($k)] = self::recursivKeyToLower($v);
			} else {
				$ret[is_int($k) ? $k : self::str_trim_lower($k)] = $v;
			}
		}
		return $ret;
	}

	/**
	 * 获取type level
	 * @param $typeName
	 * @return int|mixed
	 */
	private static function getTypeLevel($typeName)
	{
		// fast -> slow
		static $types = ["system", "const", "eq_ref", "ref", "fulltext", "ref_or_null",
			"index_merge", "unique_subquery", "index_subquery", "range", "index", "all"];

		$typeName = self::str_trim_lower($typeName);
		$index = array_search($typeName, $types);
		return $index === false ? count($types) - 1 : $index;
	}

	/**
	 * 字符串分隔 去空格 转小写，计算是否有交集
	 * contains("Using filesort,x" , "Using filesort,Using temporary")  -> true
	 * @param $explainResult
	 * @param $userCond
	 * @return bool
	 */
	private static function contains($explainResult, $userCond, $seq = ",")
	{
		$resule = self::array_trim_lower(explode($seq, $explainResult));
		$conf = self::array_trim_lower(explode($seq, $userCond));
		return boolval(array_intersect($resule, $conf));
	}

	/**
     * 单条配置测试
     * 扩充duration选项
     * 修改：配置项添加于测试逻辑
	 * @param $explainKey string 数据库 explain结果key
	 * @param $explainResult string 数据库 explain结果value
	 * @param $userCond string 筛选配置项
	 * @return bool
	 */
	private static function ifKeyNotice($explainKey, $explainResult, $userCond)
	{
		switch(strtolower(trim($explainKey))) {
			case "select_type":
				// 包含用户配置的逗号分隔不可接受查询之一则提醒
				return self::contains($explainResult, $userCond);

			case "type":
				// 大于用户设置最小可接受级别
				return self::getTypeLevel($explainResult) > self::getTypeLevel($userCond);

			case "possible_keys":
				// 大于最小扫可能使用的key数量提醒
				return intval($explainResult) > intval($userCond);

			case "key":
				// 小于最小使用试用key数量
				return intval($explainResult) < intval($userCond);

			case "rows":
				// 大于最小扫描行数提醒
				return intval($explainResult) > intval($userCond);

			case "extra":
				// 包含用户配置的逗号分隔关键词之一则提醒
				return self::contains($explainResult, $userCond);

            case "duration":
                // sql实际执行时间花费大于指定时间则提醒
                return floatval($explainResult) > floatval($userCond);

			default:
				// 其他情况返回不提醒
				return false;
		}
	}

    /**
     * 关系处理
     * @param $allCount int
     * @param $noticeCount int
     * @param $relation string
     * @return bool
     */
    private static function relation($allCount, $noticeCount, $relation = "and")
    {
        $allCount = intval($allCount);
        $noticeCount = intval($noticeCount);
        $relation = strtolower($relation);

        if(!$allCount && !$noticeCount){
            return false;
        }

        if($relation === "and") {
            return $allCount === $noticeCount;
        } else if($relation === "or") {
            return $noticeCount > 0;
        } else  {
            // relation error
            return true;
        }
    }

    /**
     * 单层（一维关联数组）配置测试
     * @param string $explainKey
     * @param string $explainVal
     * @param array $filterConf
     * @return bool
     */
    private static function ifConfarrNotice($explainKey, $explainResult, array $filterConf)
    {
        $explainKey = self::str_trim_lower($explainKey);
        if(!isset($filterConf[$explainKey])) {
            return false;
        }
        return self::ifKeyNotice($explainKey, $explainResult, $filterConf[$explainKey]);
    }

    /**
     * @param string $explainKey
     * @param string $userCond
     * @param array $explainResultArr
     * @return bool
     */
    private static function ifRetarrNotice($explainKey, $userCond, array $explainResultArr)
    {
        $explainKey = self::str_trim_lower($explainKey);
        if(!isset($explainResultArr[$explainKey])) {
            return false;
        }
        return self::ifKeyNotice($explainKey, $explainResultArr[$explainKey], $userCond);
    }

    public static function createNoticeFunc(array $multiDimFilterConf) {
        return function(array $explainResultArr, $relation = "and") use($multiDimFilterConf) {
            return self::ifNotice($explainResultArr, $multiDimFilterConf, $relation);
        };
    }

    /**
     * @param array $explainResultArr
     * @param array $multiDimFilterConf
     * @param string $relation
     * @return bool
     */
	public static function ifNotice(array $explainResultArr, array $multiDimFilterConf, $relation = "and")
	{
		if(!$explainResultArr || !$multiDimFilterConf) {
			return false;
		}

        // 转小写
        $relation = strtolower($relation);
		$explainResultArr = self::recursivKeyToLower($explainResultArr);
		$multiDimFilterConf = self::recursivKeyToLower($multiDimFilterConf);

		$flagAll = 0;
		$flagNotice = 0;

        // 过滤key
        $support = array_merge(array_intersect(self::$support, array_keys($explainResultArr)), ["and", "or"]);

        // 遍历嵌套配置
		foreach($multiDimFilterConf as $rel => $filterConf) {
            if(!self::str_ncase_in_array($rel, $support)) {
                continue;
            }

            // 处理关系，递归遍历自配置项目
            if($rel === "or") {
                $ifNotice = self::ifNotice($explainResultArr, $filterConf, "or");
            }
            else if($rel === "and") {
                $ifNotice = self::ifNotice($explainResultArr, $filterConf, "and");
            }
            // 处理filter项目
            else {
                $ifNotice = intval(self::ifRetarrNotice($rel, $filterConf, $explainResultArr));
			}

            // 短路处理
            if($relation === "and" && !$ifNotice) {
                return false;
            }
            if($relation ==="or" && $ifNotice) {
                return true;
            }

            $flagAll++;
            $flagNotice += intval($ifNotice);
		}

        return self::relation($flagAll, $flagNotice, $relation);
	}

}