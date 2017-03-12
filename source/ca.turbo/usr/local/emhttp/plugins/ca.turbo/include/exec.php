<?PHP
require_once("/usr/local/emhttp/plugins/ca.turbo/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ca.turbo/include/paths.php");

switch ($_POST['action']) {
  case 'apply':
    $rawSettings = getPostArray('settings');
    $settings = getSettings($rawSettings);
    
    exec("mkdir -p /boot/config/plugins/ca.turbo");
    file_put_contents("/boot/config/plugins/ca.turbo/settings.ini",create_ini_file($settings));
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
        2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
      );
      proc_open("/usr/local/emhttp/plugins/ca.turbo/scripts/auto_turbo.php",$descriptorspec,$pipes);
    }        
    echo "Settings Updated";
    break;
  case 'status':
    $status = readJsonFile($turboPaths['status']);
    if ( ! $status ) {
      $unRaidVars = parse_ini_file("/var/local/emhttp/var.ini");
      if ($unRaidVars['md_write_method'] == "1") {
        $status['mode'] = "turbo";
      }
    }
    $spunDown = ( $status ) ? $status['spundown'] : "<font color=red>Script Not Running</font>";
    $o = "<script>";
    $o .= "  $('#spunDown').html('$spunDown');";
    $msg = ($status['mode'] == "turbo") ? "Turbo (Reconstruct Write)" : "Normal (Read/Modify/Write)";
    $o .= "  $('#turboOn').html('$msg');";
    if ( is_file($turboPaths['backgroundPID']) ) {
      $o .= "  $('#running').html('<font color=green>Running</font>');";
    } else {
      $o .= "  $('#running').html('<font color=red>Not Running</font>');";
    }
    $o .= "</script>";
    echo $o;
    break;
    
}
      

?>