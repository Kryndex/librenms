#!/usr/bin/env php
<?php

$filename = basename(__FILE__);
$install_dir = realpath(__DIR__ . '/..');
chdir($install_dir);

require $install_dir . '/vendor/autoload.php';

$short_opts = 'lsufpcho:m:';
$long_opts = array(
    'lint',
    'style',
    'unit',
    'os:',
    'module:',
    'fail-fast',
    'passthru',
    'snmpsim',
    'db',
    'commands',
    'help',
);
$options = getopt($short_opts, $long_opts);

if (check_opt($options, 'h', 'help')) {
    echo "LibreNMS Code Tests Script
Running $filename without options runs all checks.
  -l, --lint      Run php lint checks to test for valid syntax
  -s, --style     Run phpcs check to check for PSR-2 compliance
  -u, --unit      Run phpunit tests
  -o, --os        Specific OS to run tests on. Implies --unit, --db, --snmpsim
  -m, --module    Specific Module to run tests on. Implies --unit, --db, --snmpsim
  -f, --fail-fast Quit when any failure is encountered
  -p, --passthru  Display output from checks as it comes
      --db        Run unit tests that require a database
      --snmpsim   Use snmpsim for unit tests
  -c, --commands  Print commands only, no checks
  -h, --help      Show this help text.\n";
    exit();
}

// set up some variables
$passthru = check_opt($options, 'p', 'passthru');
$command_only = check_opt($options, 'c', 'commands');
$fail_fast = check_opt($options, 'f', 'fail-fast');
$return = 0;
$completed_tests = array(
    'lint' => false,
    'style' => false,
    'unit' => false,
);

if ($os = check_opt($options, 'os', 'o')) {
    // enable unit tests, snmpsim, and db
    $options['u'] = false;
    $options['snmpsim'] = false;
    $options['db'] = false;
}

if ($module = check_opt($options, 'm', 'module')) {
    putenv("TEST_MODULES=$module");
    // enable unit tests, snmpsim, and db
    $options['u'] = false;
    $options['snmpsim'] = false;
    $options['db'] = false;
}

$all = !check_opt($options, 'l', 'lint', 's', 'style', 'u', 'unit');
if ($all) {
    // no test specified, run all tests in this order
    $options += array('u' => false, 's' => false, 'l' => false);
}

if (check_opt($options, 'snmpsim')) {
    putenv('SNMPSIM=1');
}

if (check_opt($options, 'db')) {
    putenv('DBTEST=1');
}

// run tests in the order they were specified
foreach (array_keys($options) as $opt) {
    $ret = 0;
    if ($opt == 'l' || $opt == 'lint') {
        $ret = run_check('lint', $passthru, $command_only);
    } elseif ($opt == 's' || $opt == 'style') {
        $ret = run_check('style', $passthru, $command_only);
    } elseif ($opt == 'u' || $opt == 'unit') {
        $ret = run_check('unit', $passthru, $command_only, compact('fail_fast', 'os', 'module'));
    }

    if ($fail_fast && $ret !== 0 && $ret !== 250) {
        exit($ret);
    } else {
        $return += $ret;
    }
}

// output Tests ok, if no arguments passed
if ($all && $return === 0) {
    echo "\033[32mTests ok, submit away :)\033[0m \n";
}
exit($return); //return the combined/single return value of tests


/**
 * Run the specified check and return the return value.
 * Make sure it isn't skipped by SKIP_TYPE_CHECK env variable and hasn't been run already
 *
 * @param string $type type of check lint, style, or unit
 * @param bool $passthru display the output as comes in
 * @param bool $command_only only display the intended command, no checks
 * @param array $options command specific options
 * @return int the return value from the check (0 = success)
 */
function run_check($type, $passthru, $command_only, $options = array())
{
    global $completed_tests;
    if (getenv('SKIP_' . strtoupper($type) . '_CHECK') || $completed_tests[$type]) {
        echo ucfirst($type) . ' check skipped.';
        return 0;
    }

    $function = 'check_' . $type;
    if (function_exists($function)) {
        $completed_tests[$type] = true;
        return $function($passthru, $command_only, $options);
    }

    return 1;
}

/**
 * Runs php -l and tests for any syntax errors
 *
 * @param bool $passthru display the output as comes in
 * @param bool $command_only only display the intended command, no checks
 * @return int the return value from running php -l (0 = success)
 */
function check_lint($passthru = false, $command_only = false)
{
    $parallel_lint_bin = check_exec('parallel-lint');

    // matches a substring of the relative path, leading / is treated as absolute path
    $lint_excludes = array('vendor/');
    if (defined('HHVM_VERSION') || version_compare(PHP_VERSION, '5.6', '<')) {
        $lint_excludes[] = 'lib/influxdb-php/';
    }

    $lint_exclude = build_excludes('--exclude ', $lint_excludes);
    $lint_cmd = "$parallel_lint_bin $lint_exclude ./";

    if ($command_only) {
        echo $lint_cmd . PHP_EOL;
        return 250;
    }

    echo 'Running lint check... ';

    if ($passthru) {
        echo PHP_EOL;
        passthru($lint_cmd, $lint_ret);
    } else {
        exec($lint_cmd, $lint_output, $lint_ret);

        if ($lint_ret > 0) {
            print(implode(PHP_EOL, $lint_output) . PHP_EOL);
        } else {
            echo "success\n";
        }
    }

    return $lint_ret;
}

