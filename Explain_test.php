<?php
require __DIR__ . "/Explain.php";

// 可能有问题sql explain筛选条件
$conf = [
    "select_type" => "PRIMARY, UNCACHEABLE SUBQUERY", // contains 包含类型,逗号分隔
    "type" => "range", // >
    "possible_keys" => 1, // >
    // "key" => 1, // <
    "rows" => 1000, // >
    "Extra" => "Using filesort,Using temporary", // contains
];

// true 满足问题sql条件 false 不满足

$result = ["key" => "1"]; //
assert(Explain::ifNotice($result, $conf) === false);

$result = [ "type" => "range"];
assert(Explain::ifNotice($result, $conf) === false);

$result = ["type" => "index"]; // index > range
assert(Explain::ifNotice($result, $conf) === true);

$result = [ "rows" => 1000];
assert(Explain::ifNotice($result, $conf) === false);

$result = [ "rows" => 1001];
assert(Explain::ifNotice($result, $conf) === true);


$result = [ "Extra" => "xxx"];
assert(Explain::ifNotice($result, $conf) === false);

$result = [ "Extra" => "Using filesort"];
assert(Explain::ifNotice($result, $conf) === true);

$result = [ "Extra" => "Using filesort,Using temporary"];
assert(Explain::ifNotice($result, $conf) === true);

$result = [ "Extra" => "Using filesort,Using temporary, other"];
assert(Explain::ifNotice($result, $conf) === true);


$conf = [
    "and" => $conf
];

$result = ["key" => "1"];
assert(Explain::ifNotice($result, $conf) === false);

$result = [ "type" => "range"];
assert(Explain::ifNotice($result, $conf) === false);

$result = [ "type" => "index"];
assert(Explain::ifNotice($result, $conf) === true);

$result = [ "rows" => 1000];
assert(Explain::ifNotice($result, $conf) === false);

$result = [ "rows" => 1001];
assert(Explain::ifNotice($result, $conf) === true);


$result = [ "Extra" => "xxx"];
assert(Explain::ifNotice($result, $conf) === false);

$result = [ "Extra" => "Using filesort"];
assert(Explain::ifNotice($result, $conf) === true);

$result = [ "Extra" => "Using filesort,Using temporary"];
assert(Explain::ifNotice($result, $conf) === true);

$result = [ "Extra" => "Using filesort,Using temporary, other"];
assert(Explain::ifNotice($result, $conf) === true);


///////////////////////////////////////////////////////////////////////////////////////////////
$result = [
    "select_type" => "PRIMARY, UNCACHEABLE SUBQUERY",
    "type" => "range",
    "possible_keys" => 1,
    "key" => 1,
    "rows" => 1000,
    "Extra" => "Using filesort",
];


$conf = ["key" => 1]; // 1 < 1
assert(Explain::ifNotice($result, $conf) === false);

$conf = ["key" => 2]; // 1 < 2
assert(Explain::ifNotice($result, $conf) === true);


$conf = [ "type" => "index_subquery"]; // range > index_subquery
assert(Explain::ifNotice($result, $conf) === true);

$conf = [ "type" => "index"]; // range > index
assert(Explain::ifNotice($result, $conf) === false);

$conf = [ "rows" => 1000]; // 1000 > 1000
assert(Explain::ifNotice($result, $conf) === false);

$conf = [ "rows" => 999]; // 1000 > 999
assert(Explain::ifNotice($result, $conf) === true);

$conf = [ "Extra" => "xxx"]; // xxx   has    Using filesort,Using temporary
assert(Explain::ifNotice($result, $conf) === false);

$conf = [ "Extra" => "Using filesort"]; // Using filesort    has      Using filesort,Using temporary
assert(Explain::ifNotice($result, $conf) === true);

$conf = [ "Extra" => "Using filesort,Using temporary"]; // Using filesort,Using temporary     has    Using filesort,Using temporary
assert(Explain::ifNotice($result, $conf) === true);

$conf = [ "Extra" => "Using filesort,Using temporary, other"]; // Using filesort,Using temporary, other    has    Using filesort,Using temporary
assert(Explain::ifNotice($result, $conf) === true);


///////////////////////////////////////////////////////////////////////////////////////////////
$result = [
    "select_type" => "PRIMARY",
    "type" => "range",
    "possible_keys" => 1,
    "key" => 1,
    "rows" => 1000,
    "Extra" => "Using filesort",
];


