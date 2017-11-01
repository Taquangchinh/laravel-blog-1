<?php
/**
 * @var $blog \Bjuppa\LaravelBlog\Contracts\Blog
 * @var $entry \Bjuppa\LaravelBlog\Contracts\BlogEntry
 */
?>
{{-- TODO: add title="Latest entries" to the link list --}}
<ul>
  @foreach($blog->latestEntries() as $entry)
    <li>
      <a href="{{ $blog->urlToEntry($entry) }}">{{ $entry->getTitle() }}</a>
    </li>
  @endforeach
</ul>
