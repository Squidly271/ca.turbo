#!/usr/bin/php
<?PHP
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


$settings = getPluginSettings();
$debug = ($settings['debug'] == "true");

if ($settings['enabled'] != "yes") return;
if ( is_file($turboPaths['backgroundPID']) ) {
  logger("Auto Turbo Mode Background process is already running!");
#  return;
}

exec("mkdir -p /tmp/ca.turbo/");
file_put_contents($turboPaths['backgroundPID'],getmypid());

while (true) {
  getUnraidDisks();
  $validDisks = getDisks();
  $totalSpunDown = 0;
  foreach ($validDisks as $disk) {
    $result = shell_exec("hdparm -C /dev/{$disk['device']}");
    if ( $debug ) {
      logger($result);
    }
    if ( ! strpos($result,"active") ) {
      $totalSpunDown++;
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
      $currentMode = "normal";
    }
  } else {
    $currentMode = $currentMode ? $currentMode : "normal";
    if ( $currentMode == "normal" ) {
      if ( $debug ) {
        logger("Entering Turbo Mode");
      }
      $currentMode = "turbo";
    }
  }
  $status['spundown'] = $totalSpunDown;
  $status['mode'] = $currentMode;
  writeJsonFile($turboPaths['status'],$status); 
}


?>