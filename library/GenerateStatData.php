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

class GenerateStatData
{
    public $host_dict = array();
    public $days_ago = -1;
    public $full_url = '';

    public function __construct(array $host_dict, $days_ago = -1)
    {
        $this->host_dict = $host_dict;
        $this->days_ago = $days_ago;
    }

    public static function isLocal(array $host_dict)
    {
        $host_count = count($host_dict);
        return $host_count === 0
            || $host_count === 1 && key($host_dict) == $_SERVER['SERVER_ADDR'];
    }

    public function requireTodayData($ipaddr, $port=22)
    {
        $command = dirname(SYSSTAT_DATA_PATH) . '/bin/sysstat_today.sh';
        shell_exec("$command $ipaddr $port");
        $result = glob(SYSSTAT_DATA_PATH . "/$ipaddr/sat???");
        return $result;
    }

    public function getFullURL($with_query = true)
    {
        if (empty($this->full_url)) {
            $url = 'http://';
            if (isset($_SERVER['PHP_AUTH_USER'])) {
                $url .= $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'] . '@';
            }
            $url .= $_SERVER['HTTP_HOST'];
            $url .= $with_query ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];
            $url = str_replace('/index.php', '/', $url);
            $this->full_url = rtrim($url, '/') . '/';
        }
        return $this->full_url;
    }

    public function execute($cache_file = false)
    {
        // get listing of sar data files on disc, if no files found then no work to do
        if ($this->days_ago === 0) {
            $data_resource_list = array();
            if (self::isLocal($this->host_dict)) {
                $data_resource_list[] = $this->getFullURL(false) . 'today.php';
            } else {
                foreach ($this->host_dict as $host => $port) {
                    $one_list = $this->requireTodayData($host, $port);
                    $data_resource_list = array_merge($data_resource_list, $one_list);
                }
            }
        } else if (!($data_resource_list = $this->getSarDataFileList())) {
            // no sar data files found
            return '{}';
        }
        if ($cache_file && $report_time = $this->getJsonReportTimestamp($cache_file)) {
            if ($report_time > $this->getSarDataLatestTimestamp($data_resource_list)) {
                // JSON report file is newer than latest sar data file, no work to do
                return file_get_contents($cache_file);
            }
        }

        // process sar data files and build new JSON report data file
        $import_stat_file_data = new ImportStatFileData();
        foreach ($data_resource_list as $file) {
            // import each sar file from disc
            if (substr($file, 0, 4) !== 'http' && !is_file($file)) {
                continue;
            }
            $import_stat_file_data->importFile($file);
        }

        // generate JSON block and write to disc
        $build_json_structure = new BuildJsonStructure(
            $import_stat_file_data->getValidNetworkInterfaceList(),
            $import_stat_file_data->getTimePointList(),
            $import_stat_file_data->getStatTypeList()
        );
        $result = $build_json_structure->render();

        if ($cache_file && $fp = fopen($cache_file, 'w')) {
            fwrite($fp, $result);
            fclose($fp);
        }
        return $result;
    }

    public function getFileMask()
    {
        if ($this->days_ago < 0) {
            return 'sar??';
        } else if ($this->days_ago > 0) {
            $time = time();
            $days = array();
            for ($i = 1; $i <= $this->days_ago; $i ++) {
                $days[] = date('d', $time - 86400 * $i);
            }
            return 'sar{' . implode(',', $days) . '}';
        } else {
            return '';
        }
    }

    private function getSarDataFileList()
    {
        if (self::isLocal($this->host_dict)) {
            // remove any trailing slashes from sysstat data path
            $datadir = rtrim(LOCAL_DATA_PATH, '\//');
            $hostmask = '/';
        } else {
            // remove any trailing slashes from sysstat data path
            $datadir = rtrim(SYSSTAT_DATA_PATH, '\//');
            $hostmask = '/{' . implode(',', array_keys($this->host_dict)) . '}/';
        }
        // sysstat data path must exist
        if (!is_dir($datadir))
            return array();
        // fetch all files in data folder
        $mask = $datadir . $hostmask . $this->getFileMask();
        if (strpos($mask, '{') === false) {
            return glob($mask);
        } else {
            return glob($mask, GLOB_BRACE);
        }
    }

    private function getSarDataLatestTimestamp(array $inputfilelist)
    {
        $timestamp = 0;
        foreach ($inputfilelist as $file) {
            $filetimestamp = (is_file($file)) ? filemtime($file) : 0;
            $timestamp = ($filetimestamp > $timestamp) ? $filetimestamp : $timestamp;
        }
        // return the timestamp of the latest sar data file found
        return $timestamp;
    }

    private function getJsonReportTimestamp($cache_file)
    {
        if (is_file($cache_file)) {
            // if filesize of JSON structure file is zero (empty file) - then return a zero timestamp to allow new JSON data creation
            if (filesize($cache_file) == 0)
                return 0;
            // return file modified timestamp
            return filemtime($cache_file);
        }
        // no JSON structure file found
        return 0;
    }
}