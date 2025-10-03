[![CI](https://github.com/Neuron-PHP/cms/actions/workflows/ci.yml/badge.svg)](https://github.com/Neuron-PHP/cms/actions)
# Neuron-PHP CMS

A lightweight Content Management System component for PHP 8.4+ built on the Neuron MVC framework. This component provides blog and content management functionality with support for articles, categories, tags, RSS feeds, and markdown rendering.

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Components](#core-components)
- [Configuration](#configuration)
- [Usage Examples](#usage-examples)
- [Controllers](#controllers)
- [Blog Features](#blog-features)
- [Testing](#testing)
- [Dependencies](#dependencies)
- [More Information](#more-information)

## Installation

### Requirements

- PHP 8.4 or higher
- Composer
- Neuron MVC component (0.7.*)
- Blahg article repository system (0.5.*)

### Install via Composer

```bash
composer require neuron-php/cms
```

## Quick Start

### 1. Create Your Directory Structure

```bash
mkdir -p blog
mkdir -p config
mkdir -p public
mkdir -p resources/views/blog
mkdir -p resources/views/static_pages
mkdir -p resources/views/layouts
mkdir -p resources/views/http_codes
mkdir -p storage
```

### 2. Configure Your Application

Create a `config/config.yaml` file:

```yaml
system:
  base_path: .
  routes_path: config

site:
  name: My Blog
  title: Welcome to My Blog
  description: A blog about technology and programming
  url: https://example.com

views:
  path: views

cache:
  enabled: true
  storage: file
  path: cache/views
  ttl: 3600
```

### 3. Set Up Routes

Create a `config/routes.yaml` file:

```yaml
routes:
  # Blog homepage
  blog_index:
    route: /blog
    method: GET
    controller: Neuron\Cms\Controllers\Blog@index

  # Individual article
  blog_article:
    route: /blog/article/{title}
    method: GET
    controller: Neuron\Cms\Controllers\Blog@show

  # Category listing
  blog_category:
    route: /blog/category/{category}
    method: GET
    controller: Neuron\Cms\Controllers\Blog@category

  # Tag listing
  blog_tag:
    route: /blog/tag/{tag}
    method: GET
    controller: Neuron\Cms\Controllers\Blog@tag

  # Author articles
  blog_author:
    route: /blog/author/{author}
    method: GET
    controller: Neuron\Cms\Controllers\Blog@author

  # RSS feed
  blog_feed:
    route: /blog/rss
    method: GET
    controller: Neuron\Cms\Controllers\Blog@feed

  # Static page (markdown)
  page:
    route: /page/{page}
    method: GET
    controller: Neuron\Cms\Controllers\Content@markdown
```

### 4. Create the Front Controller

Create a `public/index.php` file:

```php
<?php
require_once '../vendor/autoload.php';

use function Neuron\Mvc\Boot;
use function Neuron\Mvc\Dispatch;

// Bootstrap the application
$app = Boot('../config');

// Dispatch the current request
Dispatch($app);
```

### 5. Create a Sample Article

Create `blog/articles/hello-world.md`:

```markdown
---
title: Hello World
slug: hello-world
author: John Doe
date_published: 2025-01-01
categories:
  - General
tags:
  - introduction
  - welcome
excerpt: Welcome to my new blog!
---

# Hello World

Welcome to my new blog built with Neuron-PHP CMS!

This is my first post using the CMS component.
```

## Core Components

### Content Controller

The base `Content` controller provides foundational CMS functionality:

- **Site Configuration**: Manages site name, title, description, and URL
- **Version Tracking**: Automatically loads and tracks version information
- **SEO Metadata**: Handles titles and descriptions for search engines
- **RSS Feed URLs**: Configures feed URLs for content syndication
- **Markdown Rendering**: Support for static markdown pages
- **Registry Integration**: Settings management through the Registry pattern

### Blog Controller

The `Blog` controller extends Content with blog-specific features:

- **Article Management**: Full CRUD operations for blog articles
- **Categorization**: Organize content by categories
- **Tagging System**: Flexible content tagging
- **Author Filtering**: Filter articles by author
- **RSS/Atom Feeds**: Automatic feed generation
- **Draft Mode**: Preview unpublished content with `?drafts=1`
- **SEO-Friendly URLs**: Slug-based routing
- **Error Handling**: Graceful handling of missing articles

## Configuration

### Site Settings

Configure your site in `config/config.yaml`:

```yaml
site:
  name: My Tech Blog
  title: Technology and Programming
  description: Articles about web development, PHP, and software engineering
  url: https://myblog.com
```

### Blog Repository Location

The blog repository path is configured in the Blog controller constructor:

```php
// Default location is ../blog
$this->setRepository(new Repository(
    "../blog",  // Path to your blog content
    $drafts     // Enable draft mode
));
```

### View Templates

Create view templates in your configured views directory:

#### views/blog/index.php
```php
<div class="blog-index">
    <h1><?= $Title ?></h1>
    <p><?= $Description ?></p>

    <?php foreach ($Articles as $article): ?>
        <article>
            <h2>
                <a href="/blog/article/<?= $article->getSlug() ?>">
                    <?= $article->getTitle() ?>
                </a>
            </h2>
            <div class="meta">
                By <?= $article->getAuthor() ?>
                on <?= $article->getDatePublished() ?>
            </div>
            <p><?= $article->getExcerpt() ?></p>
        </article>
    <?php endforeach; ?>
</div>
```

#### views/blog/show.php
```php
<article>
    <h1><?= $Article->getTitle() ?></h1>
    <div class="meta">
        By <?= $Article->getAuthor() ?>
        on <?= $Article->getDatePublished() ?>
    </div>
    <div class="content">
        <?= $Article->getBody() ?>
    </div>
    <div class="tags">
        <?php foreach ($Article->getTags() as $tag): ?>
            <a href="/blog/tag/<?= $tag ?>">#<?= $tag ?></a>
        <?php endforeach; ?>
    </div>
</article>
```

## Usage Examples

### Creating a Custom Content Controller

```php
namespace App\Controllers;

use Neuron\Cms\Controllers\Content;
use Neuron\Mvc\Responses\HttpResponseStatus;

class PageController extends Content
{
    public function about(array $params): string
    {
        return $this->renderHtml(
            HttpResponseStatus::OK,
            [
                'Title' => 'About Us | ' . $this->getName(),
                'Description' => 'Learn more about our company',
                'Content' => $this->loadAboutContent()
            ],
            'about'
        );
    }

    public function contact(array $params): string
    {
        return $this->renderHtml(
            HttpResponseStatus::OK,
            [
                'Title' => 'Contact | ' . $this->getName(),
                'Description' => 'Get in touch with us',
                'Email' => 'contact@example.com'
            ],
            'contact'
        );
    }
}
```

### Extending the Blog Controller

```php
namespace App\Controllers;

use Neuron\Cms\Controllers\Blog;
use Blahg\Repository;

class CustomBlog extends Blog
{
    public function __construct($app = null)
    {
        parent::__construct($app);

        // Custom repository configuration
        $this->setRepository(new Repository(
            "/path/to/articles",
            true  // Always show drafts
        ));
    }

    public function featured(array $params): string
    {
        $featured = $this->getRepository()
            ->getArticlesByTag('featured');

        return $this->renderHtml(
            HttpResponseStatus::OK,
            [
                'Articles' => $featured,
                'Title' => 'Featured Articles | ' . $this->getName()
            ],
            'featured'
        );
    }
}
```

## Blog Features

### Article Structure

Articles are markdown files with YAML frontmatter:

```markdown
---
title: Article Title
slug: article-slug
author: Author Name
date_published: 2025-01-15
date_updated: 2025-01-20
categories:
  - Technology
  - Programming
tags:
  - php
  - web-development
excerpt: Brief description of the article
featured_image: /images/article.jpg
draft: false
---

# Article Content

Your markdown content here...
```

### Draft Mode

Enable draft mode to preview unpublished articles:

```
https://example.com/blog?drafts=1
```

### Category and Tag Filtering

- **By Category**: `/blog/category/technology`
- **By Tag**: `/blog/tag/php`
- **By Author**: `/blog/author/john-doe`

### RSS Feed Generation

Automatic RSS/Atom feed generation at `/blog/rss` includes:

- Article title and content
- Publication dates
- Author information
- Categories and tags
- Links to full articles

## Controllers

### Content Controller Methods

| Method | Description |
|--------|-------------|
| `getName()` | Get the site name |
| `getTitle()` | Get the site title |
| `getDescription()` | Get the site description |
| `getUrl()` | Get the site URL |
| `getRssUrl()` | Get the RSS feed URL |
| `markdown($params)` | Render a markdown page |

### Blog Controller Methods

| Method | Description |
|--------|-------------|
| `index($params, $request)` | Display all articles |
| `show($params, $request)` | Display a single article |
| `category($params, $request)` | Display articles by category |
| `tag($params, $request)` | Display articles by tag |
| `author($params, $request)` | Display articles by author |
| `feed($params, $request)` | Generate RSS feed |
| `getRepository()` | Get the article repository |

## Testing

Run the test suite:

```bash
# Run all tests
vendor/bin/phpunit tests

# Run with coverage
vendor/bin/phpunit tests --coverage-html coverage

# Run specific test file
vendor/bin/phpunit tests/Cms/Controllers/BlogTest.php
```

### Test Structure

```
tests/
├── Cms/
│   └── Controllers/
│       ├── BlogTest.php
│       └── ContentTest.php
├── bootstrap.php
└── phpunit.xml
```

## Dependencies

The CMS component requires:

- **neuron-php/mvc** (0.7.*): MVC framework functionality
- **neuron-php/cli** (0.1.*): CLI command support
- **ljonesfl/blahg** (0.5.*): Article repository system
- **PHP Extensions**: curl, json

### Development Dependencies

- **phpunit/phpunit** (9.*): Unit testing
- **mikey179/vfsstream** (^1.6): Virtual filesystem for testing

## Advanced Features

### Custom Repository Configuration

```php
use Blahg\Repository;
use Blahg\Filter\CategoryFilter;
use Blahg\Sort\DateSort;

$repository = new Repository(
    "/path/to/articles",
    false,  // drafts
    new CategoryFilter(['technology']),
    new DateSort('desc')
);
```

### Multiple Content Sources

```php
class MultiSourceBlog extends Blog
{
    private array $repositories = [];

    public function addRepository(string $name, Repository $repo): void
    {
        $this->repositories[$name] = $repo;
    }

    public function getAggregatedArticles(): array
    {
        $articles = [];
        foreach ($this->repositories as $repo) {
            $articles = array_merge($articles, $repo->getArticles());
        }
        return $articles;
    }
}
```

### Content Caching

Leverage the MVC component's caching system:

```yaml
cache:
  enabled: true
  storage: redis
  ttl: 3600
  html: true
  markdown: true
```

## More Information

- **Neuron Framework**: [neuronphp.com](http://neuronphp.com)
- **GitHub**: [github.com/neuron-php/cms](https://github.com/neuron-php/cms)
- **Packagist**: [packagist.org/packages/neuron-php/cms](https://packagist.org/packages/neuron-php/cms)

## License

MIT License - see LICENSE file for details
