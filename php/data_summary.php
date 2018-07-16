<?php
# filename: data_summary.php
# Used to get data summary of a specified type

include('abrlib.php');
$abr = new abrlib();

$type = $_GET['type'];

switch($type) {

    case "queue":
        print($abr->response($abr->get_queue_summary()));
        break;
    case "results":
        print($abr->response($abr->get_results_summary()));
        break;
    default:
        print($abr->response("BAD REQUEST: You asked for a data summary of a type I don't expect.", 400)) and die();
}

?>
