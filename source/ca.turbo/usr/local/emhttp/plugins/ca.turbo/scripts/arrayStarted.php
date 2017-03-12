#!/usr/bin/php
<?PHP
require_once("/usr/local/emhttp/plugins/ca.turbo/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ca.turbo/include/paths.php");

$settings = getPluginSettings();
if ($settings['enabled'] == "yes") {
  exec("/usr/local/emhttp/plugins/ca.turbo/scripts/startBackground.sh");
}
?>