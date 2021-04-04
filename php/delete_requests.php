<?php
# filename: delete_requests.php
# purpose: delete DB data, results and zip files associated with a case study

include('abrlib.php');
$abr = new abrlib();

$case_study = $_POST['case_study'];
$instruction = $_POST['instruction'];

# check multiple case_study names in array: ^[a-z0-9_]{4,16}$
if( strcmp($instruction, "delete_multiple_full") === 0 ) {
  foreach( $case_study as $cs ) {
    if( ! preg_match('/^[a-z0-9_]{4,16}$/', $cs) ) {
      print($abr->response("CASE STUDY NAME ERROR: The case study name isn't valid. [$cs]", 400)) and die();
    }
  }
} else {
  # check case_study: ^[a-z0-9_]{4,16}$
  if( ! preg_match('/^[a-z0-9_]{4,16}$/', $case_study) ) {
      print($abr->response("CASE STUDY NAME ERROR: The case study name isn't valid. [$case_study]", 400)) and die();
  }
}

# determine which instruction was sent
switch($instruction) {
    case "delete_queued":
        if($abr->delete_queued_requests($case_study)) {
            print($abr->response("Success"));
        } else {
            print($abr->response("DELETION ERROR: Queue deletion failed for some reason.", 400)) and die();
        }
        break;
    case "delete_full":
        if($abr->delete_case_study($case_study)) {
            print($abr->response("Success"));
        } else {
            print($abr->response("DELETION ERROR: Case study deletion failed for some reason.", 400)) and die();
        }
        break;
    case "delete_multiple_full":
        # iterate through list
        foreach( $case_study as $cs ) {
            # try deleting
            if(! $abr->delete_case_study($cs)) {
                # if any fails, die 
                print($abr->response("DELETION ERROR: Case study [" . cs . "] deletion failed for some reason.", 400)) and die();
            }
        }
        # all went well, send confirmation
        print($abr->response("Success"));
        break;
    default:
        print($abr->response("BAD REQUEST: You sent instructions I don't understand/expect.", 400)) and die();
}

?>
