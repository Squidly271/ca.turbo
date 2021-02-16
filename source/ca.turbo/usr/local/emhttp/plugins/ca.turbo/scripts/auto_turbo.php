#!/usr/bin/php
<?PHP
###################################
#                                 #
# CA Automatic Turbo Mode         #
# Copyright 2021, Andrew Zawadzki #
#                                 #
###################################

require_once("/usr/local/emhttp/plugins/ca.turbo/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ca.turbo/include/paths.php");

##
# Try 10 times with pause in case a concurrent write to the file messes it up
##

function getUnraidDisks() {
  global $unRaidDisks;
  
  for ($i = 0; $i < 10; $i++) {
    $unRaidDisks = parse_ini_file("/var/local/emhttp/disks.ini",true);
    if ( $unRaidDisks ) {
      break;
    }
    sleep(5);
  }
}

# Function to get the valid disks

function getDisks() {
  global $unRaidDisks;
  
  $validDisks = array();
  foreach ($unRaidDisks as $disk) {
    $valid = (strpos($disk['name'],"disk") === 0) && ($disk['size']);
    if ( ! $valid ) { continue; }
    $newDisk['name'] = $disk['name'];
    $newDisk['device'] = $disk['device'];
    $validDisks[] = $newDisk;
  }  
  return $validDisks;
}

################
################
##            ##     
## BEGIN MAIN ##
##            ##
################
################

$settings = getPluginSettings();
$debug = ($settings['debug'] == "true");

if ($settings['enabled'] != "yes") return;
if ( is_file($turboPaths['backgroundPID']) ) {
  logger("Auto Turbo Mode Background process is already running!");
  if ($debug) {
    logger("Auto Turbo Mode Background process is already running (PID: ".file_get_contents($turboPaths['backgroundPID']));
  }
  return;
}

$dirContents = array_diff(@scandir("/tmp/ca.turbo/otherPIDs"),array(".",".."));
if ( ! empty($dirContents) ) {
  logger("CA Auto Turbo Schedules currently in process");
  return;
}

exec("mkdir -p /tmp/ca.turbo/");
$MyPID = getmypid();
file_put_contents($turboPaths['backgroundPID'],"$MyPID");

while (true) {
  getUnraidDisks();
  $validDisks = getDisks();
  $totalSpunDown = 0;
  foreach ($validDisks as $disk) {
    if ( file_exists("/usr/local/sbin/sdspin") ) {
      exec("/usr/local/sbin/sdspin /dev/{$disk['device']}",$out,$ret);
      if ( $ret == 2 ) {
        $totalSpunDown++;
      }
    } else {
      $result = shell_exec("hdparm -C /dev/{$disk['device']}");
      if ( $debug ) {
        logger($result);
      }
      if ( ! strpos($result,"active") ) {
        $totalSpunDown++;
      }
    }
  }
  if ( $debug ) {
    logger("Total Spundown: $totalSpunDown");
  }
  if ($totalSpunDown > $settings['maxSpunDown'] ) {
    $currentMode = $currentMode ? $currentMode : "turbo";
    if ( $currentMode == "turbo" ) {
      if ( $debug ) {
        logger("Entering Normal Mode");
      }
      setNormal();
      $currentMode = "normal";
    }
  } else {
    $currentMode = $currentMode ? $currentMode : "normal";
    if ( $currentMode == "normal" ) {
      if ( $debug ) {
        logger("Entering Turbo Mode");
      }
      setTurbo();
      $currentMode = "turbo";
    }
  }
  $status['spundown'] = $totalSpunDown;
  $status['mode'] = $currentMode;
  writeJsonFile($turboPaths['status'],$status); 
  sleep($settings['pollingTime']);
  # if PID file no longer exists (or is a different PID), stop the process
  $testPID = @file_get_contents($turboPaths['backgroundPID']);
  if ( $testPID != $MyPID ) {
    break;
  }
}
# reset write mode to unRaid's setting
$unRaidVars = parse_ini_file("/var/local/emhttp/var.ini");
exec("/usr/local/sbin/mdcmd set md_write_method ".$unRaidVars['md_write_method']);
@unlink($turboPaths['backgroundPID']);
@unlink($turboPaths['status']);

?>