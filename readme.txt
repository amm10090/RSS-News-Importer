=== RSS News Importer ===
Contributors: HuaYangTian
Donate link: https://blog.amoze.cc/donate
Tags: rss, news, importer, feed
Requires at least: 5.2
Tested up to: 6.4
Stable tag: 2.5.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import news articles from RSS feeds into WordPress posts automatically.

== Description ==

RSS News Importer is a powerful WordPress plugin that automatically imports news articles from RSS feeds into your WordPress site as posts. It's perfect for news aggregators, content curators, or anyone looking to automatically populate their site with fresh content from various sources.

Key features:

* Import articles from multiple RSS feeds
* Customize import settings for each feed
* Set post categories and tags automatically
* Import images and set featured images
* Schedule regular imports
* Filter content based on keywords
* Preserve original article attribution

This plugin is ideal for:

* News websites
* Content aggregators
* Niche bloggers
* Anyone who wants to keep their site updated with the latest content from various sources

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/rss-news-importer` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->RSS News Importer screen to configure the plugin
4. (Make your instructions match the desired user flow for your plugin)

== Frequently Asked Questions ==

= How often does the plugin import new articles? =

By default, the plugin checks for new articles every hour. You can adjust this frequency in the plugin settings.

= Can I import from multiple RSS feeds? =

Yes, you can add as many RSS feeds as you like. Each feed can have its own import settings.

= Will this plugin slow down my website? =

No, the import process runs in the background and should not affect your website's performance.

== Changelog ==
= 2.0.0=
1.对插件进行大量重构
2.优化插件样式
3.优化导入帖子内容净化
4.优化缓存机制和RSS解析器
5.重构插件目录,
6.优化日志查看器
7.大量国际化本地化支持

== Upgrade Notice ==
= 1.6.0 =
1.增加仪表盘页面支持显示各种状态，支持快速操作，支持错误日志查看
2.修复报错和异常
3.优化代码结构
4.优化前端样式
5.修复日志问题
6.优化了国际化支持，改进了翻译文件

= 1.5.0 =
完善RSS管理功能，支持添加、编辑和删除RSS源
优化了国际化支持，改进了翻译文件
修复了设置无法保存的问题
为用户提供更好的RSS内容导入体验。

=1.4.5 =
改进和增强

用户界面优化

重新设计了管理界面，采用现代化的卡片式布局
实现了选项卡式导航，提高了设置页面的可用性
改进了按钮和输入框的样式，提升了整体视觉体验
添加了平滑的过渡动画，增强了用户交互体验


功能增强

新增了RSS源管理功能，支持添加、编辑和排序RSS源
改进了导入过程，现在可以显示导入进度条
添加了导入日志功能，方便追踪和调试导入过程
优化了RSS源的添加流程，提高了操作效率
增加了内容排除功能，允许用户设置不想导入的内容


性能优化

优化了JavaScript代码，提高了前端性能
改进了AJAX请求处理，提升了后端响应速度


安全性

增强了AJAX请求的安全性检查
改进了用户权限验证机制



Bug修复

修复了在某些情况下设置无法保存的问题
解决了导入过程中可能出现的内存溢出问题
修复了日志查看器在某些环境下无法正确加载的问题

开发者相关

重构了插件的核心类，提高了代码的可维护性
添加了更多的钩子和过滤器，方便进行自定义开发
改进了错误处理和日志记录机制，便于调试

其他改进

更新了插件文档，反映了新的功能和设置选项
优化了国际化支持，改进了翻译文件的结构

注意：RSS源的预览和移除功能正在开发中，将在下一版本中推出。 

=1.3.6 =
1.优化rss源显示页面问题
2.优化日志页面布局和样式 为不同的日志级别显示不同的颜色
3.日志支持筛选 搜索 升降序,增加删除日志功能
4.国际化和本地化 添加更多的内容

=1.3.0 =
1. 图片处理优化:
   - 改进图片URL处理,移除查询参数以解决文件类型无效问题
   - 实现图片下载重试机制,使用指数退避策略
   - 添加图片压缩功能,优化上传的图片大小
   - 增加对WebP等现代图片格式的支持
   - 实现简单的图片缓存机制,避免重复下载

2. 内容导入改进:
   - 优化文章重复检查逻辑,使用GUID或链接作为唯一标识
   - 添加内容过滤功能,支持通过CSS选择器或文本模式排除特定内容
   - 改进从文章内容中提取第一张图片作为封面的逻辑

3. 错误处理和日志:
   - 增强错误日志记录,提供更详细的导入过程信息
   - 实现查看导入日志的功能

4. 性能优化:
   - 实现导入限制设置,控制每次从RSS源导入的文章数量
   - 优化图片下载和处理流程,提高导入效率

5. 用户界面改进:
   - 添加RSS源预览功能
   - 优化插件设置页面,增加更多自定义选项

6. 安全性增强:
   - 增加用户权限检查
   - 改进数据验证和清理流程

7. 兼容性提升:
   - 增加对多语言支持的改进
   - 确保与最新版本的WordPress兼容

8. 代码重构:
   - 优化类结构,提高代码可维护性
   - 实现更多的钩子和过滤器,便于扩展

这次更新全面提升了RSS News Importer插件的性能、稳定性和功能性,为用户提供更好的RSS内容导入体验。

=1.3.1 =
RSS News Importer - 更新日志
用户界面改进：
1. 重新设计了管理界面，采用现代化的卡片式布局。
2. 实现了选项卡式导航，提高了设置页面的可用性。
3. 优化了RSS源管理界面，使添加和删除源更加直观。
4. 改进了按钮和输入框的样式，提升了整体视觉体验。
5. 添加了平滑的过渡动画，增强了用户交互体验。

功能增强：
1. 新增了RSS源预览功能，可以在添加前查看源的内容。
2. 改进了导入过程，现在可以显示每个源的导入结果。
3. 添加了导入日志功能，方便追踪和调试导入过程。
4. 优化了RSS源的添加和删除流程，提高了操作效率。
5. 增加了内容排除功能，允许用户设置不想导入的内容。

性能优化：
1. 优化了JavaScript代码，提高了前端性能。
2. 改进了AJAX请求处理，提升了后端响应速度。

本地化：
1. 完善了中文（简体）翻译，覆盖了插件的所有文本。
2. 优化了翻译文件结构，便于未来添加更多语言支持。

安全性：
1. 增强了AJAX请求的安全性检查。
2. 改进了用户权限验证机制。

其他改进：
1. 更新了代码注释，提高了代码的可读性和可维护性。
2. 优化了错误处理和用户反馈机制。
3. 更新了插件文档，反映了新的功能和设置选项。