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
     * View constructor.
     * @param $template
     */
    public function __construct($template, $controller = null) {
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
     * 编译模板,转为php语法
     * @return string
     */
    public function compiled() {
        //首先读入模板,然后处理模板语法
        $template = file_get_contents($this->template_file);
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
                '/{\$(.*?)}/',//变量定义
                '/{:\$(.*?)}/',//变量输出
                '/{:(.*?)}/',//函数执行/变量操作输出
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
                '<?php echo ((($v=call_user_func([\$this,\'variable\'],\'$1\'))!==false)?\$v:\$$1);?>',//输出变量
                '<?php echo $1;?>',//输出函数/操作变量
                ''
            ]
        ];
        $template = preg_replace_callback('/{([^{}]|(?R))*}/is', function ($matches) use ($grammar) {
            return preg_replace($grammar[0], $grammar[1], $matches[0]);
        }, $template);
        $this->template = $template;
        return $template;
    }

    /**
     * 获取变量
     * @param string $variable
     * @return  mixed
     */
    protected function variable(string $variable) {
        //获取变量,先对变量进行处理,获取是否为数组的变量
        preg_match_all('/\[(.*?)\]/', $variable, $matches);
        if (sizeof($matches[0]) > 0) {
            $variable = substr($variable, 0, strpos($variable, '['));
        }
        $ret = false;
        if (isset($this->controller->$variable)) {
            $ret = $this->controller->$variable;
        }
        //进入数组循环,判断数组是否存在,存在继续循环,否则返回false
        foreach ($matches[1] as $value) {
            if (isset($ret[$value])) {
                $ret = $ret[$value];
            } else {
                return false;
            }
        }
        return $ret;
    }

    /**
     * 执行编译后的模板,返回html/文本代码
     */
    public function execute() {
        if (empty($this->template)) {
            $this->compiled();
        }
        extract($this->bind);
        ob_start();
        eval('?>' . $this->template);
        return ob_get_clean();
    }
}