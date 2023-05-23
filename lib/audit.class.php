<?php 

class audit
{
    static function log ($logdata) {
        print ( gmdate("YmdHis.u") . ": " . $logdata . "\n");
    }
}
