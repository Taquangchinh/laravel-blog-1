<?php

namespace Bjuppa\LaravelBlog;

use Bjuppa\LaravelBlog\Contracts\Blog as BlogContract;
use Bjuppa\LaravelBlog\Contracts\BlogEntry;
use Bjuppa\LaravelBlog\Contracts\BlogEntryProvider;
use Bjuppa\LaravelBlog\Support\Author;
use Bjuppa\LaravelBlog\Support\HandlesRoutes;
use Bjuppa\LaravelBlog\Support\ProvidesBladeViewNames;
use Bjuppa\LaravelBlog\Support\ProvidesTranslationKeys;
use Bjuppa\LaravelBlog\Support\QueriesEntryProvider;
use Bjuppa\MetaTagBag\MetaTagBag;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Blog implements BlogContract
{
    use ProvidesBladeViewNames;
    use ProvidesTranslationKeys;
    use HandlesRoutes;
    use QueriesEntryProvider;

    /**
     * Blog identifier
     * @var string
     */
    protected $id;

    /**
     * The path part of this blog's url
     * @var string
     */
    protected $public_path;

    /**
     * Author data (may contain name, email, and url)
     * @var string|array
     */
    protected $author_data;

    /**
     * The number of entries to display in "latest" listings
     * @var int
     */
    protected $latest_entries_limit = 5;

    /**
     * Instance of a blog entry provider to pull blog entries from
     * @var BlogEntryProvider
     */
    protected $entry_provider;

    /**
     * The domain where this blog resides
     * @var string|null
     */
    protected $domain;

    /**
     * The middleware for the routes of this blog
     * @var array|string|\Closure
     */
    protected $middleware;

    /**
     * Title of the blog
     * @var string
     */
    protected $title;

    /**
     * Stylesheets used for this blog
     * @var Collection
     */
    protected $stylesheet_urls;

    /**
     * Meta-title for html page head for this blog
     * @var string|null
     */
    protected $page_title;

    /**
     * Append this string to entries' page titles
     * @var string|null
     */
    protected $entry_page_title_suffix;

    /**
     * The timezone for this blog
     * @var \DateTimeZone
     */
    protected $timezone;

    /**
     * Array of array of attribute-value pairs for meta tags
     * @var array
     */
    protected $index_meta_tags = [];

    /**
     * Array of array of attribute-value pairs for meta tags
     * @var array
     */
    protected $default_meta_tags = [];

    /**
     * Default config for showing full entries in feed
     * @var bool
     */
    protected $full_entries_in_feed = false;

    /**
     * Main ability to authorize admin access to this blog
     * @var string
     */
    protected $main_ability = null;

    /**
     * Ability to authorize create access to this blog
     * @var string
     */
    protected $create_ability = null;

    /**
     * Ability to authorize edit access to this blog
     * @var string
     */
    protected $edit_ability = null;

    /**
     * Ability to authorize preview access to this blog
     * @var string
     */
    protected $preview_ability = null;

    /**
     * Blog constructor.
     *
     * @param string $blog_id
     * @param BlogEntryProvider $provider
     * @param iterable $configuration
     */
    public function __construct(string $blog_id, BlogEntryProvider $provider, iterable $configuration = [])
    {
        $this->id = $blog_id;
        $this->withPublicPath('blog/' . $blog_id);
        $this->withEntryProvider($provider);

        $this->configure($configuration);
    }

    /**
     * Set configuration values on the blog
     *
     * @param iterable $configuration
     * @return $this
     */
    public function configure(iterable $configuration): BlogContract
    {
        foreach ($configuration as $config_name => $config_value) {
            $method_name = 'with' . Str::studly($config_name);
            try {
                $reflection = new \ReflectionMethod($this, $method_name);
                if ($reflection->isPublic()) {
                    $this->$method_name($config_value);
                }
            } catch (\Exception $e) {
                trigger_error(
                    "Configuration problem for Blog '" . $this->getId()
                        . "', config key '${config_name}': " . $e->getMessage(),
                    E_USER_WARNING
                );
            }
        }

        return $this;
    }

    /**
     * Set the path part of the url to the blog
     *
     * @param string $path
     * @return $this
     */
    public function withPublicPath(string $path): BlogContract
    {
        $this->public_path = $path;

        return $this;
    }

    /**
     * Get the path part of the url to the blog
     *
     * @return string
     */
    public function getPublicPath(): string
    {
        return $this->public_path;
    }

    /**
     * Get the blog's identifier
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the entry provider instance
     *
     * @param BlogEntryProvider $provider
     * @return $this
     */
    public function withEntryProvider(BlogEntryProvider $provider): BlogContract
    {
        $provider->withBlogId($this->getId());
        $this->entry_provider = $provider;

        return $this;
    }

    /**
     * Get the blog's entry provider instance
     *
     * @return BlogEntryProvider
     */
    public function getEntryProvider(): BlogEntryProvider
    {
        return $this->entry_provider;
    }

    /**
     * Get the number of default entries to show
     *
     * @return int
     */
    public function getLatestEntriesLimit(): int
    {
        return $this->latest_entries_limit;
    }

    /**
     * Set the number of default entries to show
     *
     * @param int $limit
     * @return BlogContract
     */
    public function withLatestEntriesLimit(int $limit): BlogContract
    {
        $this->latest_entries_limit = $limit;

        return $this;
    }

    /**
     * Set a specific domain for this blog
     *
     * @param string $domain
     * @return BlogContract
     */
    public function withDomain(string $domain): BlogContract
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Get the domain for this blog
     *
     * @return string|null
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Set middleware for the blog's routes
     * @param array|string|\Closure $middleware
     * @return BlogContract
     */
    public function withMiddleware($middleware): BlogContract
    {
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * Get middleware to apply to the blog's routes
     * @return array|string|\Closure
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Get the title of the blog
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title ?? $this->getId();
    }

    /**
     * Set the title for the blog
     * @param string $title
     * @return $this
     */
    public function withTitle(string $title): BlogContract
    {
        $this->title = $title;

        return $this;
    }

    /**
     * The blog's authors
     * (should not be empty)
     * @return Collection
     */
    public function getAuthors(): Collection
    {
        $authors = collect();
        $authors->push(new Author($this->author_data ?: $this->getTitle()));

        return $authors;
    }

    /**
     * Set default author data on the blog
     * @param string|array $author_data
     * @return BlogContract
     */
    public function withAuthor($author_data): BlogContract
    {
        $this->author_data = $author_data;

        return $this;
    }

    /**
     * The blog's timezone
     * @return \DateTimeZone
     */
    public function getTimezone(): \DateTimeZone
    {
        if (!$this->timezone instanceof \DateTimeZone) {
            $this->timezone = new \DateTimeZone(date_default_timezone_get());
        }

        return $this->timezone;
    }

    /**
     * Move a Carbon time object into this blog's timezone
     * @param Carbon|null
     * @return Carbon|null
     */
    public function convertToBlogTimezone(?Carbon $time): ?Carbon
    {
        return $time ? $time->copy()->tz($this->getTimezone()) : null;
    }

    /**
     * Set timezone for this blog
     * @param string|\DateTimeZone $timezone
     * @return BlogContract
     */
    public function withTimezone($timezone): BlogContract
    {
        $this->timezone = new \DateTimeZone($timezone);

        return $this;
    }

    /**
     * Set stylesheets to use with this blog
     * @param string|array $styles relative or absolute urls
     * @return BlogContract
     */
    public function withStylesheets($styles): BlogContract
    {
        $urls = collect($styles)->map(function ($style) {
            return url($style);
        });

        $this->stylesheet_urls = $this->stylesheetUrls()->merge($urls);

        return $this;
    }

    /**
     * Get the stylesheets used for this blog
     * @return Collection
     */
    public function stylesheetUrls(): Collection
    {
        return collect($this->stylesheet_urls);
    }

    /**
     * Get an intro description for this blog
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return trim($this->getMetaTagBag()->content(['name' => 'description'])) ?: null;
    }

    /**
     * Set custom meta-tag attributes for the blog's index page
     * @param array $meta_tags
     * @return $this
     */
    public function withIndexMetaTags(array $meta_tags): BlogContract
    {
        $this->index_meta_tags += $meta_tags;

        return $this;
    }

    /**
     * Get any custom meta-tag attributes for this blog
     * @return MetaTagBag
     */
    public function getMetaTagBag(): MetaTagBag
    {
        return $this->getDefaultMetaTags()
            ->merge(
                ['property' => 'og:title', 'content' => $this->getTitle()],
                ['property' => 'og:type', 'content' => 'blog']
            )
            ->merge($this->index_meta_tags);
    }

    /**
     * Set custom meta-tag attributes for the blog's index page
     * @param array $meta_tags
     * @return $this
     */
    public function withDefaultMetaTags(array $meta_tags): BlogContract
    {
        $this->default_meta_tags += $meta_tags;

        return $this;
    }

    /**
     * Get default meta tags for any page under this blog
     * @return MetaTagBag
     */
    public function getDefaultMetaTags(): MetaTagBag
    {
        return MetaTagBag::make(
            ['name' => 'twitter:card', 'content' => 'summary']
        )
            ->merge($this->default_meta_tags);
    }

    /**
     * Set the html head title for the blog
     * @param string $title
     * @return $this
     */
    public function withPageTitle(string $title): BlogContract
    {
        $this->page_title = $title;

        return $this;
    }

    /**
     * Set the title suffix for blog entries
     * @param string $title
     * @return $this
     */
    public function withEntryPageTitleSuffix(string $title): BlogContract
    {
        $this->entry_page_title_suffix = $title;

        return $this;
    }

    /**
     * Get the html head page title of the blog
     * @return string
     */
    public function getPageTitle(): string
    {
        return $this->page_title ?? $this->getTitle();
    }

    /**
     * Get string to append after html page title on entry pages
     * @return string
     */
    public function getEntryPageTitleSuffix(): string
    {
        return is_null($this->entry_page_title_suffix)
            ? ' - ' . $this->getPageTitle()
            : $this->entry_page_title_suffix;
    }

    /**
     * Set if entry contents should be displayed in feed by default
     * @param string $title
     * @return $this
     */
    public function withFullEntriesInFeed(bool $config): BlogContract
    {
        $this->full_entries_in_feed = $config;

        return $this;
    }

    /**
     * Check if complete entry contents should be made available in feed
     * @param BlogEntry|null $entry
     * @return bool
     */
    public function displayFullEntryInFeed(BlogEntry $entry = null): bool
    {
        return $entry ? $entry->displayFullContentInFeed($this->full_entries_in_feed) : $this->full_entries_in_feed;
    }

    /**
     * Set the main ability to authorize admin access to this blog
     * @param string $ability
     * @return $this
     */
    public function withMainAbility(?string $ability): BlogContract
    {
        $this->main_ability = $ability;

        return $this;
    }

    /**
     * Get the main ability to authorize admin access to this blog
     * @return string|null
     */
    public function getMainAbility(): ?string
    {
        return $this->main_ability;
    }

    /**
     * Set the ability to authorize create access to this blog
     * @param string $ability
     * @return $this
     */
    public function withCreateAbility(?string $ability): BlogContract
    {
        $this->create_ability = $ability;

        return $this;
    }

    /**
     * Get the ability to authorize create entry in this blog
     * @return string|null
     */
    public function getCreateAbility(): ?string
    {
        return $this->create_ability ?? $this->getMainAbility();
    }

    /**
     * Set the ability to authorize edit access to this blog
     * @param string $ability
     * @return $this
     */
    public function withEditAbility(?string $ability): BlogContract
    {
        $this->edit_ability = $ability;

        return $this;
    }

    /**
     * Get the ability to authorize edit entry in this blog
     * @return string|null
     */
    public function getEditAbility(): ?string
    {
        return $this->edit_ability ?? $this->getCreateAbility();
    }

    /**
     * Set the ability to authorize preview access to this blog
     * @param string $ability
     * @return $this
     */
    public function withPreviewAbility(?string $ability): BlogContract
    {
        $this->preview_ability = $ability;

        return $this;
    }

    /**
     * Get the ability to authorize preview access to this blog
     * @return string
     */
    public function getPreviewAbility(): string
    {
        return $this->preview_ability ?? $this->getEditAbility() ?? 'preview';
    }
}
