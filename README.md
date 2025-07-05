# shopaii-wordpress-hugo-export
read from the database and export the wordpress website to hugo cms

# WordPress to Hugo Exporter

This plugin allows you to export your WordPress posts, pages, and products as Hugo-compatible Markdown files, enabling you to easily migrate content to the Hugo static site generator.

## Features

- Supports exporting posts, pages, and products (requires WooCommerce plugin installation)
- Preserves metadata such as categories, tags, and featured images
- Generates Hugo-compatible Front Matter format
- Automatically creates appropriate directory structures
- Displays progress and result statistics during the export process

## Installation

1. Download the plugin zip file from GitHub
2. Navigate to "Plugins" > "Add New" in your WordPress admin panel
3. Click the "Upload Plugin" button
4. Select the downloaded zip file and upload it
5. Activate the plugin

## Usage

1. Go to "Tools" > "Hugo Export" in your WordPress admin panel
2. Choose the content types to export (posts, pages, products, or all)
3. Click the "Start Export" button
4. Wait for the export to complete and view the result statistics
5. Exported Markdown files will be saved in the `wp-content/md/` directory

## Export File Structure

The plugin creates the following directory structure:
md/
└── content/
    ├── posts/
    ├── pages/
    └── products/
Each content type is exported to its corresponding directory, with filenames in the format "date-slug.md".

## Front Matter Format

Exported Markdown files include the following Front Matter metadata:
---
layout: post
title: "Post Title"
slug: "post-slug"
permalink: "/post-slug/"
date: 2023-01-01 12:00:00
categories:
- Category 1
- Category 2
featureImage: https://example.com/image.jpg
image: https://example.com/image.jpg
tags: ["Tag 1", "Tag 2"]
---
For products, additional metadata includes:
sku: "Product SKU"
product_categories:
- Product Category 1
- Product Category 2
product_tags:
- Product Tag 1
- Product Tag 2
buy_link: https://example.com/buy
images:
- https://example.com/featured.jpg
- https://example.com/gallery1.jpg
- https://example.com/gallery2.jpg
description: >
  Brief product description...

## Notes

- Ensure your server has sufficient permissions to create and write files in the `wp-content/` directory
- Exporting large websites may take significant time; please be patient
- Do not close your browser window during the export process
- Exported file paths and URLs are based on your current WordPress settings
- For sites with extensive content, consider exporting in batches

## Support

If you encounter any issues, please submit an issue on GitHub and we will respond promptly.

## Contributions

We welcome contributions to this plugin! You can submit pull requests or suggest improvements.

## License

This plugin is released under the GPL-2.0+ license.


# WordPress to Hugo Exporter

这个插件可以将你的WordPress文章、页面和产品导出为Hugo兼容的Markdown文件，让你能够轻松地将内容迁移到Hugo静态网站生成器。

## 功能特点

- 支持导出文章、页面和产品（需安装WooCommerce插件）
- 保留分类、标签、特色图片等元数据
- 生成Hugo兼容的Front Matter格式
- 自动创建适当的目录结构
- 导出过程中显示进度和结果统计

## 安装方法

1. 从GitHub下载插件压缩包
2. 在WordPress后台导航到"插件" > "添加新插件"
3. 点击"上传插件"按钮
4. 选择下载的压缩包并上传
5. 激活插件

## 使用方法

1. 在WordPress后台导航到"工具" > "Hugo导出"
2. 选择要导出的内容类型（文章、页面、产品或全部）
3. 点击"开始导出"按钮
4. 等待导出完成，查看导出结果统计
5. 导出的Markdown文件将保存在`wp-content/md/`目录下

## 导出文件结构

插件会创建以下目录结构：
md/
└── content/
    ├── posts/
    ├── pages/
    └── products/
每个内容类型都会被导出到对应的目录中，文件名将采用"日期-别名.md"的格式。

## Front Matter格式

导出的Markdown文件将包含以下Front Matter元数据：
---
layout: post
title: "文章标题"
slug: "post-slug"
permalink: "/post-slug/"
date: 2023-01-01 12:00:00
categories:
- 分类1
- 分类2
featureImage: https://example.com/image.jpg
image: https://example.com/image.jpg
tags: ["标签1", "标签2"]
---
对于产品类型，还会包含额外的元数据：
sku: "产品SKU"
product_categories:
- 产品分类1
- 产品分类2
product_tags:
- 产品标签1
- 产品标签2
buy_link: https://example.com/buy
images:
- https://example.com/featured.jpg
- https://example.com/gallery1.jpg
- https://example.com/gallery2.jpg
description: >
  产品简短描述...
## 注意事项

- 请确保你的服务器有足够的权限在`wp-content/`目录下创建和写入文件
- 导出大型网站可能需要较长时间，请耐心等待
- 导出过程中不要关闭浏览器窗口
- 导出的文件路径和URL基于你的WordPress当前设置
- 如果你的网站包含大量内容，建议分批次导出

## 支持

如果你在使用过程中遇到任何问题，请在GitHub上提交issue，我们会尽快回复。

## 贡献

欢迎对本插件进行贡献！你可以提交pull request或提出改进建议。

## 许可证

本插件采用GPL-2.0+许可证发布。
    
