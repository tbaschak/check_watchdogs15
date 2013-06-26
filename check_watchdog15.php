#!/usr/bin/php
<?php
/**
 * check_watchdog15.php
 * 
 * Nagios plugin to check status of WatchDog 15/15P server over SNMP
 * See http://www.itwatchdogs.com/product-detail-watchdog_15-71.html
 * 
 * See show_usage(), below, for usage information.
 * 
 * Note: This puglin has minimal support for additional sensor units.
 * If your device has more that one sensor for the standard temperature,
 * humidity, etc. specify a unit number on the command line using the
 * ``-u <unit#>'' option.  This has not been tested and may not use
 * the proper OID.
 * 
 * Note: We have no additional sensor probes, so have not provided any
 * support for those.  If we get our hands on any, we'll add support
 * at that time.  If you have other probes, the OIDs are quite obvious.
 * You can download the MIB from the GUI of the device, and find a CSV
 * file therein which provides the information needed to extend this
 * plugin.
 * 
 * To extend the plugin, you'll need to do the following:
 *  * Add more OIDs to the `define' commands, below.
 *  * Add a new option to the shortopts string and associated processing
 *    to the switch statement which follows.
 *  * Each new case in the switch statement must set $label, $oid and 
 *    $units properly.
 *  * Please add appropriate usage information to the show_usage() function
 * 
 * Please forward any such changes to us for inclusion in future releases.
 * mailto:<support@onlight.com>
 */

/**
 * Copyright (c) November  4, 2010
 * by Nic Bernstein <nic@onlight.com>
 * for Onlight, llc.
 * 219 N. Milwaukee St.
 * Suite 2A
 * Milwaukee, WI  53202
 *
 * All rights reserved, except as provided by the following license
 * information...
 *
 * License Information:
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 */

/**
 * Output of an snmpwalk
 * IT-WATCHDOGS-MIB-V3::climateTempC.1 = INTEGER: 31 Degrees Celsius
 * IT-WATCHDOGS-MIB-V3::climateTempF.1 = INTEGER: 87 Degress Fahrenheit
 * IT-WATCHDOGS-MIB-V3::climateHumidity.1 = INTEGER: 18 %
 * IT-WATCHDOGS-MIB-V3::climateLight.1 = INTEGER: 48
 * IT-WATCHDOGS-MIB-V3::climateAirflow.1 = INTEGER: 1
 * IT-WATCHDOGS-MIB-V3::climateSound.1 = INTEGER: 0
 * IT-WATCHDOGS-MIB-V3::climateIO1.1 = INTEGER: 99
 * IT-WATCHDOGS-MIB-V3::climateIO2.1 = INTEGER: 99
 * IT-WATCHDOGS-MIB-V3::climateIO3.1 = INTEGER: 99
 * IT-WATCHDOGS-MIB-V3::climateDewPointC.1 = INTEGER: 3 Degrees Celsius
 * IT-WATCHDOGS-MIB-V3::climateDewPointF.1 = INTEGER: 39 Degress Fahrenheit
 */

/**
 * show_usage()
 * 
 * Print standard usage instructions and exit
 */
