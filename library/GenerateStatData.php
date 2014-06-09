<?php

// GenerateStatData.php



/*
  Graph #1
  proc/s - Tasks created per second

  Graph #2
  cswch/s - Context switches per second

  Graph #3
  %usr - Percentage of CPU utilisation that occurred while executing at the user level
  %sys - Percentage of CPU utilisation that occurred while executing at the system level
  %iowait - Percentage of time that the CPU or CPUs were idle during which the system had an outstanding disk I/O request

  Graph #4
  kbmemused - Amount of used memory in kilobytes (processor converts to megabytes)
  kbswpused - Amount of used swap space in kilobytes (processor converts to megabytes)

  Graph #5
  runq-sz - Run queue length (number of tasks waiting for run time)
  plist-sz - Number of tasks in the process list

  Graph #6
  ldavg-1 - System load average for the last minute
  ldavg-5 - System load average for the past 5 minutes
  ldavg-15 - System load average for the past 15 minutes

  Graph #n (network)
  rxpck/s - Total number of packets received per second
  txpck/s - Total number of packets transmitted per second

  Graph #n+1 (network)
  rxkB/s - Total number of kilobytes received per second
  txkB/s - Total number of kilobytes transmitted per second
 */



class GenerateStatData {

    public function execute($days_ago = -1) {
        if ($days_ago > 0 && $days_ago <= 30) {
            $time = time();
            $days = array();
            for ($i = 1; $i <= $days_ago; $i ++) {
                $days[] = date('d', $time - 86400 * $i);
            }
            $mask = '{' . implode(',', $days) . '}';
        } else {
            $mask = '??';
        }

        // remove any trailing slashes from sysstat data path
        $sysstatdatapath = rtrim(SYSSTATDATAPATH, '\//');

        // get listing of sar data files on disc, if no files found then no work to do
        if (!($sardatafilelist = $this->getSarDataFileList($sysstatdatapath, $mask))) {
            // no sar data files found
            return;
        }

        if ($this->getJsonReportTimestamp() > $this->getSarDataLatestTimestamp($sardatafilelist)) {
            // JSON report file is newer than latest sar data file, no work to do
            return;
        }

        // process sar data files and build new JSON report data file
        $import_stat_file_data = new ImportStatFileData();

        foreach ($sardatafilelist as $file) {
            // import each sar file from disc
            if (!is_file($file))
                continue;
            $import_stat_file_data->importFile($file);
        }

        // generate JSON block and write to disc
        $build_json_structure = new BuildJsonStructure(
                $import_stat_file_data->getValidNetworkInterfaceList(), $import_stat_file_data->getTimePointList(), $import_stat_file_data->getStatTypeList()
        );

        $fp = fopen(JSONSTRUCTUREFILENAME, 'w');
        if ($fp === FALSE) {
            die('Error: Unable to create ' . realpath('.') . '/' . JSONSTRUCTUREFILENAME . ' - possible file system permissions issue.');
        }

        fwrite($fp, $build_json_structure->render());
        fclose($fp);
    }

    private function getSarDataFileList($inputdatapath, $mask = '??') {

        // sysstat data path must exist
        if (!is_dir($inputdatapath))
            return array();

        // fetch all files in data folder
        return glob($inputdatapath . '/sar' . $mask, GLOB_BRACE);
    }

    private function getSarDataLatestTimestamp(array $inputfilelist) {

        $timestamp = 0;
        foreach ($inputfilelist as $file) {
            $filetimestamp = (is_file($file)) ? filemtime($file) : 0;
            $timestamp = ($filetimestamp > $timestamp) ? $filetimestamp : $timestamp;
        }

        // return the timestamp of the latest sar data file found
        return $timestamp;
    }

    private function getJsonReportTimestamp() {

        if (is_file(JSONSTRUCTUREFILENAME)) {
            // if filesize of JSON structure file is zero (empty file) - then return a zero timestamp to allow new JSON data creation
            if (filesize(JSONSTRUCTUREFILENAME) == 0)
                return 0;

            // return file modified timestamp
            return filemtime(JSONSTRUCTUREFILENAME);
        }

        // no JSON structure file found
        return 0;
    }

}
