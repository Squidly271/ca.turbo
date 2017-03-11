<?PHP
require_once("/usr/local/emhttp/plugins/ca.turbo/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ca.turbo/include/paths.php");

switch ($_POST['action']) {
  case 'apply':
    $rawSettings = getPostArray('settings');
    $settings = getSettings($rawSettings);
    
    exec("mkdir -p /boot/config/plugins/ca.turbo");
    file_put_contents("/boot/config/plugins/ca.turbo/settings.ini",create_ini_file($settings));
    if ( $settings['enable'] == 'no' ) {
      if ( is_file($turboPaths['backgroundPID']) ) {
        logger("Stopping Auto Turbo");
        $PID = file_get_contents($turboPaths['backgroundPID']);
        posix_kill($PID,SIGKILL);
        @unlink($turboPaths['backgroundPID']);
        @unlink($turboPaths['status']);
      }
    }
    if ( $settings['enable'] == 'yes' ) {
      if ( is_file($turboPaths['backgroundPID']) ) {
        logger("Stopping Auto Turbo");
        $PID = file_get_contents($turboPaths['backgroundPID']);
        posix_kill($PID,SIGKILL);
      }
      logger("Starting Auto Turbo");
      exec("/usr/local/emhttp/plugins/ca.turbo/scripts/startBackground.sh");
    }        
    echo "Settings Updated";
    break;
  case 'status':
    $status = readJsonFile($turboPaths['status']);
    if ( ! $status ) { return; }
    $o = "<script>";
    $o .= "  $('#spunDown').html('{$status['spundown']}');";
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