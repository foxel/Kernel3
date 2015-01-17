<?php
/**
 * Copyright (C) 2015 Andrey F. Kupreychik (Foxel)
 *
 * This file is part of QuickFox Kernel 3.
 * See https://github.com/foxel/Kernel3/ for more details.
 *
 * Kernel 3 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Kernel 3 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Kernel 3. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Class K3_Util_File
 */
class K3_Util_Exec extends K3_Util
{
    /**
     * @param string $command
     * @param string $input
     * @param string $cwd
     * @param array $envVars
     * @return array [returnValue, output, errors]
     * @throws FException
     */
    public static function exec($command, $input = '', $cwd = null, array $envVars = null)
    {
        $pipesSpec = array(
            0 => array("pipe", "r"), // stdin is a pipe that the child will read from
            1 => array("pipe", "w"), // stdout is a pipe that the child will write to
            2 => array("pipe", "w"), // stderr is a pipe that the child will write to
        );

        $process = proc_open($command, $pipesSpec, $pipes, $cwd, $envVars);

        if (!is_resource($process)) {
            throw new FException('Can\'t run external program');
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnValue = proc_close($process);

        return array($returnValue, $output, $errors);
    }
}
