<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 相册插件（最终稳定版）
 * 
 * @package AutoLayoutGallery
 * @author 网友小宋&豆包
 * @version 1.0.2
 * @link https://xyzbz.cn
 */
class AutoLayoutGallery_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array(__CLASS__, 'parse');
        Typecho_Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'outputHeader');
        Typecho_Plugin::factory('Widget_Archive')->footer = array(__CLASS__, 'outputFooter');
        return _t('相册插件已激活，点击图片在本页弹窗');
    }

    public static function deactivate()
    {
        return _t('相册插件已禁用');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $maxCols = new Typecho_Widget_Helper_Form_Element_Text(
            'maxCols', 
            null, 
            '6', 
            _t('最大列数'), 
            _t('单排最多显示的图片数量（默认6）')
        );
        $form->addInput($maxCols);

        $gap = new Typecho_Widget_Helper_Form_Element_Text(
            'gap', 
            null, 
            '10px', 
            _t('图片间距'), 
            _t('图片之间的间距（默认10px）')
        );
        $form->addInput($gap);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 解析内容生成相册结构
     */
    public static function parse($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        if (!$widget instanceof Widget_Abstract_Contents || !self::isSinglePage()) {
            return $content;
        }

        $options = Typecho_Widget::widget('Widget_Options')->plugin('AutoLayoutGallery');
        $maxCols = intval($options->maxCols ?? 6);
        $gap = $options->gap ?? '10px';

        // 匹配[photos]标签
        $content = preg_replace_callback(
            '/\[photos\](.*?)\[\/photos\]/ism',
            function ($matches) use ($maxCols, $gap) {
                $html = $matches[1];
                $images = [];

                // 解析Markdown和HTML图片
                preg_match_all('/!\[(.*?)\]\((\s*?[^"\s)]+\s*?)(?:["\'](.*?)["\'])?\)/i', $html, $mdMatches, PREG_SET_ORDER);
                preg_match_all('/<img\s+src="([^"]+)"\s+alt="([^"]*)"\s*(title="([^"]*)")?\/?>/i', $html, $htmlMatches, PREG_SET_ORDER);

                // 提取图片信息
                foreach (array_merge($mdMatches, $htmlMatches) as $img) {
                    if (strpos($img[0], '![]') !== false) {
                        $images[] = [
                            'src' => trim($img[2]),
                            'alt' => $img[1] ?: '图片',
                            'title' => $img[3] ?: ($img[1] ?: '图片')
                        ];
                    } else {
                        $images[] = [
                            'src' => $img[1],
                            'alt' => $img[2] ?: '图片',
                            'title' => $img[4] ?: ($img[2] ?: '图片')
                        ];
                    }
                }

                $total = count($images);
                if ($total === 0) return '';

                // 计算列数
                $cols = min($total, $maxCols);
                $galleryId = 'gallery-' . uniqid();
                $itemsHtml = '';

                // 生成图片项（添加额外class确保选择器唯一）
                foreach ($images as $index => $img) {
                    $count = ($index + 1) . '/' . $total;
                    $itemsHtml .= <<<HTML
<div class="gallery-item">
    <a href="{$img['src']}" class="gallery-link gallery-link-{$galleryId}" 
       data-gallery="{$galleryId}" 
       data-caption="{$img['title']}" 
       data-index="{$index}">
        <img src="{$img['src']}" alt="{$img['alt']}" class="gallery-img" loading="lazy">
        <div class="gallery-overlay">+</div>
    </a>
</div>
HTML;
                }

                return <<<HTML
<div class="gallery-container" id="{$galleryId}" style="--cols: {$cols}; --gap: {$gap};">
    {$itemsHtml}
</div>
HTML;
            },
            $content
        );

        return $content;
    }

    /**
     * 输出头部样式
     */
    public static function outputHeader()
    {
        if (!self::isSinglePage()) return;

        echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">' . "\n";
        echo <<<CSS
<style>
.gallery-container {
    display: grid;
    grid-template-columns: repeat(var(--cols), 1fr);
    gap: var(--gap);
    margin: 20px 0;
    padding: 0;
}
@media (max-width: 767px) {
    .gallery-container {
        grid-template-columns: 1fr !important;
    }
}
.gallery-item {
    position: relative;
    border-radius: 4px;
    overflow: hidden;
    list-style: none;
}
.gallery-link {
    display: block;
    position: relative;
    height: 200px;
    overflow: hidden;
    cursor: pointer; /* 显示手型光标，提示可点击 */
}
.gallery-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.gallery-link:hover .gallery-img {
    transform: scale(1.08);
}
.gallery-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    font-size: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.gallery-link:hover .gallery-overlay {
    opacity: 1;
}
/* 灯箱样式 */
.fancybox__container {
    --fancybox-bg: rgba(0, 0, 0, 0.9);
    z-index: 99999 !important; /* 确保在最顶层 */
    position: fixed !important; /* 固定在当前页面 */
}
.fancybox__button--close {
    width: 36px;
    height: 36px;
    color: #fff;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 50%;
    top: 20px;
    right: 20px;
    opacity: 1 !important;
}
.fancybox__caption {
    color: #fff;
    padding: 10px 20px;
}
</style>
CSS;
    }

    /**
     * 输出灯箱脚本（核心：阻止跳转，强制本页弹窗）
     */
    public static function outputFooter()
    {
        if (!self::isSinglePage()) return;

        echo '<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>' . "\n";
        echo <<<JS
<script>
// 等待DOM完全加载后再绑定事件
document.addEventListener('DOMContentLoaded', function() {
    // 收集所有相册组
    const galleryGroups = {};
    
    // 第一步：收集图片信息，建立分组
    document.querySelectorAll('.gallery-link[data-gallery]').forEach(link => {
        const groupId = link.getAttribute('data-gallery');
        if (!galleryGroups[groupId]) {
            galleryGroups[groupId] = [];
        }
        // 存储图片链接和标题
        galleryGroups[groupId].push({
            src: link.getAttribute('href'),
            caption: link.getAttribute('data-caption')
        });
    });

    // 第二步：为每个图片绑定点击事件，强制阻止跳转
    document.querySelectorAll('.gallery-link[data-gallery]').forEach(link => {
        link.addEventListener('click', function(e) {
            // 双重阻止默认行为（关键修复）
            e.preventDefault ? e.preventDefault() : (e.returnValue = false);
            e.stopPropagation ? e.stopPropagation() : (e.cancelBubble = true);

            const groupId = this.getAttribute('data-gallery');
            const index = parseInt(this.getAttribute('data-index'));
            const slides = galleryGroups[groupId] || [];

            if (slides.length === 0) return;

            // 初始化灯箱并显示
            const fancybox = new Fancybox(slides, {
                startIndex: index,
                container: document.body, // 强制在body内渲染
                appendTo: document.body,
                closeButton: true,
                escapeKey: true,
                clickOutside: true,
                loop: true,
                // 禁用所有可能导致跳转的配置
                hash: false, // 不修改URL
                trapFocus: true // 焦点锁定在灯箱内
            });

            fancybox.show();
        });

        // 额外处理：防止其他脚本干扰
        link.addEventListener('mousedown', function(e) {
            e.preventDefault ? e.preventDefault() : (e.returnValue = false);
        });
    });
});

// Pjax无刷新场景兼容
$(document).on('pjax:complete', function() {
    const galleryGroups = {};
    document.querySelectorAll('.gallery-link[data-gallery]').forEach(link => {
        const groupId = link.getAttribute('data-gallery');
        if (!galleryGroups[groupId]) galleryGroups[groupId] = [];
        galleryGroups[groupId].push({
            src: link.getAttribute('href'),
            caption: link.getAttribute('data-caption')
        });
    });

    document.querySelectorAll('.gallery-link[data-gallery]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault ? e.preventDefault() : (e.returnValue = false);
            e.stopPropagation ? e.stopPropagation() : (e.cancelBubble = true);

            const groupId = this.getAttribute('data-gallery');
            const index = parseInt(this.getAttribute('data-index'));
            const slides = galleryGroups[groupId] || [];

            new Fancybox(slides, {
                startIndex: index,
                container: document.body,
                closeButton: true
            }).show();
        });
    });
});
</script>
JS;
    }

    private static function isSinglePage()
    {
        return Typecho_Widget::widget('Widget_Archive')->is('single');
    }
}
