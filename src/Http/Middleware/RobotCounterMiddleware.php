<?php

namespace Workable\RobotCounter\Http\Middleware;

use Closure;
use Jenssegers\Agent\Agent;

class RobotCounterMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $agent    = new Agent();
        if ($agent->isRobot() && !$request->ajax())
        {
            if (!in_array($request->method(), config('robot_counter.accepted_methods'))
                || !in_array($agent->robot(), config('robot_counter.list_bot')))
                return $response;

            $date     = now()->format('Y-m-d');
            $config   = config('robot_counter');
            $filePath = $config['storage_path'] . '/' . $config['prefix_log_file'] . '-' . $date . '.log';

            try {
                if (!file_exists($filePath))
                    $file = fopen($filePath, 'w');
                else
                    $file = fopen($filePath, 'a');

                $dataLog = [
                    'time'         => now()->format('Y-m-d H:i:s'),
                    'bot'          => $agent->robot(),
                    'execute_time' => (int)((microtime(true) - LARAVEL_START_EXECUTION_TIME) * 1000),
                    'uri'          => urldecode($request->getRequestUri()),
                    'agent'        => get_agent(),
                    'ip_address'   => ip_user_client()
                ];
                fwrite($file, json_encode($dataLog)."\n");
                fclose($file);
            }
            catch (\Exception $e)
            {
                \Log::error($e->getMessage());
            }
        }

        return $response;
    }
}
