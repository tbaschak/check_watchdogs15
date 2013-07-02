check_watchdogs15
=================
This is a simple php based check script for Nagios (or probably Icinga or Shrinken too) to check and return the current temperature, humidity, and dewpoint from the IT Watchdogs 15/15P.

Product Info
----------
http://www.itwatchdogs.com/product-detail-watchdog_15-71.html
and
http://www.itwatchdogs.com/product-detail-watchdog_15poe-72.html

To Use
----------
`php check_watchdog15.php -H 192.168.x.x  -C public -s c -p t`

TEMP OK - 22.8?C | 22.8

Compatibility
----------
This script is based on the check_goose script provided by OnLight, but as the WatchDog15 and WeatherGoose have different OID structure they are not compatible between each other.


