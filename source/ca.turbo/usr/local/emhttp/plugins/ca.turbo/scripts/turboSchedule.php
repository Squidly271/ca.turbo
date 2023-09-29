#!/usr/bin/php
<?PHP
require_once("/usr/local/emhttp/plugins/ca.turbo/include/helpers.php");

$enableTurboMode = $argv[1];
$duration = $argv[2];

exec("mkdir -p /tmp/ca.turbo/otherPIDs");                                    

logger("Executing {$argv[0]} {$argv[1]} {$argv[2]}");
$dirContents = array_diff(@scandir("/tmp/ca.turbo/otherPIDs/"),array(".",".."));
if ( ! empty($dirContents) ) {
  logger("Warning: Overlapping schedules of CA Auto Turbo Mode Detected");
}
  
$myPID = getmypid();
file_put_contents("/tmp/ca.turbo/otherPIDs/$myPID",$myPID);
                                    
if ( is_file("/tmp/ca.turbo/PID") ) {
  logger("CA Turbo Mode Schedule Starting.  Killing Automatic Mode");
  $scriptPID = file_get_contents("/tmp/ca.turbo/PID");
  posix_kill($scriptPID,SIGKILL);    # kill the running auto script
  unlink("/tmp/ca.turbo/PID");
  $runningFlag = true;
}

$mode = ($enableTurboMode == "enable") ? "1" : "0";
$status['spundown'] = "Unknown";
$status['mode'] = ($enableTurboMode == "enable") ? "turbo" : "normal";
$status['override'] = true;

file_put_contents("/tmp/ca.turbo/status.json",json_encode($status, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

exec("/usr/local/sbin/mdcmd set md_write_method $mode");

sleep($duration * 60);
unlink("/tmp/ca.turbo/otherPIDs/$myPID");

# Check if any other overlapping schedules happen to still be running.
# Don't restart ca auto turbo if a schedule is still active
# If no other active schedules, either restart CA Auto Turbo or restore unRaid's settings

$dirContents = array_diff(@scandir("/tmp/ca.turbo/otherPIDs/"),array(".",".."));
print_r($dirContents);
if (empty($dirContents) ) {
  unlink("/tmp/ca.turbo/status.json");
  $settings = getPluginSettings();
  if ( $settings['enabled'] == "yes" ) {
    logger("Restarting CA Auto Turbo Mode");
    $descriptorspec = array(
      0 => array("pipe", "r"),
      1 => array("pipe", "w"),
      2 => array("file", "/tmp/error-output.txt", "a")
    );
    proc_open("/usr/local/emhttp/plugins/ca.turbo/scripts/auto_turbo.php",$descriptorspec,$pipes);
  } else {
    $unRaidSettings = parse_ini_file("/boot/config/disk.cfg");
    logger("Restoring unRaid write settings");
    exec("/usr/local/sbin/mdcmd set md_write_method {$unRaidSettings['md_write_method']}");
  }
} else {
  logger("Warning: Overlapping schedules of CA Auto Turbo Mode Detected");
}
?>