# browser_sync
基于tp5浏览器自动刷新测试工具  
思路来源于[http://www.browsersync.cn/](http://www.browsersync.cn/)  
实现方式是采用socket通知机制
### 安装依赖workerman
`composer require workerman/workerman`
#### 下载文件，并覆盖本地代码

---
基于nodejs的浏览器自动刷新，现已有[http://www.browsersync.cn/](http://www.browsersync.cn/)，该工具实现了代码修改浏览器自动刷新功能。据说能提高约30%的开发效率，但是必须得安装nodejs，node比较耗资源，而且对于php开发人员来说维护起来比较麻烦。故开发php版本的同步刷新工具。基于workerman+tp5，算是使用tp5框架的php开发者的一个福音吧，tp3可以对应的仿照实现。
## 使用方式
项目根目录执行  
```
php think
```  
执行结果 
```  
......  
    Available commands:   
    browser_sync        监听服务器文件，当文件修改时，自动同步刷新浏览器
    build               Build Application Dirs
    clear               Clear runtime file
    help                Displays help for a command
    list                Lists commands
......
```
查看帮助
```
php think browser_sync --help
```
执行结果

```
Usage:
  browser_sync [options]

Options:
  -f, --file_types=FILE_TYPES  监听变化的文件后缀名，分号分割，例如：html;js;css;php [default: "html;js;css;php"]
  -d, --dirs=DIRS              监听web根目录下变化的文件夹，分号分割，例如：application;public [default: "application;public"]
  -p, --port=PORT              socket监听的端口，注意防火墙的设置 [default: "8000"]
  -c, --command=COMMAND        start/启动，start-d/启动（守护进程），status/状态, restart/重启，reload/平滑重启，stop/停止 [default: "start"]
  -h, --help                   Display this help message
  -V, --version                Display this console version
  -q, --quiet                  Do not output any message
      --ansi                   Force ANSI output
      --no-ansi                Disable ANSI output
  -n, --no-interaction         Do not ask any interactive question
  -v|vv|vvv, --verbose         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```  
默认方式执行，或监听default中的定义
```
php think browser_sync
```
守护进程方式执行

```
php think browser_sync -c start-d
```
仅仅监听php文件
```
php think browser_sync -f php
```
修改监听的端口，比如改为6000
```
php think browser_sync -p 6000
```
上述工作做完之后，手工刷新下浏览器，则在浏览器下方自动拼接相关js刷新代码
```
<script type="text/javascript">
    var ws = new WebSocket('ws://yourdomain:8000');
    ws.onclose = function(){
        window.location.reload();
    };
</script>
```
这时，修改任意被监听的代码，则浏览器会自动刷新。




