<?php
/**
 * Created by PhpStorm.
 * User: SongKejing
 * QQ: 597481334
 * Date: 2017-04-07
 * Time: 17:37
 */

namespace app\console;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use Workerman\Lib\Timer;
use Workerman\Worker;

/**
 * 浏览器自动刷新
 * Class BrowserSync
 * @package app\console
 */
class BrowserSync extends Command
{

    /**
     * socket端口
     * @var int
     */
    private $socket_port = 8000;

    /**
     * 输出
     * @var null
     */
    private $output_object = null;

    /**
     * 输入
     * @var null
     */
    private $input_object = null;

    /**
     * pid file
     * @var string
     */
    private $worker_man_pid_file = LOG_PATH . 'browser_sync.pid';

    /**
     * log file
     * @var string
     */
    private $worker_man_log_file = LOG_PATH . 'browser_sync.log';

    /**
     * port file
     * @var string
     */
    private $worker_man_port_file = LOG_PATH . 'browser_sync.port';

    /**
     * 实例对象
     * @var null
     */
    private static $instance_instance = null;

    /**
     * 监听文件结果
     * @var array
     */
    private $scan_files = [];

    /**
     * 实例对象
     * @return BrowserSync|null
     */
    public static function instance()
    {
        if (self::$instance_instance == null) {
            self::$instance_instance = new self();
        }
        return self::$instance_instance;
    }

    /**
     * 配置文件
     */
    protected function configure()
    {
        $this->setName('browser_sync')
            ->addOption('--file_types', '-f', Option::VALUE_REQUIRED, '监听变化的文件后缀名，分号分割，例如：html;js;css;php', 'html;js;css;php')
            ->addOption('--dirs', '-d', Option::VALUE_REQUIRED, '监听web根目录下变化的文件夹，分号分割，例如：application;public', 'application;public')
            ->addOption('--port', '-p', Option::VALUE_REQUIRED, 'socket监听的端口，注意防火墙的设置', '8000')
            ->addOption('--command', '-c', Option::VALUE_REQUIRED, 'start/启动，start-d/启动（守护进程），status/状态, restart/重启，reload/平滑重启，stop/停止', 'start')
            ->setDescription('监听服务器文件，当文件修改时，自动同步刷新浏览器');
    }

