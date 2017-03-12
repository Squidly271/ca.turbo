#!/bin/bash
/usr/local/emhttp/plugins/ca.turbo/scripts/auto_turbo.php & > /dev/null | at NOW -M >/dev/null 2>&1

