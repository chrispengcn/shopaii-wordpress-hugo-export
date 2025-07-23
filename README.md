# Shopaii WordPress to Hugo Exporter

A WordPress plugin that exports posts, pages, products, and categories to Hugo-compatible Markdown files.

## Features

- Export WordPress posts, pages, and products to Markdown
- Export categories and product categories
- Customizable export directories
- Option to overwrite existing files or skip them
- Configurable maximum export count
- Hugo-compatible front matter with proper YAML formatting
- Preserves metadata including dates, categories, tags, and featured images
- Special handling for WooCommerce products with additional product metadata

## Installation

1. Download the plugin zip file
2. In your WordPress admin dashboard, navigate to Plugins > Add New
3. Click "Upload Plugin" and select the zip file
4. Activate the plugin after installation

## Usage

1. In your WordPress admin dashboard, find the "HUGOCMS exporter" menu item
2. Configure your export settings:
   - Select export directory (default or English)
   - Choose which categories to export
   - Set overwrite options for existing files
   - Define maximum export count
   - Select content types to export (posts, pages, products, or all)
3. Click "Start Export" to begin the export process
4. After completion, you'll see a summary of exported, skipped, and failed items

## Exported Content

The plugin exports content to Markdown files with YAML front matter containing:

- Layout information
- Title and slug
- URLs and permalinks
- Publication date
- Categories and tags
- Featured image
- For products: SKU, product categories, product tags, buy links, images, and short description

## Export Directory

By default, files are exported to:
- `/wp-content/hugo/_default_project/content/` for the default directory
- `/wp-content/hugo/_default_project/content/en/` for the English directory

Subdirectories are automatically created for different content types:
- Posts: `/blog/`
- Pages: `/pages/`
- Products: `/products/`
- Categories: `/categories/`
- Product categories: `/product_categories/`

## Requirements

- WordPress 4.7 or higher
- PHP 5.6 or higher
- WooCommerce (if exporting products)

## License

This plugin is released under the GPL-2.0+ license.

## Author

Chris  
[https://hugocms.net](https://hugocms.net)
