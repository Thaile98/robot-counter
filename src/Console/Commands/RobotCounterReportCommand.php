<?php

namespace Thailv\RobotCounter\Console\Commands;

use Carbon\Carbon;
use DateTime;
use Illuminate\Console\Command;
use Workable\Support\Http\HttpBuilder;

class RobotCounterReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'robot-counter:report
    {--date=today : range time needs to report [today, yesterday, week, month, range]}
    {--start= : range time needs to report [YYYY-MM-DD]}
    {--end= : range time needs to report [YYYY-MM-DD]}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Count robot visit from file and push api to reporter to save report';

    private $http;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->http = (new HttpBuilder())->host(config('url.reporter') ?? 'https://reporter.123job.vn');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $config = config('robot_counter');
        $date   = $this->option('date');

        $this->__deleteOldFile($config);

        list($start, $end) = $this->__parseDate($date);

        $dates = $this->__getDatesFromRange($start, $end);

        $allDataBot = $this->__extractDataBot($dates, $config);

        $this->__saveLog($allDataBot, $config);

    }

    private function __extractDataBot($dates, $config)
    {
        foreach ($dates as $date) {
            $robots = [];
            $file = $config['storage_path'] . '/' . $config['prefix_log_file'] . '-' . $date . '.log';
            if (!file_exists($file)) {
                $this->warn($date . ' no data ');
                continue;
            }
            $this->info("- Pushing data at: ".$date);
            $botList = [];
            $this->__processFile($file, $date, $robots, $botList);
            $allDataBot[$date] = $robots;
        }

        return $allDataBot ?? [];
    }

    private function __saveLog($allDataBot, $config)
    {
        if(!$allDataBot) return false;

        foreach ($allDataBot as $date => $paths) {
            foreach ($paths as $path => $robotInfo) {
                $dataPost = [];
                foreach ($robotInfo as $bot => $info) {
                    $dataPost = [
                        'app_int'          => $config['app_int'],
                        'bot'              => strtolower($bot),
                        'path'             => $path,
                        'date'             => $date,
                        'total_visit'      => $info['total_visit'],
                        'total_time'       => $info['total_time'],
                        'avg_execute_time' => $info['avg_execute_time'],
                        'max_execute_time' => $info['max_execute_time'],
                        'min_execute_time' => $info['min_execute_time'],
                        'by_hour'          => json_encode($info['by_hour']),
                        'ip_address'       => $info['ip_address']
                    ];
                }
                $this->http->post('api/robot-counter/store')
                    ->formParams($dataPost)
                    ->call();
            }
        }
    }

    private function __processFile($file, $date, &$robots, $botList)
    {
        $handle = fopen($file, "r");
        while (!feof($handle)) {
            $line = fgets($handle);
            if (strlen($line)) {
                $dataLog = json_decode($line, true);
                $time    = $dataLog['time'];
                $hour    = (int)date('H', strtotime($time));

                $bot          = $dataLog['bot'];
                $execute_time = $dataLog['execute_time'];
                $uri          = urldecode($dataLog['uri']);

                if (!in_array($bot, $botList) || !isset($robots[$uri])) {
                    $botList[] = $bot;
                    for ($i = 0; $i < 24; $i++) {
                        $time = (int)date("H", strtotime($date . '00:00:00') + 3600 * $i);
                        $robots[$uri][$bot]['by_hour'][$time]['total_visit']      = 0;
                        $robots[$uri][$bot]['by_hour'][$time]['avg_execute_time'] = 0;
                    }
                }

                $dataBot     = $robots[$uri][$bot];
                $dataBotHour = $dataBot['by_hour'];

                $dataBotHour[$hour]['avg_execute_time'] = (int)(
                    ((int)$execute_time + $dataBotHour[$hour]['avg_execute_time'] * $dataBotHour[$hour]['total_visit'])
                    / ($dataBotHour[$hour]['total_visit'] + 1));
                $dataBotHour[$hour]['total_visit']++;

                $dataBot['by_hour'] = $dataBotHour;

                if (array_key_exists('total_visit', $dataBot)) {
                    $dataBot['total_visit']++;
                    $dataBot['total_time'] += (int)$execute_time;

                    $dataBot['avg_execute_time'] = (int)($dataBot['total_time'] / $dataBot['total_visit']);
                    $dataBot['max_execute_time'] = (int)$execute_time > $dataBot['max_execute_time']
                                                    ? (int)$execute_time
                                                    : $dataBot['max_execute_time'];
                    $dataBot['min_execute_time'] = (int)$execute_time < $dataBot['min_execute_time']
                                                    ? (int)$execute_time
                                                    : $dataBot['min_execute_time'];
                } else {
                    $dataBot['total_visit']      = 1;
                    $dataBot['total_time']       = (int)$execute_time;
                    $dataBot['avg_execute_time'] = (int)$execute_time;
                    $dataBot['max_execute_time'] = (int)$execute_time;
                    $dataBot['min_execute_time'] = (int)$execute_time;
                }
                $dataBot['ip_address'] = $dataLog['ip_address'];

                $robots[$uri][$bot] = $dataBot;
            }
        }
        fclose($handle);
    }

    /**
     * @param $date
     *
     * @return array [start, end]
     */
    private function __parseDate($date)
    {
        switch ($date) {
            case "yesterday":// count cho hom qua
                $now   = new Carbon();
                $end   = $now->format('Y-m-d');
                $start = $now->sub(new \DateInterval('P1D'))->format('Y-m-d');
                break;
            case "week":// count cho tuan nay
                $now   = new Carbon();
                $end   = $now->format('Y-m-d');
                $start = $now->sub(new \DateInterval('P1W'))->format('Y-m-d');
                break;
            case "month":// count cho thang nay
                $now   = new Carbon();
                $end   = $now->format('Y-m-d');
                $start = $now->sub(new \DateInterval('P1M'))->format('Y-m-d');
                break;
            case "range":// count lai tat ca
                $start = new Carbon($this->option('start'));
                $start = $start->format('Y-m-d');
                $end   = new Carbon($this->option('end'));
                $end   = $end->format('Y-m-d');
                break;
            case "today":// chi count cho hom nay
            default:
                $now   = new Carbon($date);
                $end   = $now->format('Y-m-d');
                $start = $now->format('Y-m-d');
                break;
        }
        return [$start, $end];
    }

    private function __getDatesFromRange($start, $end, $format = 'Y-m-d')
    {
        $array    = array();
        $interval = new \DateInterval('P1D');
        $realEnd  = new DateTime($end);
        $realEnd->add($interval);
        $period = new \DatePeriod(new DateTime($start), $interval, $realEnd);
        foreach ($period as $date) {
            $array[] = $date->format($format);
        }
        return $array;
    }

    /**
     * Xóa cac file log từ 5 ngày trước
     * @param $config
     */
    private function __deleteOldFile($config)
    {
        $storagePath = $config['storage_path'];
        $prefixFile  = $config['prefix_log_file'];
        $maxDayLog   = $config['max_day_log'];

        foreach(glob($storagePath.'/'.$prefixFile.'-*.log') as $file) {
            $fileName = basename($file);
            $date = str_replace($prefixFile.'-', '', $fileName);
            $date = str_replace('.log', '', $date);

            $date = Carbon::createFromFormat('Y-m-d', $date);
            if($date->diffInDays(Carbon::today()) >= $maxDayLog) unlink($file);
        }
    }
}