    /**
     * 执行
     * @param Input $input
     * @param Output $output
     * @return bool
     */
    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        $this->output_object = $output;
        $this->input_object = $input;
        $this->socket_port = $input->getOption('port');
        return $this->syncClient();
    }

    /**
     * 输出信息
     * @param $msg
     */
    private function output($msg)
    {
        $this->output_object->highlight($msg);
    }

    /**
     * 同步客户端
     */
    private function syncClient()
    {
        $command = $this->input_object->getOption('command');
        $command = trim($command);
        $command = $this->spaceManyToOne($command);
        if (!in_array($command, ['start', 'start-d', 'stop', 'restart', 'reload', 'status'])) {
            $this->output('command input:' . $command . ' error，请输入如下命令：start/启动，start -d/启动（守护进程），status/状态, restart/重启，reload/平滑重启，stop/停止');
            exit;
        }
        if ($command == 'start-d') {
            $GLOBALS['argv'][1] = 'start';
            $GLOBALS['argv'][2] = '-d';
        } else {
            $GLOBALS['argv'][1] = $command;
        }
        $worker = new Worker(sprintf('websocket://0.0.0.0:%s', $this->socket_port));
        //进程名称
        $worker->name = __FILE__;
        //设置进程id文件地址
        $worker::$pidFile =$this->worker_man_pid_file;
        //设置日志文件
        $worker::$logFile = $this->worker_man_log_file;
        $worker->onWorkerStart = function ($worker) {
            //定时器定时监听
            Timer::add(0.5, function () use ($worker) {
                //记录端口号，用于生成js自动刷新代码
                $this->port($this->socket_port);
                if ($this->fileIsModify()) {
                    foreach ($worker->connections as $connection_each) {
                        usleep(50000);
                        $connection_each->close('sync');
                    }
                }
            });
        };
        $worker->onWorkerStop = function ($worker) {
            //删除端口号
            $this->port(-1);
            //刷新页面
            foreach ($worker->connections as $connection_each) {
                usleep(50000);
                $connection_each->close('sync');
            }
        };
        // 运行worker
        Worker::runAll();
        return true;
    }

    /**
     * 端口号
     * @param int $port 0/获取， < 0 / 删除，> 0 / 设置
     * @return bool|mixed
     */
    public function port($port = 0)
    {
        //清除缓存
        clearstatcache();
        if (empty($port)) {
            if (is_file($this->worker_man_port_file)) {
                //该文件修改时间小于安全时间，则判断当前监听无效
                if (filemtime($this->worker_man_port_file) + 2 < time()) {
                    //清除无效文件
                    foreach ([$this->worker_man_port_file,$this->worker_man_pid_file, $this->worker_man_log_file] as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    return null;
                } else {
                    return file_get_contents($this->worker_man_port_file);
                }
            } else {
                return null;
            }
        } else if ($port > 0) {
            return file_put_contents($this->worker_man_port_file, $port);
        } else {
            return unlink($this->worker_man_port_file);
        }
    }

    /**
     * 获取js拼接内容
     * @return string
     */
    public function getJsContent()
    {
        $port = $this->port();
        if (empty($port)) {
            return '';
        }
        return sprintf('<script type="text/javascript">
    var ws = new WebSocket(\'ws://%s:%s\');
    ws.onclose = function(){
        window.location.reload();
    };
</script>', request()->host(), $port);
    }

    /**
     * 获取监听的文件是否被修改
     * @return bool
     */
    private function fileIsModify()
    {
        //清除缓存
        clearstatcache();
        $last_scan_files = $this->scan_files;
        $files_types = $this->input_object->getOption('file_types');
        if (empty($files_types)) {
            $this->output('<error>请输入监听文件files的类型</error>');
            return false;
        }
        $files_types = explode(';', trim(trim(str_replace('；', ';', $files_types)), ';'));
        $dirs = $this->input_object->getOption('dirs');
        $root_path = dirname(dirname(__DIR__));
        $dirs = explode(';', trim(trim(str_replace('；', ';', $dirs)), ';'));
        $files = [];
        foreach ($dirs as $dir) {
            if (empty($dir) || !is_dir($root_path . '/' . $dir)) {
                continue;
            }
            $files = array_merge($files, $this->dirGetFiles($root_path . '/' . $dir, $files_types));
        }
        $scan_files = [];
        //文件内容改变，md5_file计算时间较长，以文件最后修改的时间为判断依据
        if (!empty($files)) {
            foreach ($files as $file) {
                $scan_files[$file] = filemtime($file);
            }
        }
        //判断文件是否改变
        $has_modify = false;
        //文件不存在
        if (empty($last_scan_files)) {
            $has_modify = true;
        } else {
            foreach ($scan_files as $file => $modify_time) {
                if (!array_key_exists($file, $last_scan_files) || $last_scan_files[$file] != $modify_time) {
                    echo sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $file);
                    $has_modify = true;
                    break;
                }
            }
        }
        //设置新文件记录
        $this->scan_files = $scan_files;
        return $has_modify;
    }

    /**
     * 多个空格转换为一个空格
     * @param $string
     * @param string $separator
     * @return string
     */
    private function spaceManyToOne($string, $separator = ' ')
    {
        return trim(preg_replace('/[\s]+/', $separator, $string));
    }

    /**
     * 获取文件夹下面的所有文件
     * @param $dir 文件夹目录绝对地址
     * @param array $file_types :文件类型array('pdf', 'doc')
     * @param array $ignore_dir_or_file : 忽略的文件或文件夹
     * @return array
     */
    private function dirGetFiles($dir, $file_types = array(), $ignore_dir_or_file = [])
    {
        foreach (['.', '..'] as $each) {
            if (!in_array($each, $ignore_dir_or_file)) {
                $ignore_dir_or_file[] = $each;
            }
        }
        $data = array();
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if (in_array($file, $ignore_dir_or_file)) {
                    continue;
                }
                if (is_dir($dir . '/' . $file)) {
                    $data = array_merge($data, $this->dirGetFiles($dir . '/' . $file, $file_types, $ignore_dir_or_file));
                } else {
                    if (empty($file_types)) {
                        $data[] = $dir . '/' . $file;
                    } else {
                        //判断类型
                        if (in_array($this->getSuffix($file), $file_types)) {
                            $data[] = $dir . '/' . $file;
                        }
                    }
                }
            }
        } else if (is_file($dir)) {
            if (empty($file_types)) {
                if (!in_array($dir, $ignore_dir_or_file)) {
                    $data[] = $dir;
                }
            } else {
                //判断类型
                if (in_array($this->getSuffix($dir), $file_types) && !in_array($dir, $ignore_dir_or_file)) {
                    $data[] = $dir;
                }
            }
        }
        return $data;
    }

    /**
     * 获取文件后缀名
     * @param $file
     * @return mixed
     */
    private function getSuffix($file)
    {
        return isset(pathinfo($file)['extension']) ? strtolower(pathinfo($file)['extension']) : '';
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {

    }

}