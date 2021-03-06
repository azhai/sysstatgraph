<?php
// ImportStatFileData.php
class ImportStatFileData 
{
    const filesection_taskspersecond = 'taskspersecond';
    const filesection_cpuutilisation = 'cpuutilisation';
    const filesection_memoryusage = 'memoryusage';
    const filesection_swapusage = 'swapusage';
    const filesection_load = 'load';
    const filesection_network = 'network';
    // $filesectionkeylist stores key header fields we are looking for to know what part of the sar file we are currently reading
    private $filesectionkeylist = array(
        'proc/s' => self::filesection_taskspersecond,
        'CPU' => self::filesection_cpuutilisation,
        'kbmemfree' => self::filesection_memoryusage,
        'kbswpfree' => self::filesection_swapusage,
        'runq-sz' => self::filesection_load,
        'IFACE' => self::filesection_network
        );
    // $datalinepartslookuplist stores the number of data fields we expect per each row type
    private $datalinepartslookuplist = array(
        self::filesection_taskspersecond => 3,
        self::filesection_cpuutilisation => 11,
        self::filesection_memoryusage => 8,
        self::filesection_swapusage => 6,
        self::filesection_load => 6,
        self::filesection_network => 9
        );

    private $networkinterfacelist = array();
    private $validnetworkinterfacelist = array();
    private $timepointlist = array();
    private $stattypelist = array();
    private $currentfilesection = '';
    private $twelvehourtimeformat = false;

    public function __construct()
    {
        $this->networkinterfacelist = unserialize(NETWORK_INTERFACE_LIST);
    }

    public function importFile($inputfilename)
    {
        // die($inputfilename);
        $this->currentfilesection = '';
        $fp = fopen($inputfilename, 'r');
        // first line will contain the date of the report - must exist
        if (!($filedatetimestamp = (!feof($fp)) ? $this->getFiledateTimestamp(fgets($fp)) : false)) {
            // cant locate file date - exit
            fclose($fp);
            return;
        }

        $firstdataline = true;
        while (!feof($fp)) {
            $linetext = trim(fgets($fp));
            if ($linetext == '') {
                // empty line - next line
                $this->currentfilesection = '';
                continue;
            }

            if ($firstdataline) {
                // determine if times are in 12/24 hour format
                $this->twelvehourtimeformat = preg_match('/^\d{2}:\d{2}:\d{2} (AM|PM) /', $linetext);
                $firstdataline = false;
            }

            if ($this->twelvehourtimeformat) {
                // remove space between AM/PM in 12hour time format
                $linetext = str_replace(' AM ', 'AM ', $linetext);
                $linetext = str_replace(' PM ', 'PM ', $linetext);
            }
            // split up line into parts
            $lineparts = preg_split('/ +/', $linetext);

            if ((isset($lineparts[1])) && ($this->checkFileSection($lineparts[1]))) {
                // found next data section in file - next line
                continue;
            }
            // get report time for the current line as a unix timestamp
            // if FALSE, then not a valid line we want to process
            if (!($linetimestamp = $this->getLineTimestamp($lineparts[0]))) {
                // invalid time - next line
                continue;
            }
            // offset line timestamp by the file date
            $linetimestamp += $filedatetimestamp;
            // record the data line values, validating the data line has the right number of data parts
            $this->recordDataLine($linetimestamp, $lineparts);
        }

        fclose($fp);
    }

    public function getValidNetworkInterfaceList()
    {
        return array_keys($this->validnetworkinterfacelist);
    }

    public function getTimePointList()
    {
        // sort time point list in ascending order
        $sortedtimepointlist = array_keys($this->timepointlist);
        sort($sortedtimepointlist, SORT_NUMERIC);

        return $sortedtimepointlist;
    }

    public function getStatTypeList()
    {
        $sortedstattypelist = array();

        foreach (array_keys($this->stattypelist) as $typename) {
            // sort all time based data for the stat type
            $sortdatalist = $this->stattypelist[$typename];
            ksort($sortdatalist, SORT_NUMERIC);
            // throw result into $sortedstattypelist
            $sortedstattypelist[$typename] = $sortdatalist;
        }
        // return the time sorted $sortedstattypelist array
        return $sortedstattypelist;
    }

    private function getFiledateTimestamp($inputfirstline)
    {
        $inputfirstline = rtrim($inputfirstline);
        // convert YYYY-MM-DD or MM/DD/YYYY to a unix timestamp
        $pattern1 = '!(?P<year>\d{4})-(?P<month>\d{2})-(?P<day>\d{2})!';
        $pattern2 = '!(?P<month>\d{2})/(?P<day>\d{2})/(?P<year>\d{4})!';
        return (preg_match($pattern1, $inputfirstline, $match) || preg_match($pattern2, $inputfirstline, $match))
        ? mktime(0, 0, 0, intval($match['month']), intval($match['day']), intval($match['year']))
        : false;
    }

    private function checkFileSection($inputvalue)
    {
        if (!isset($this->filesectionkeylist[$inputvalue])) {
            // key not found - ignore
            return false;
        }
        // store current file section
        $this->currentfilesection = $this->filesectionkeylist[$inputvalue];
        return true;
    }

