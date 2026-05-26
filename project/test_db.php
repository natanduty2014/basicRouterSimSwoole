<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions/db/hyperfDB/initHyperfDb.php';
initHyperfDb();

use Hyperf\DbConnection\Db;

try {
    $unit = Db::table('tb_unidades')->where('uni_slug', 'ponta-negra')->first();
    echo "Unit:\n";
    print_r($unit);

    $tables = Db::select('SHOW TABLES');
    echo "\nTables:\n";
    print_r($tables);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
