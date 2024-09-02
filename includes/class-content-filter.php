<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Content_Filter
{
    protected $logger;
    private $options;
    private $dom;
    private $xpath;

    public function __construct($logger)
    {
        $this->logger = $logger;
        $this->options = get_option('rss_news_importer_options', array());
        $this->dom = new DOMDocument();
        $this->dom->preserveWhiteSpace = false;
        libxml_use_internal_errors(true); // 抑制HTML错误
    }

    /**
     * 过滤内容
     *
     * @param string $content 原始内容
     * @return string 过滤后的内容
     */
    public function filter_content($content)
    {
        // 转换编码
        $content = $this->convert_to_utf8($content);

        // 加载HTML
        $this->load_html($content);

        // 应用过滤规则
        $this->apply_filters();

        // 清理和修复HTML
        $this->clean_and_repair_html();

        // 获取处理后的HTML
        $filtered_content = $this->get_filtered_html();

        // 应用自定义过滤器钩子
        $filtered_content = apply_filters('rss_news_importer_filtered_content', $filtered_content);

        return $filtered_content;
    }

    /**
     * 转换内容为UTF-8编码
     */
    private function convert_to_utf8($content)
    {
        $encoding = mb_detect_encoding($content, mb_detect_order(), true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        return $content;
    }

    /**
     * 加载HTML内容
     */
    private function load_html($content)
    {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
        $this->xpath = new DOMXPath($this->dom);
    }

    /**
     * 应用过滤规则
     */
    private function apply_filters()
    {
        $this->remove_unwanted_elements();
        $this->remove_unwanted_attributes();
        $this->convert_relative_urls();
        $this->handle_iframes();
        $this->limit_content_length();
    }

    /**
     * 移除不需要的元素
     */
    private function remove_unwanted_elements()
    {
        $unwanted_elements = $this->options['unwanted_elements'] ?? ['script', 'style', 'iframe', 'form'];
        foreach ($unwanted_elements as $element) {
            $nodes = $this->xpath->query("//{$element}");
            foreach ($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        // 移除空段落
        $empty_paragraphs = $this->xpath->query("//p[not(normalize-space())]");
        foreach ($empty_paragraphs as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * 移除不需要的属性
     */
    private function remove_unwanted_attributes()
    {
        $unwanted_attributes = $this->options['unwanted_attributes'] ?? ['style', 'onclick', 'onload'];
        $nodes = $this->xpath->query("//*[@*]");
        foreach ($nodes as $node) {
            foreach ($unwanted_attributes as $attr) {
                $node->removeAttribute($attr);
            }
        }
    }

    /**
     * 转换相对URL为绝对URL
     */
    private function convert_relative_urls()
    {
        $base_url = $this->options['base_url'] ?? '';
        if (empty($base_url)) {
            return;
        }

        $nodes = $this->xpath->query("//a[@href] | //img[@src]");
        foreach ($nodes as $node) {
            if ($node->tagName === 'a' && $node->hasAttribute('href')) {
                $href = $node->getAttribute('href');
                if (strpos($href, 'http') !== 0) {
                    $node->setAttribute('href', $base_url . ltrim($href, '/'));
                }
            } elseif ($node->tagName === 'img' && $node->hasAttribute('src')) {
                $src = $node->getAttribute('src');
                if (strpos($src, 'http') !== 0) {
                    $node->setAttribute('src', $base_url . ltrim($src, '/'));
                }
            }
        }
    }

    /**
     * 处理iframe
     */
    private function handle_iframes()
    {
        $iframe_policy = $this->options['iframe_policy'] ?? 'remove';
        $nodes = $this->xpath->query("//iframe");
        foreach ($nodes as $node) {
            switch ($iframe_policy) {
                case 'remove':
                    $node->parentNode->removeChild($node);
                    break;
                case 'placeholder':
                    $placeholder = $this->dom->createElement('p', '[嵌入内容]');
                    $node->parentNode->replaceChild($placeholder, $node);
                    break;
                case 'allow':
                    // 保留iframe，但移除可能的脚本属性
                    $node->removeAttribute('onload');
                    break;
            }
        }
    }

    /**
     * 限制内容长度
     */
    private function limit_content_length()
    {
        $max_length = $this->options['max_content_length'] ?? 0;
        if ($max_length > 0) {
            $body = $this->xpath->query("//body")->item(0);
            if ($body) {
                $content = $body->textContent;
                if (mb_strlen($content) > $max_length) {
                    $truncated = mb_substr($content, 0, $max_length) . '...';
                    $new_body = $this->dom->createElement('body');
                    $new_body->textContent = $truncated;
                    $body->parentNode->replaceChild($new_body, $body);
                }
            }
        }
    }

    /**
     * 清理和修复HTML
     */
    private function clean_and_repair_html()
    {
        // 移除注释
        $comments = $this->xpath->query('//comment()');
        foreach ($comments as $comment) {
            $comment->parentNode->removeChild($comment);
        }

        // 修复未闭合的标签
        $this->dom->recover = true;
        $this->dom->strictErrorChecking = false;
    }

    /**
     * 获取过滤后的HTML
     */
    private function get_filtered_html()
    {
        return $this->dom->saveHTML();
    }

    /**
     * 净化HTML内容
     *
     * @param string $html HTML内容
     * @return string 净化后的HTML
     */
    public function sanitize_html($html)
    {
        $allowed_html = wp_kses_allowed_html('post');

        // 添加一些额外允许的标签和属性
        $allowed_html['iframe'] = array(
            'src' => true,
            'width' => true,
            'height' => true,
            'frameborder' => true,
            'allowfullscreen' => true,
        );

        return wp_kses($html, $allowed_html);
    }

    /**
     * 检测并移除恶意内容
     *
     * @param string $content 内容
     * @return string 清理后的内容
     */
    public function remove_malicious_content($content)
    {
        // 检测和移除可能的XSS攻击
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $content);
        $content = preg_replace('/on\w+="[^"]*"/is', '', $content);

        // 检测和移除可能的SQL注入
        $content = preg_replace('/\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|TRUNCATE|ALTER|CREATE|TABLE|FROM|WHERE|AND|OR)\b/is', '', $content);

        // 移除潜在的危险URL
        $content = preg_replace('/(https?:\/\/|www\.)(?![^" ]*(?:jpg|jpeg|png|gif|svg|webp))[^" ]*/i', '[链接已移除]', $content);

        return $content;
    }
}
