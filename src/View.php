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
     * View constructor.
     * @param $template
     */
    public function __construct($template = '', $controller = null) {
        $this->template_file = $template;
        $this->controller = $controller;
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
     * 编译模板,转为php语法
     * @return string
     */
    public function compiled(): string {
        //首先读入模板,然后处理模板语法
        $template = file_get_contents($this->template_file);
        //模板语法替换成为php的语法,然后使用eval执行
        $grammar = [
            [
                '/{include(.*?)}/',
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
                '/{*(.*?)*}/'//注释
            ], [
                '<?php $1;?>',
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
                '<?php echo $this->$1;?>',//输出函数/操作变量
                ''
            ]
        ];

        $template = preg_replace_callback('/{([^{}]|(?R))*}/is', function ($matches) use ($grammar) {
            //对$符号和函数进行处理,替换成$this
            $matches[0] = str_replace('$', '$this->', $matches[0]);
            $ret = preg_replace($grammar[0], $grammar[1], $matches[0]);
            return $ret;
        }, $template);
        $this->template = $template;
        return $template;
    }

    /**
     * 使用魔术方法来调用方法
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments) {
        // TODO: Implement __call() method.
        //调用方法顺序,控制器>全局
        if (method_exists($this->controller, $name)) {
            return call_user_func_array([$this->controller, $name], $arguments);
        } else if (function_exists($name)) {
            return call_user_func_array($name, $arguments);
        }
        throw new MethodExistsException('Method does not exist');
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
        return ob_get_clean();
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
     */
    public function setTemplateFile(string $template_file): void {
        $this->template_file = $template_file;
    }

    public function __toString() {
        // TODO: Implement __toString() method.
        try {
            $ret = $this->execute();
        } catch (\Throwable $exception) {
            //toString 不能抛出异常...自己消化掉
        }
        return $ret;
    }
}