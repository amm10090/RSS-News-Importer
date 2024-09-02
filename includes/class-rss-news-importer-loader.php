<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Loader {

    protected $actions;
    protected $filters;
    protected $shortcodes;
    private $logger;

    /**
     * 初始化加载器
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
        $this->shortcodes = array();
        $this->logger = new RSS_News_Importer_Logger();
    }

    /**
     * 添加新的动作钩子
     *
     * @param string $hook          钩子名称
     * @param object $component     注册钩子的对象
     * @param string $callback      回调方法名
     * @param int    $priority      优先级
     * @param int    $accepted_args 接受的参数数量
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * 添加新的过滤器钩子
     *
     * @param string $hook          钩子名称
     * @param object $component     注册钩子的对象
     * @param string $callback      回调方法名
     * @param int    $priority      优先级
     * @param int    $accepted_args 接受的参数数量
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * 添加新的短代码
     *
     * @param string $tag           短代码标签
     * @param object $component     注册短代码的对象
     * @param string $callback      回调方法名
     */
    public function add_shortcode($tag, $component, $callback) {
        $this->shortcodes = $this->add($this->shortcodes, $tag, $component, $callback, 0, 0);
    }

    /**
     * 通用的添加钩子方法
     *
     * @param array  $hooks         现有的钩子数组
     * @param string $hook          钩子名称
     * @param object $component     注册钩子的对象
     * @param string $callback      回调方法名
     * @param int    $priority      优先级
     * @param int    $accepted_args 接受的参数数量
     * @return array                更新后的钩子数组
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * 注册存储的钩子
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->shortcodes as $shortcode) {
            add_shortcode($shortcode['hook'], array($shortcode['component'], $shortcode['callback']));
        }
    }

    /**
     * 获取已注册的动作
     *
     * @return array 已注册的动作列表
     */
    public function get_actions() {
        return $this->actions;
    }

    /**
     * 获取已注册的过滤器
     *
     * @return array 已注册的过滤器列表
     */
    public function get_filters() {
        return $this->filters;
    }

    /**
     * 获取已注册的短代码
     *
     * @return array 已注册的短代码列表
     */
    public function get_shortcodes() {
        return $this->shortcodes;
    }

    /**
     * 移除指定的动作钩子
     *
     * @param string $hook      要移除的钩子名称
     * @param object $component 组件对象
     * @param string $callback  回调方法名
     */
    public function remove_action($hook, $component, $callback) {
        $this->actions = $this->remove($this->actions, $hook, $component, $callback);
    }

    /**
     * 移除指定的过滤器钩子
     *
     * @param string $hook      要移除的钩子名称
     * @param object $component 组件对象
     * @param string $callback  回调方法名
     */
    public function remove_filter($hook, $component, $callback) {
        $this->filters = $this->remove($this->filters, $hook, $component, $callback);
    }

    /**
     * 通用的移除钩子方法
     *
     * @param array  $hooks     现有的钩子数组
     * @param string $hook      要移除的钩子名称
     * @param object $component 组件对象
     * @param string $callback  回调方法名
     * @return array            更新后的钩子数组
     */
    private function remove($hooks, $hook, $component, $callback) {
        return array_filter($hooks, function($item) use ($hook, $component, $callback) {
            return !($item['hook'] === $hook && $item['component'] === $component && $item['callback'] === $callback);
        });
    }
}