/**
 * Runs phpcs --standard=PSR2 against the code base
 *
 * @param bool $passthru display the output as comes in
 * @param bool $command_only only display the intended command, no checks
 * @return int the return value from phpcs (0 = success)
 */
function check_style($passthru = false, $command_only = false)
{
    $phpcs_bin = check_exec('phpcs');

    // matches a substring of the full path
    $cs_excludes = array(
        '/vendor/',
        '/lib/',
        '/html/plugins/',
        '/config.php',
    );

    $cs_exclude = build_excludes('--ignore=', $cs_excludes);
    $cs_cmd = "$phpcs_bin -n -p --colors --extensions=php --standard=PSR2 $cs_exclude ./";

    if ($command_only) {
        echo $cs_cmd . PHP_EOL;
        return 250;
    }

    echo 'Running style check... ';

    if ($passthru) {
        echo PHP_EOL;
        passthru($cs_cmd, $cs_ret);
    } else {
        exec($cs_cmd, $cs_output, $cs_ret);

        if ($cs_ret > 0) {
            echo "failed\n";
            print(implode(PHP_EOL, $cs_output) . PHP_EOL);
        } else {
            echo "success\n";
        }
    }

    return $cs_ret;
}

/**
 * Runs phpunit
 *
 * @param bool $passthru display the output as comes in
 * @param bool $command_only only display the intended command, no checks
 * @param array $options Supported: fail_fast, os, module
 * @return int the return value from phpunit (0 = success)
 */
function check_unit($passthru = false, $command_only = false, $options = array())
{
    $phpunit_bin = check_exec('phpunit');

    $phpunit_cmd = "$phpunit_bin --colors=always";

    if ($options['fail_fast']) {
        $phpunit_cmd .= ' --stop-on-error --stop-on-failure';
    }

    if ($options['os']) {
        $phpunit_cmd .= " --group os --filter \"@{$options['os']}.*\"";
    }

    if ($options['module']) {
        $phpunit_cmd .= ' tests/OSModulesTest.php';
    }

    if ($command_only) {
        echo $phpunit_cmd . PHP_EOL;
        return 250;
    }

    echo 'Running unit tests... ';
    if ($passthru) {
        echo PHP_EOL;
        passthru($phpunit_cmd, $phpunit_ret);
    } else {
        exec($phpunit_cmd, $phpunit_output, $phpunit_ret);

        if ($phpunit_ret > 0) {
            echo "failed\n";
            echo implode(PHP_EOL, $phpunit_output) . PHP_EOL;
            echo 'snmpsimd: output at /tmp/snmpsimd.log';
        } else {
            echo "success\n";
        }
    }

    return $phpunit_ret;
}

/**
 *  Check if the given options array contains any of the $opts specified
 *
 * @param array $options the array from getopt()
 * @param string $opts,... options to check for
 * @return bool If one of the specified options is set
 */
function check_opt($options)
{
    $args = func_get_args();
    array_shift($args);

    foreach ($args as $option) {
        if (isset($options[$option])) {
            if ($options[$option] === false) {
                // no data, return that option is enabled
                return true;
            }
            return $options[$option];
        }
    }

    return false;
}

/**
 * Build a list of exclude arguments from an array
 *
 * @param string $exclude_string such as "--exclude"
 * @param array $excludes array of directories to exclude
 * @return string resulting string
 */
function build_excludes($exclude_string, $excludes)
{
    $result = '';
    foreach ($excludes as $exclude) {
        $result .= $exclude_string . $exclude . ' ';
    }

    return $result;
}

/**
 * Find an executable
 *
 * @param string|array $execs executable names to find
 * @return string the path to the executable, or '' if it is not found
 * @throws Exception Could not find the Executable
 */
function find_exec($execs)
{
    foreach ((array)$execs as $exec) {
        // check vendor bin first
        $vendor_bin_dir = './vendor/bin/';
        if (is_executable($vendor_bin_dir . $exec)) {
            return $vendor_bin_dir . $exec;
        }

        // check path
        $path_exec = shell_exec("which $exec 2> /dev/null");
        if (!empty($path_exec)) {
            return trim($path_exec);
        }

        // check the cwd
        if (is_executable('./' . $exec)) {
            return './' . $exec;
        }
    }
    throw new Exception('Executable not found');
}

/**
 * Check for an executable and return the path to it
 * If it does not exist, run composer update.
 * If composer isn't installed, print error and exit.
 *
 * @param string $exec the name of the executable to check
 * @return string path to the executable
 */
function check_exec($exec)
{
    try {
        return find_exec($exec);
    } catch (Exception $e) {
        try {
            $composer_bin = find_exec(array('composer', 'composer.phar'));
            shell_exec("$composer_bin update");
            return find_exec($exec);
        } catch (Exception $ce) {
            echo "\nCould not find $exec. Please install composer.\nYou can find more info at http://docs.librenms.org/Developing/Validating-Code/\n";
            exit(1);
        }
    }
}
