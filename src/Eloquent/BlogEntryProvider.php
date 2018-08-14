<?php

namespace Bjuppa\LaravelBlog\Eloquent;

use Bjuppa\LaravelBlog\Contracts\BlogEntry;
use Bjuppa\LaravelBlog\Contracts\BlogEntryProvider as BlogEntryProviderContract;
use Bjuppa\LaravelBlog\Contracts\ProvidesDatabaseMigrationsPath;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BlogEntryProvider implements BlogEntryProviderContract, ProvidesDatabaseMigrationsPath
{
    protected $model = \Bjuppa\LaravelBlog\Eloquent\BlogEntry::class;

    /**
     * Blog id for the current blog
     * @var string
     */
    protected $blog_id;

    /**
     * Set the id of the blog to use
     * @param string $blog_id
     * @return $this
     */
    public function withBlogId(string $blog_id): BlogEntryProviderContract
    {
        $this->blog_id = $blog_id;

        return $this;
    }

    /**
     * Get an instance of the Eloquent model used
     * @return Model
     * TODO: Have getBlogModel() return instance of EloquentBlogEntry contract
     */
    protected function getBlogModel(): Model
    {
        return new $this->model;
    }

    /**
     * Get a prepared query builder for the blog
     * @return Builder
     */
    protected function getBuilder(): Builder
    {
        return $this->getBlogModel()->blog($this->blog_id);
    }

    /**
     * Get a blog entry from a slug
     * @param string $slug
     * @return BlogEntry|null
     */
    public function findBySlug($slug): ?BlogEntry
    {
        return $this->getBuilder()->slug($slug)->first();
    }

    /**
     * Get the next entry within this blog
     * @param BlogEntry|null $entry
     * @return BlogEntry|null
     */
    public function nextEntry(BlogEntry $entry): ?BlogEntry
    {
        return $this->getBuilder()->publishedAfter($entry)->first();
    }

    /**
     * Get the previous entry within this blog
     * @param BlogEntry|null $entry
     * @return BlogEntry|null
     */
    public function previousEntry(BlogEntry $entry): ?BlogEntry
    {
        return $this->getBuilder()->publishedBefore($entry)->first();
    }

    /**
     * Get the newest entries of the blog
     * @param int $limit
     * @return Collection
     */
    public function latest($limit = 5): Collection
    {
        return $this->getBuilder()->limit($limit)->get();
    }

    /**
     * Get the last updated timestamp for the entire blog
     * @return Carbon
     */
    public function getUpdated(): Carbon
    {
        return new Carbon($this->getBuilder()->max($this->getBlogModel()::CREATED_AT));
    }

    /**
     * Return the path to database migrations for models.
     * @return string
     */
    public function getDatabaseMigrationsPath(): string
    {
        return __DIR__ . '/../../database/migrations/';
    }
}
