<?php


namespace HuanL\Viewdeal;


class View {

    /**
     * 模板文件
     * @var string
     */
    private $template_file = '';

    /**
     * 编译后的模板
     * @var string
     */
    private $template = '';
    /**
     * 控制器的对象
     * @var null
     */
    private $controller = null;

    /**
     * 绑定的变量
     * @var array
     */
    private $bind = [];

    /**
     * 保存构建新的变量列表
     * @var array
     */
    private $variable = [];

    /**
     * 缓存目录
     * @var string
     */
    private $cacheDir = '';

    /**
     * layout文件路径
     * @var string
     */
    private static $layoutPath = null;

    /**
     * View constructor.
     * @param $template
     */
    public function __construct($template = '', $controller = null) {
        $this->setTemplateFile($template);
        $this->controller = $controller;
    }

    /**
     * 设置布局layout
     * @param null $path
     */
    public static function setLayout($path = null) {
        self::$layoutPath = $path;
    }

    /**
     * 绑定值
     * @param string $parameter
     * @param mixed $value
     */
    public function bindValue(string $parameter, $value) {
        $this->bind[$parameter] = $value;
    }

    /**
     * 绑定变量
     * @param string $parameter
     * @param $value
     */
    public function bindParam(string $parameter, &$value) {
        $this->bind[$parameter] = $value;
    }

    /**
     * 设置缓存目录
     * @param $dir
     * @return $this
     */
    public function setCacheDir($dir) {
        $this->cacheDir = $dir;
        return $this;
    }

    /**
     * 获取缓存文件
     * @return string
     */
    public function getCacheFilePath() {
        return $this->cacheDir . '/' . md5($this->template_file) . '.php';
    }

    /**
     * 编译模板,转为php语法
     * @return string
     */
    public function compiled(): string {
        $cacheFile = $this->getCacheFilePath();
        //读取缓存
        if ($this->cacheDir &&
            file_exists($cacheFile)
            && filemtime($this->template_file) < filemtime($cacheFile)) {
            return $this->template = file_get_contents($cacheFile);
        }
        //首先读入模板,然后处理模板语法
        $template = file_get_contents($this->template_file);
        //处理得到模板目录路径
        $segPos = 0;
        $segPos = strrpos($this->template_file, '/');
        $templateDir = substr($this->template_file, 0, $segPos + 1);
        //模板语法替换成为php的语法,然后使用eval执行
        $grammar = [
            [
                '/{include \'(.*?)\'}/',
                '/{if(.*?)}/',
                '/{elif(.*?)}/',
                '/{else}/',
                '/{\/if}/',
                '/{foreach (.*?)}/',
                '/{\/foreach}/',
                '/{break}/',
                '/{continue}/',
                '/{for(.*?)}/',
                '/{\/for}/',
                '/{\$(.*?)}/',//变量操作
                '/{:\$(.*?)}/',//变量操作
                '/{:(.*?)}/',//函数操作
                '/{\*(.*?)\*}/'//注释
            ], [
                '<?php echo (new self(\'' . $templateDir . '$1.html\',$this->controller))->setCacheDir("' . $this->cacheDir . '")
                ->execute();?>',
                '<?php if($1):?>',
                '<?php elseif($1):?>',
                '<?php else:?>',
                '<?php endif;?>',
                '<?php foreach($1):?>',
                '<?php endforeach;?>',
                '<?php break;?>',
                '<?php continue;?>',
                '<?php for($1):?>',
                '<?php endfor;?>',
                '<?php \$$1;?>',//变量定义
                '<?php echo \$$1;?>',
                '<?php echo $$1;?>',//输出函数/操作变量
                ''
            ]
        ];
        $template = preg_replace($grammar[0], $grammar[1], $template);
        $template = preg_replace_callback('#<\?php (.*?)\?>#', function ($matches) use ($grammar) {
            //对$符号和函数进行处理,替换成$this
            $matches[0] = str_replace('$', '$this->', $matches[0]);
            return $matches[0];
        }, $template);
        $this->template = $template;
        if ($this->cacheDir) {
            //如果不能写出缓存,禁止报错
            try {
                @file_put_contents($this->getCacheFilePath(), $template);
            } catch (\Throwable $throwable) {

            }
        }
        return $template;
    }

    /**
     * 使用魔术方法来调用方法
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments) {
        // TODO: Implement __call() method.
        //调用方法顺序:全局>控制器
        if (function_exists($name)) {
            return call_user_func_array($name, $arguments);
        }
        return call_user_func_array([$this->controller, $name], $arguments);
    }

    /**
     * 使用魔术方法获取变量
     * @param $name
     * @return mixed
     */
    public function __get($name) {
        // TODO: Implement __get() method.
        //优先级为绑定的变量(bind)>控制器成员>构建新的变量
        //如果都没有找到,则创建一个新的
        if (isset($this->bind[$name])) {
            return $this->bind[$name];
        } else if (isset($this->controller->$name)) {
            return $this->controller->$name;
        } else if (isset($this->variable[$name])) {
            return $this->variable[$name];
        }
        return $this->variable[$name] = false;
    }

    /**
     * 使用魔术方法赋值变量
     * @param $name
     * @param $value
     * @return mixed
     */
    public function __set($name, $value) {
        // TODO: Implement __set() method.
        if (isset($this->bind[$name])) {
            return $this->bind[$name] = $value;
        } else if (isset($this->controller->$name)) {
            return $this->controller->$name = $value;
        }
        return $this->variable[$name] = $value;
    }

    /**
     * 执行编译后的模板,返回html/文本代码
     * @return string
     */
    public function execute(): string {
        if (empty($this->template)) {
            $this->compiled();
        }
        ob_start();
        eval('?>' . $this->template);
        $content = ob_get_clean();
        if (self::$layoutPath === null) {
            return $content;
        }
        $this->setTemplateFile(self::$layoutPath);
        $this->bindValue('page_content', $content);
        $compile = $this->compiled();
        ob_start();
        eval('?>' . $compile);
        $content = ob_get_clean();
        return $content;
    }

    /**
     * 设置模板代码
     * @param string $template
     */
    public function setTemplate(string $template): void {
        $this->template = $template;
    }

    /**
     * 设置模板文件
     * @param string $template_file
     * @return View
     */
    public function setTemplateFile(string $template_file): self {
        $this->template_file = realpath(str_replace('\\', '/', $template_file));
        return $this;
    }

    public function __toString() {
        // TODO: Implement __toString() method.
        $ret = '';
        try {
            $ret = $this->execute();
        } catch (\Throwable $exception) {
            //toString 不能抛出异常...自己消化掉
        }
        return $ret;
    }
}