function show_usage() {
    global $argv;
    
    echo <<<_EOF_
  Usage: $argv[0] -H <host> -C <community string> -s <scale> 
            -p <probe> -c <crit_range> -w <warn_range> -h -t <timeout> 
            -u <unit> -d

  Required:
    -H [STRING|IP]
        Hostname or IP address
    -p Upper or lower case T,H,L,A,S or D
        Which probe to check: Temperature, Humidity, Light level, 
                              Airflow, Sound level or Dewpoint

  Actions:
    -h show this help
    -v show version

  Options:
    -C  STRING
        SNMP community string (defaults to `public').
    -s  Upper or lower case C or F
        Temperature scale, Celcius or Fahrenheit, for temperature or
        dewpoint readings (*required* for those readings)
    -c <crit-range>
        Range which will not result in a CRITICAL status.
    -w <warn-range>
        Range which will not result in a WARNING status
        - Ranges are inclusive and are indicated with colons. When specified as
          'min:max' a STATE_OK will be returned if the result is within the indicated
          range or is equal to the upper or lower bound. A non-OK state will be
          returned if the result is outside the specified range.
        - If specified in the order 'max:min' a non-OK state will be returned if the
          result is within the (inclusive) range.
    -t  Timeout (in seconds, default 10)
    -u  Unit number, starting from 1 (default 1)
    -d  Log debug output to error log
      
  Examples:
    Report temperature in degrees Fahrenheit, return Critical if below 50 or above 90
    and warning if below 60 or above 80:
        $argv[0] -H 192.168.1.2 -C public -s f -p t -c 50:90 -w 60:80
        TEMP WARNING - 85°F is above 80 | 85
    
    Report humidity:
        $argv[0] -H 192.168.1.2 -C public -s f -p h
        HUMIDITY OK - 17% | 17

  Return status:
    exit code 0: OK
    exit code 1: WARNING
    exit code 2: CRITICAL
    exit code 3: UNKNOWN, OTHER
 
_EOF_;
    echo "\n";
}

/**
 * debug_msg()
 * 
 * write a message the error log
 *
 * @param string $msg    message to write to error log
 */
function debug_msg($msg) {
    global $debug;
    
    if ($debug) {
        error_log($msg, 0);
    }
}

/**
 * my_echo
 * 
 * write message to standard out, and if debugging is enabled, to error log
 *
 * @param string $msg    message to write
 */
function my_echo($msg) {
    echo "$msg\n";
    
    debug_msg($msg);
}

/**
 * my_exit()
 * 
 * Exit program with exit status and optional message
 *
 * @param integer $exit_status Exit status
 * @param string $msg message to write on exit (default empty)
 */
function my_exit($exit_status, $msg='') {
    global $debug;
    
    if (!empty($msg)) {
        echo "$msg\n";
        debug_msg("$msg - exit with status code $exit_status");
    }    
    exit($exit_status);
}

/**
 * my_unknown()
 * 
 * Print message and exit with status 3 (UNKNOWN)
 *
 * @param string $msg message to write on exit (default empty)
 */
function my_unknown($msg='') {
    global $label;
    
    my_exit(3, "$label UNKNOWN - $msg");
}

/******** END OF FUNCTIONS ********/

/******** DECLARE CONSTANTS AND DEFAULTS ********/
define('THIS_VERSION','1.0');
error_reporting(E_ALL);

/**
 * OIDs
 * 
 * Note: an integer specifying unit number must be added to end of value
 *       prior to use
 * 
 * Note: Add more OID's here to extend plugin
 * 
 * Note: We use `SNMPv2-SMI::enterprises.17373' rather than
 *       `IT-WATCHDOGS-MIB-V3::' in case the MIB is not installed.
 */
define('OID_TEMP_UNITS','1.3.6.1.4.1.17373.4.1.1.7.');
define('OID_CLIMATE_TEMP','1.3.6.1.4.1.17373.4.1.2.1.5.');
define('OID_CLIMATE_HUMIDITY','1.3.6.1.4.1.17373.4.1.2.1.6.');
define('OID_CLIMATE_IO1','SNMPv2-SMI::enterprises.17373.3.2.1.11.');
define('OID_CLIMATE_IO2','SNMPv2-SMI::enterprises.17373.3.2.1.12.');
define('OID_CLIMATE_IO3','SNMPv2-SMI::enterprises.17373.3.2.1.13.');
define('OID_CLIMATE_DEWPOINT','1.3.6.1.4.1.17373.4.1.2.1.7.');

// Set some defaults
// Timeout for SNMP calls
$timeout = 10;

// Whether to log debugging info
$debug = FALSE;

// Which unit number of probe are we to read
$unit = 1;

// The SNMP community string
$comm = 'public';

// Report version?
$ver = FALSE;



// Process our command line options
$shortopts = 'H:s:p:C:c:w:hdt:u:v';
$opts = getopt($shortopts);

foreach ($opts as $opt => $value) {
    $val = strtolower($value);
    switch ($opt) {
    case 'H':
        // hostname or IP address
        $host = $value;
        break;

    case 'h':
        // Help
        show_usage();
        my_exit(0);
        break;

    case 's':
        // Scale - this will be processed, if needed, below
        break;

    case 'p':
        // Probe
        switch ($val) {
        case 't':
            // Temperature
            $label = 'TEMP';
            if (!isset($opts['s'])) {
                show_usage();
                my_exit(3,"Invalid value for Scale, either F or C required!");
            }
            switch (strtolower($opts['s'])) {
            case 'c':
                // Celsius
                $oid = OID_CLIMATE_TEMP;
                $units = '°C';
                break;

            case 'f': 
                // Fahrenheit
                $oid = OID_CLIMATE_TEMPF;
                $units = '°F';
                break;
            }
            break;

        case 'h':
            // Humidity
            $label = 'HUMIDITY';
            $oid = OID_CLIMATE_HUMIDITY;
            $units = '%';
            break;

        case 'd':
            // Dewpoint
            $label = 'DEWPOINT';
            switch (strtolower($opts['s'])) {
            case 'c':
                // Celsius
                $oid = OID_CLIMATE_DEWPOINT;
                $units = '°C';
                break;

            case 'f': 
                // Fahrenheit
                $oid = OID_CLIMATE_DEWPOINTF;
                $units = '°F';
                break;

            default:
                show_usage();
                my_exit(3,"Invalid value for Scale, either F or C required!");
                break;
            }
            break;

        default:
            show_usage();
            my_exit(3, "Invalid value for Probe, either T,H,L,A,S or D required!");
            break;
        }
        break;

    case 'C':
        // SNMP community string
        $comm = $value;
        break;

    case 'c':
        if (strpos($val, ':') === FALSE) {
            my_echo("Invalid value for critical; missing colon");
            show_usage();
            my_exit();
        } else {
            list($cmin, $cmax) = split(':',$val);
            $cexcl = ($cmin > $cmax) ? TRUE : FALSE;
        }
        break;

    case 'w':
        if (strpos($val, ':') === FALSE) {
            my_echo("Invalid value for warning; missing colon");
            show_usage();
            my_exit();
        } else {
            list($wmin, $wmax) = split(':',$val);
            $wexcl = ($wmin > $wmax) ? TRUE : FALSE;
        }
        break;
        
    case 'd':
        $debug = TRUE;
        break;
        
    case 'v':
        $msg = "Version: " . THIS_VERSION;
        $ver = TRUE;
        break;
        
    case 't':
        $timeout = $value;
        break;

    case 'u':
        $unit = $value;
        break;
    }
}

// Simply report the version and exit
if ($ver) {
    my_exit(0, $msg);
}

// Make sure we have the barest minimum of settings
if ($argc == 0) {
    show_usage();
    my_exit(3, "No arguments specified");
}

if (!isset($host) || empty($host)) {
    show_usage();
    my_exit(3, "Hostname must be specified");
}

if (!isset($oid) || empty($oid)) {
    show_usage();
    my_exit(3, "Probe must be specified");
}

// Get the status from the probe
// The snmpget functions in PHP 5.2 do not seem to work properly, so
// we just use the net-snmp commands directly.
$cmd = escapeshellcmd("snmpget -Oqv -v2c -c $comm -t $timeout $host $oid$unit");
$result = trim(exec($cmd, $out, $return))/10;

// Bail if we don't get a proper exit code
if ($return != 0) {
	my_unknown("Error connecting to probe $host");
}

// Format our message with the appropriate units
$msg = $result.$units;

// Process the critical criteria
if (isset($cmin) || isset($cmax)) {
    // Is the range inclusive or exclusive
    if ($cexcl) {
        if ($result >= $cmin) {
            my_exit(2, "$label CRITICAL - $msg is above or equal to $cmin | $result");
        } elseif ($result <= $cmax) {
            my_exit(2, "$label CRITICAL - $msg is below or equal to $cmax | $result");
        }
    } else {
        if ($result < $cmin) {
            my_exit(2, "$label CRITICAL - $msg is below $cmin | $result");
        } elseif ($result > $cmax) {
            my_exit(2, "$label CRITICAL - $msg is above $cmax | $result");
        }
    }
}

// Process the warning criteria
if (isset($wmin) || isset($wmax)) {
    // Is the range inclusive or exclusive
    if ($wexcl) {
        if ($result >= $wmin) {
            my_exit(1, "$label WARNING - $msg is above or equal to $wmin | $result");
        } elseif ($result <= $wmax) {
            my_exit(1, "$label WARNING - $msg is below or equal to $wmax | $result");
        }
    } else {
        if ($result < $wmin) {
            my_exit(1, "$label WARNING - $msg is below $wmin | $result");
        } elseif ($result > $wmax) {
            my_exit(1, "$label WARNING - $msg is above $wmax | $result");
        }
    }
}

// We're okay
my_exit(0, "$label OK - $msg | $result");
?>
<?php //vim:set ts=4 sw=4 ai: ?>