    private function getLineTimestamp($inputtime)
    {
        if (!$this->twelvehourtimeformat) {
            // try hh:mm:ss formatted time
            if (preg_match('/^(?P<hour>\d{2}):(?P<minute>\d{2}):(?P<second>\d{2})$/', $inputtime, $matches)) {
                // found match - convert to timestamp
                return (intval($matches['hour']) * 3600) + (intval($matches['minute']) * 60) + intval($matches['second']);
            }
        }
        // try hh:mm:ss AM/PM formatted time
        if (preg_match('/^(?P<hour>\d{2}):(?P<minute>\d{2}):(?P<second>\d{2})(?P<period>AM|PM)$/', $inputtime, $matches)) {
            // handle AM/PM
            $hour = intval($matches['hour']);
            if ($hour == 12) $hour = 0;
            if ($matches['period'] == 'PM') $hour += 12;;
            // found match - convert to timestamp
            return ($hour * 3600) + (intval($matches['minute']) * 60) + intval($matches['second']);
        }
        // not a valid match
        return false;
    }

    private function recordDataLine($inputtimestamp, array $inputlinepartlist)
    {
        // validate the data line has the right number of data parts for the file section
        if (
            (!isset($this->datalinepartslookuplist[$this->currentfilesection])) ||
                ($this->datalinepartslookuplist[$this->currentfilesection] != sizeof($inputlinepartlist))
                ) {
            // invalid number of line data parts for current file section - reject the data line
            return;
        }
        // tasks created/context switches (per second)
        if ($this->currentfilesection == self::filesection_taskspersecond) {
            $this->recordStat($inputtimestamp, 'taskspersecond', floatval($inputlinepartlist[1]));
            $this->recordStat($inputtimestamp, 'cswitchpersecond', floatval($inputlinepartlist[2]));
            return;
        }
        // CPU utilisation (%)
        if ($this->currentfilesection == self::filesection_cpuutilisation) {
            // we only want the 'all' CPU report lines
            if ($inputlinepartlist[1] != 'all') return;

            $this->recordStat($inputtimestamp, 'cpuuser', floatval($inputlinepartlist[2]));
            $this->recordStat($inputtimestamp, 'cpusystem', floatval($inputlinepartlist[4]));
            $this->recordStat($inputtimestamp, 'cpuiowait', floatval($inputlinepartlist[5]));

            return;
        }
        // memory usage kilobytes (convert from kilobytes to megabytes)
        if ($this->currentfilesection == self::filesection_memoryusage) {
            $this->recordStat($inputtimestamp, 'mbmemoryused', $this->twoDecimalPlaces(floatval($inputlinepartlist[2] / 1024)));
            return;
        }
        // swap usage kilobytes (convert from kilobytes to megabytes)
        if ($this->currentfilesection == self::filesection_swapusage) {
            $this->recordStat($inputtimestamp, 'mbswapused', $this->twoDecimalPlaces(floatval($inputlinepartlist[2] / 1024)));
            return;
        }
        // system load averages
        if ($this->currentfilesection == self::filesection_load) {
            $runtaskcount = intval($inputlinepartlist[1]);
            $this->recordStat($inputtimestamp, 'taskcountrun', $runtaskcount);
            $this->recordStat($inputtimestamp, 'taskcountsleep', intval($inputlinepartlist[2]) - $runtaskcount);

            $this->recordStat($inputtimestamp, 'loadavg1', floatval($inputlinepartlist[3]));
            $this->recordStat($inputtimestamp, 'loadavg5', floatval($inputlinepartlist[4]));
            $this->recordStat($inputtimestamp, 'loadavg15', floatval($inputlinepartlist[5]));

            return;
        }
        // network traffic (per second)
        if ($this->currentfilesection == self::filesection_network) {
            // we only want network adapters that are in our $this->networkinterfacelist
            $adaptername = $inputlinepartlist[1];
            if (!in_array($adaptername, $this->networkinterfacelist)) return;
            // save as valid network adapter
            $this->validnetworkinterfacelist[$adaptername] = true;
            // packets sent/received
            $this->recordStat($inputtimestamp, 'pcktsrecvpersecond-' . $adaptername, floatval($inputlinepartlist[2]));
            $this->recordStat($inputtimestamp, 'pcktstrnspersecond-' . $adaptername, floatval($inputlinepartlist[3]));
            // KB's sent/received - now Kbit (~clockfort)
            $this->recordStat($inputtimestamp, 'kbrecvpersecond-' . $adaptername, (round($inputlinepartlist[4] * 8)));
            $this->recordStat($inputtimestamp, 'kbtrnspersecond-' . $adaptername, (round($inputlinepartlist[5] * 8)));

            return;
        }
    }

    private function recordStat($inputtimestamp, $inputstattype, $inputvalue)
    {
        $this->timepointlist[$inputtimestamp] = true;
        $this->stattypelist[$inputstattype][$inputtimestamp] = $inputvalue;
    }

    private function twoDecimalPlaces($inputvalue)
    {
        if (preg_match('/^\d+\.\d{1,2}/', $inputvalue, $matches)) {
            // return value rounded to two decimal places
            return $matches[0];
        }
        // no match, just return original value
        return $inputvalue;
    }
}