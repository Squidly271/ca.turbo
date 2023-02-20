<?PHP
###########################################################
#                                                         #
# CA Auto Turbo Mode copyright 2017-2023, Andrew Zawadzki #
#                                                         #
###########################################################

function getSettings($rawSettings) {
  foreach ($rawSettings as $set) {
    $settings[$set[0]] = $set[1];
  }
  return $settings;
}

function getPost($setting,$default) {
  return isset($_POST[$setting]) ? urldecode(($_POST[$setting])) : $default;
}

function getPostArray($setting) {
  return $_POST[$setting];
}

function create_ini_file($settings,$mode=false) {
  if ( $mode ) {
    $keys = array_keys($settings);

    foreach ($keys as $key) {
      $iniFile .= "[$key]\r\n";
      $entryKeys = array_keys($settings[$key]);
      foreach ($entryKeys as $entry) {
        $iniFile .= $entry.'="'.$settings[$key][$entry].'"'."\r\n";
      }
    }
  } else {
    $entryKeys = array_keys($settings);
    foreach ($entryKeys as $entry) {
      $iniFile .= $entry.'="'.$settings[$entry].'"'."\r\n";
    }
  }
  return $iniFile;
}

function getPluginSettings() {
  $settings = @parse_ini_file("/usr/local/emhttp/plugins/ca.turbo/default.ini");
  $userSettings = @parse_ini_file("/boot/config/plugins/ca.turbo/settings.ini");
  if ( !$userSettings ) {
    $userSettings = array();
  }
  $userKeys = array_keys($userSettings);
  foreach ($userKeys as $key) {
    $settings[$key] = $userSettings[$key];
  }
  return $settings;
}

function logger($string) {
  global $debug;
  
  exec("logger ".escapeshellarg($string));
}

##################################################################
#                                                                #
# 2 Functions to avoid typing the same lines over and over again #
#                                                                #
##################################################################

function readJsonFile($filename) {
  return json_decode(@file_get_contents($filename),true);
}

function writeJsonFile($filename,$jsonArray) {
  file_put_contents($filename,json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

# Functions to set normal / turbo mode

function setTurbo() {
  exec("/usr/local/sbin/mdcmd set md_write_method 1");
}
function setNormal() {
  exec("/usr/local/sbin/mdcmd set md_write_method 0");
}

# creates the HTML for a schedule

function createSchedule($index,$schedule) {
  if ( $schedule[3] < 1 ) {
    $schedule[3] = 60;
  }
  $o = "
    <strong>Schedule Number: $index</font>  <i class='fa fa-window-close' width='20px' onclick=deleteSchedule('$index'); style='cursor:pointer;color:#880000' title='Delete This Schedule'></i></strong>
    <dl>
    <dt>Schedule Enabled:</dt>
    <dd><select class='schedule$index schedules' id='enabled$index'>
        <option value='yes'>Yes</option>
        <option value='no' selected>No</option>
        </select></dd>
    <dt>Enable or Disable Turbo Write:</dt>
    <dd><select class='schedule$index schedules' id='turbo$index'>
        <option value='enable'>Enable</option>
        <option value='disable'>Disable</option>
        </select></dd>
    <dt>Cron time to run:</dt>
    <dd><input type='text' class='narrow schedule$index schedules' placeholder='Cron Expression' id='cron$index'></dd>
    <dt>Duration to enable / disable (minutes):</dt>
    <dd><input type='number' class='narrow schedule$index schedules' placeholder='Duration in minutes' id='duration$index'></dd>
    </dl>
    <script>
      $('#enabled$index').val('{$schedule[0]}');
      $('#turbo$index').val('{$schedule[1]}');
      $('#cron$index').val('{$schedule[2]}');
      $('#duration$index').val('{$schedule[3]}');
    </script>
  ";
  return $o;
}
?>