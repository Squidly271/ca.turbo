<?PHP
###################################
#                                 #
# CA Automatic Turbo Mode         #
# Copyright 2017, Andrew Zawadzki #
#                                 #
###################################

require_once("/usr/local/emhttp/plugins/ca.turbo/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ca.turbo/include/paths.php");

switch ($_POST['action']) {
  case 'apply':
    $rawSettings = getPostArray('settings');
    $settings = getSettings($rawSettings);
    $rawSchedule = getPostArray('schedule');
        
    exec("mkdir -p /boot/config/plugins/ca.turbo");
    file_put_contents("/boot/config/plugins/ca.turbo/settings.ini",create_ini_file($settings));
# create the cron schedule    
    writeJsonFile($turboPaths['schedule'],$rawSchedule);
		$cronFile = "";
    if ( is_array($rawSchedule) ) {
      foreach ($rawSchedule as $schedule) {
        if ( $schedule[0] == "no" ) {
          continue;
        }
        $cronFile .= trim($schedule[2])." /usr/local/emhttp/plugins/ca.turbo/scripts/turboSchedule.php {$schedule[1]} {$schedule[3]} > /dev/null 2>&1\n";
      }
    }
    if ( ! $cronFile ) {
      @unlink($turboPaths['cronFile']);
    } else {
      file_put_contents($turboPaths['cronFile'],"# Generated Schedule for CA Auto Turbo Mode\n$cronFile");
    }
    exec("/usr/local/sbin/update_cron");
      
    if ( $settings['enabled'] == 'no' ) {
      if ( is_file($turboPaths['backgroundPID']) ) {
        logger("Stopping Auto Turbo");
        $PID = file_get_contents($turboPaths['backgroundPID']);
        posix_kill($PID,SIGKILL);
        @unlink($turboPaths['backgroundPID']);
        @unlink($turboPaths['status']);
        logger("Setting write method to unRaid defined");
        $unRaidVars = parse_ini_file("/var/local/emhttp/var.ini");
        exec("/usr/local/sbin/mdcmd set md_write_method ".$unRaidVars['md_write_method']);
      }
    }
    if ( $settings['enabled'] == 'yes' ) {
      if ( is_file($turboPaths['backgroundPID']) ) {
        logger("Stopping Auto Turbo");
        $PID = file_get_contents($turboPaths['backgroundPID']);
        posix_kill($PID,SIGKILL);
        @unlink($turboPaths['backgroundPID']);
      }
      logger("Starting Auto Turbo");
      $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
        1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
        2 => array("pipe", "w") // stderr is a file to write to
      );
      proc_open("/usr/local/emhttp/plugins/ca.turbo/scripts/auto_turbo.php",$descriptorspec,$pipes);
    }        
    echo "Settings Updated";
    break;
  case 'status':
    $status = readJsonFile($turboPaths['status']);
    if ( (! is_file($turboPaths['backgroundPID']) ) && (! $status['override'] ) ) {
      $unRaidVars = parse_ini_file("/var/local/emhttp/var.ini");
      if ($unRaidVars['md_write_method'] == "1") {
        $status['mode'] = "turbo";
      }
    }
    $spunDown = ( $status ) ? $status['spundown'] : "<font color=red>Script Not Running</font>";

    $o = "<script>";
    $o .= "  $('#spunDown').html('$spunDown');";
    $msg = ($status['mode'] == "turbo") ? "Turbo (Reconstruct Write)" : "Normal (Read/Modify/Write)";
    
    if ( $status['override'] ) {
      $msg .= "  Autoscript overridden";
    }
    if ( (! is_file($turboPaths['backgroundPID']) ) && (! $status['override'] ) ) {
      $msg = "unRaid Determined";
    }

    $o .= "  $('#turboOn').html('$msg');";
    if ( is_file($turboPaths['backgroundPID']) ) {
      $o .= "  $('#running').html('<font color=green>Running</font>');";
    } else {
      $o .= "  $('#running').html('<font color=red>Not Running</font>');";
    }
    
    $dirContents = array_diff(scandir("/tmp/ca.turbo/otherPIDs/"),array(".",".."));
    if ( ! empty($dirContents) ) {
      $o .= "$('#killSchedule').prop('disabled',false);";
    } else {
      $o .= "$('#killSchedule').prop('disabled',true);";
    }
    
    $o .= "</script>";
    echo $o;
    break;
  
  case 'addSchedule':
    $rawSchedule = getPostArray("schedule");
    if ( ! is_array($rawSchedule) ) {
      $rawSchedule = array();
    }

    $index = 0;
		$o = "";
    foreach ($rawSchedule as $schedule) {
      $o .= createSchedule($index,$schedule);
      $o .= "<hr>";
      $index++;
    }
    $o .= createSchedule($index,array("no","enable","","60"));
    echo $o;
    break;
  case 'showSchedule':
    $rawSchedule = getPostArray("schedule");
    if ( ! is_array($rawSchedule) ) {
      $rawSchedule = array();
    }
    $index = 0;
		$o = "";
    foreach ($rawSchedule as $schedule) {
      $o .= createSchedule($index,$schedule);
      $o .= "<hr>";
      $index++;
    }
    if ( ! $o ) {
      $o = "No Schedules Defined";
    }
    echo $o;
    break;
  case 'initSchedule':
    $rawSchedule = readJsonFile($turboPaths['schedule']);
    if ( ! is_array($rawSchedule) ) {
      $rawSchedule = array();
    }
    $index = 0;
		$o = "";
    foreach ($rawSchedule as $schedule) {
      $o .= createSchedule($index,$schedule);
      $o .= "<hr>";
      $index++;
    }
    if ( ! $o ) {
      $o = "No Schedules Defined";
    }
    echo $o;
    break;
  case 'validateSchedule':
    $rawSchedule = getPostArray("schedule");
    if ( ! is_array($rawSchedule) ) {
      $rawSchedule = array();
    }
    $index = 0;
		$status = "";
    foreach ($rawSchedule as $schedule) {
      if ( $schedule[0] == "yes" ) {
        if ( ! trim($schedule[2]) ) {
          $status .= "Blank cron entry on schedule $index<br>";
        }
        if ( $schedule[3] < 1 ) {
          $status .= "Duration time cannot be less than one minute on schedule $index<br>";
        }
      }
      $index++;
    }
    if ( ! $status ) {
      $status = "ok";
    } else {
      $status = "<font color='red'>$status</font>";
    }
    echo $status;
    break;
  case 'killSchedule':
    unlink("/tmp/ca.turbo/status.json");
    $dirContents = array_diff(scandir("/tmp/ca.turbo/otherPIDs"),array(".",".."));
    logger("Forcibly stopping all CA Auto Turbo Schedules");
    foreach ($dirContents as $PID) {
      posix_kill($PID,SIGKILL);
      @unlink("/tmp/ca.turbo/otherPIDs/$PID");
    }
    $settings = parse_ini_file("/boot/config/plugins/ca.turbo/settings.ini");
    if ( $settings['enabled'] == "yes" ) {
      logger("Starting Auto Turbo");
      $descriptorspec = array(
        0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
        1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
        2 => array("pipe", "w") // stderr is a file to write to
      );
      proc_open("/usr/local/emhttp/plugins/ca.turbo/scripts/auto_turbo.php",$descriptorspec,$pipes);     
    } else {
      $unRaidSettings = parse_ini_file("/boot/config/disk.cfg");
      logger("Restoring unRaid write settings");
      exec("/usr/local/sbin/mdcmd set md_write_method {$unRaidSettings['md_write_method']}");
    }
    break;
    
  
}
      

?>