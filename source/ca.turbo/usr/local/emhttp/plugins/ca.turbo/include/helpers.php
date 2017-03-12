<?PHP

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
  exec("logger ".escapeshellarg($string));
  echo "$string\n";
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
?>