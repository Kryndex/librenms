<?php
/**
 * rename-mibs.php
 *
 * Rename mib files to the proper file name to match the mib name
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

array_shift($argv); // remove script name

if (empty($argv)) {
    echo "You must specify one or more files or folders containing files to rename.\n";
}

foreach ($argv as $item) {
    if (is_dir($item)) {
        foreach (scandir($item) as $file) {
            rename_mib_file($file);
        }
    } else {
        rename_mib_file($item);
    }
}


function rename_mib_file($file)
{
    if (!is_file($file)) {
        echo "Not a file: $file\n";
        return;
    }

    $mib_name = extract_mib_name($file);
    $filename = basename($file);
    if ($mib_name != $filename) {
        $new_file = dirname($file) . '/' . $mib_name;
        echo "$file -> $new_file\n";
        rename($file, $new_file);
    }
}

function extract_mib_name($file)
{
    // extract the mib name (tried regex, but was too complex and I had to read the whole file)
    if ($handle = fopen($file, "r")) {
        $header = '';
        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            if (empty($trimmed) || substr($trimmed, 0, 2) == '--') {
                continue;
            }

            $header .= " $trimmed";
            if (strpos($trimmed, 'DEFINITIONS') !== false) {
                preg_match('/(\S+)\s+(?=DEFINITIONS)/', $header, $matches);
                fclose($handle);
                return $matches[1];
            }
        }
        fclose($handle);
    }

    throw new Exception("Could not extract mib name from file ($file)");
}
