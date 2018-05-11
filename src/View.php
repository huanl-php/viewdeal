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
    public function bindValue(string $parameter, mixed $value) {
        $this->bind[$parameter] = $value;
    }

    /**
     * 绑定变量值
     * @param string $parameter
     * @param mixed $variable
     */
    public function bindParam(string $parameter, mixed &$variable) {
        $this->bind[$parameter] = $variable;
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
                '/{\$(.*?)}/',//变量操作
                '/{:(.*?)}/',//输出操作
                '/{foreach (.*?)}/',
                '/{\/foreach}/',
                '/{break}/',
                '/{continue}/',
                '/{for(.*?)}/',
                '/{\/for}/',
                '/{*(.*?)*}/'//注释
            ],
            [
                '<?php $1;?>',
                '<?php if($1):?>',
                '<?php elseif($1):?>',
                '<?php else:?>',
                '<?php endif;?>',
                '<?php \$$1;?>',
                '<?php echo $1;?>',
                '<?php foreach($1):?>',
                '<?php endforeach;?>',
                '<?php break;?>',
                '<?php continue;?>',
                '<?php for($1):?>',
                '<?php endfor;?>',
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