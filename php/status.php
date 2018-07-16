<?php
# filename: status.php
# Used to get ABR status but also change it - turn on/off ABR

include('abrlib.php');
$abr = new abrlib();

$cmd  = $_POST['cmd'];

if( $cmd !== 'clear_log' && ABR_PROCESS_CONTROL !== true) {
    print($abr->response("LOCKED: Development is taking place. ABR cannot be run.", 200)) and die();
}

switch($cmd) {

    case "turn_on":
        print($abr->response($abr->set_abr_status(1)));
        break;
    case "turn_off":
        print($abr->response($abr->set_abr_status(0)));
        break;
    case "get_status":
        print($abr->response($abr->get_abr_status()));
        break;
    case "clear_log":
        print($abr->response($abr->clear_log()));
        break;
    default:
        print($abr->response("BAD REQUEST: You asked for something but I don't understand/expect.", 400)) and die();
}

?>
