# Robots counter
This package allow you to track how many bots visit your website, their frequency and time execution for each request.


### Installation
In your project folder, run

<code>composer require thailv/robot-counter</code>

After finish, publish vendor by this command:

<code>php artisan vendor:publish --provider="Workable\RobotCounter\Providers\RobotCounterServiceProvider"</code>

and <code>php artisan migrate</code> to run migration file

### Usage Instructions
This package works by using a middleware, logging every request performed by bots in a log file, you can rename the middleware in <code>config/robots_counter.php</code> file.

If you want the middleware works for every request, just put its class <code>\Workable\RobotCounter\Http\Middleware\RobotCounterMiddleware::class</code> in array <code>$middleware </code> in <code>app/Http/Kernel.php</code>
But the best practise is using this middleware for routes need reporting for better performance.
Also, you can config your accepted request methods you want to be in your log.

### Push api to reporter to save log robot counter
Logs are saved to reporter by command <code>robot-counter:report</code>, you can set schedule for run this command to push report every option time. 
You can use it to make report for specific day, use <code>php artisan robot-counter:report --help</code> to see usage.