// type级别大于 range，扫描表行数大于999
$conf = [
    "and" => [
        "type" => "index_subquery", // range > index_subquery -> true
        "rows" => 999, // 1000 > 999 -> true
    ]
];
assert(Explain::ifNotice($result, $conf) === true);

$conf = [
    "and" => [
        "type" => "index_subquery", // range > index_subquery  -> true
        "rows" => 1001, // 1000 > 1001 -> false
    ]
];
assert(Explain::ifNotice($result, $conf) === false);

$conf = [
    "and" => [
        "type" => "index", // range > index_subquery -> false
        "rows" => 999, // 1000 > 999 -> true
    ]
];
assert(Explain::ifNotice($result, $conf) === false);


$conf = [
    "or" => [
        "type" => "index_subquery", // range > index_subquery  -> true
        "rows" => 1001, // 1000 > 1001 -> false
    ]
];
assert(Explain::ifNotice($result, $conf) === true);

$conf = [
    "or" => [
        "type" => "index", // range > index_subquery -> false
        "rows" => 999, // 1000 > 999 -> true
    ]
];
assert(Explain::ifNotice($result, $conf) === true);

$conf = [
    "or" => [
        "type" => "index", // range > index_subquery -> false
        "rows" => 1001, // 1000 > 1001 -> false
    ]
];
assert(Explain::ifNotice($result, $conf) === false);


$conf = [
    "key" => 0, // 1 < 0 -> false
    "or" => [
        "type" => "index", // range > index_subquery -> false
        "rows" => 999, // 1000 > 999 -> true
    ]
];
assert(Explain::ifNotice($result, $conf) === false);
assert(Explain::ifNotice($result, $conf, "or") === true);


$conf = [
    "key" => 2, // 1 < 2 -> true
    "or" => [
        "type" => "index", // range > index_subquery -> false
        "rows" => 1001, // 1000 > 1001 -> false
        "and" => [
            "select_type" => "PRIMARY, UNCACHEABLE SUBQUERY", //  PRIMARY in  PRIMARY, UNCACHEABLE SUBQUERY -> true
            "Extra" => "Using filesort,Using temporary", // Using filesort in Using filesort,Using temporary-> true
        ],
    ]
];

assert(Explain::ifNotice($result, $conf, "or") === true);


$conf = [
    "type" => "index_subquery", // range > index_subquery -> true
    "rows" => 1001, // 1000 > 1001 -> false
];

assert(Explain::ifNotice($result, $conf, "and") === false);
assert(Explain::ifNotice($result, $conf, "or") === true);


$conf = [
    "key" => 2, // 1 < 2 -> true
    "or" => [
        "type" => "index", // range > index -> false
        "rows" => 1001, // 1000 > 1001 -> false
        "and" => [
            "select_type" => "PRIMARY, UNCACHEABLE SUBQUERY", //  PRIMARY in  PRIMARY, UNCACHEABLE SUBQUERY -> true
            "Extra" => "Using temporary", // Using filesort in Using temporary-> false
        ],
    ]
];
assert(Explain::ifNotice($result, $conf) === false);


$conf = [
    "key" => 2, // 1 < 2 -> true
    "or" => [
        "type" => "index_subquery", // range > index -> true
        "rows" => 1001, // 1000 > 1001 -> false
        "and" => [
            "select_type" => "PRIMARY, UNCACHEABLE SUBQUERY", //  PRIMARY in  PRIMARY, UNCACHEABLE SUBQUERY -> true
            "Extra" => "Using temporary", // Using filesort in Using temporary-> false
        ],
    ]
];
assert(Explain::ifNotice($result, $conf) === true);


$conf = [
    "key" => 2, // 1 < 2 -> true
    "or" => [
        "type" => "index", // range > index -> false
        "rows" => 1001, // 1000 > 1001 -> false
        "and" => [
            "Extra" => "Using temporary", // Using filesort in Using temporary-> false
            "select_type" => "UN_EXIST", //  UN_EXIST  has PRIMARY, UNCACHEABLE SUBQUERY -> false
        ],
    ]
];
assert(Explain::ifNotice($result, $conf) === false);
assert(Explain::ifNotice($result, $conf, "or") === true);
