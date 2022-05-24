<?php

namespace chsxf\GitRepoBackup;

class Process
{
    public static function exec(string $cmd, string &$output = null, string &$error = null, string $workingDirectory = null): int
    {
        $descriptors = [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w']
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $workingDirectory);

        $output = trim(stream_get_contents($pipes[1]));
        $error = trim(stream_get_contents($pipes[2]));

        for ($i = 0; $i < count($descriptors); $i++) {
            fclose($pipes[$i]);
        }

        $status = proc_get_status($process);
        $exitCode = $status['exitcode'];
        proc_close($process);
        return $exitCode;
    }
}
