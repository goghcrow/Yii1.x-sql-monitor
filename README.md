# Yii1.x-sql-monitor
~~~
main.php

return [
	'db' => array(
	    'class' => 'MonitorDbConnection',
	    'connectionString' => 'mysql:host=xxx;dbname=xxx',
	    'monitorFilter' => [
    		    "type" => "index_subquery",
    		    "or" => [
    		        // "key" => 1,
    		        "possible_keys" => 1,
    		        "select_type" => "PRIMARY, UNCACHEABLE SUBQUERY",
    		        "rows" => 1000,
    		        "Extra" => "Using filesort,Using temporary",
    		        "duration" => 0.1,
    		    ],
    		],
	    'logdir' => "log_to_dir",
	),
];

/*
基本条件：
1. type <string> :大于指定类型 e.g. "index_subquery"
    sql执行计划类型 从快到慢 ["system", "const", "eq_ref", "ref", "fulltext", "ref_or_null",
    "index_merge", "unique_subquery", "index_subquery", "range", "index", "all"];
    all 即 全表扫描
2. select_type <string> : 包含制定查询类型，逗号分隔 e.g. "PRIMARY, UNCACHEABLE SUBQUERY"
3. key <int> : 用到的索引数量小于指定值 e.g. 1
4. possible_keys <int> : 可能用到的索引数量大于指定数值 e.g. 1
5. rows <int> : 扫描表行数大于指定值, innodb为非准确值 e.g. 1000
6. Extra <string> : 包含指定值，逗号分隔, e.g. "Using filesort,Using temporary"
7. duration <float> : 实际执行时间大于指定秒 e.g. 0.1

配置为基本条件与and or递归嵌套

e.g.
没用到索引且(扫描表大于1000行， 或 用到了文件排序 或 临时表，或执行时间大于100ms)
[
    "key" => 1,
    "or" => [
        "rows" => 1000,
        "Extra" => "Using filesort,Using temporary",
        "duration" => 0.1
    ],
]
*/
~~~
