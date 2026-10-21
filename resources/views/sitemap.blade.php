{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc></url>
    <url><loc>{{ route('dictionary') }}</loc></url>
    <url><loc>{{ route('say.index') }}</loc></url>
@foreach ($culture ?? [] as $url)
    <url><loc>{{ $url }}</loc></url>
@endforeach
@if (isset($posts))
    <url><loc>{{ route('blog.index') }}</loc></url>
@foreach ($posts as $post)
    <url><loc>{{ route('blog.show', $post->slug) }}</loc>@if ($post->updated_at)<lastmod>{{ $post->updated_at->toDateString() }}</lastmod>@endif</url>
@endforeach
@endif
@foreach ($words as $word)
    <url><loc>{{ route('dictionary.word', $word->slug) }}</loc>@if ($word->updated_at)<lastmod>{{ $word->updated_at->toDateString() }}</lastmod>@endif</url>
@endforeach
@foreach ($phrases as $phrase)
    <url><loc>{{ route('say.show', $phrase->slug) }}</loc>@if ($phrase->updated_at)<lastmod>{{ $phrase->updated_at->toDateString() }}</lastmod>@endif</url>
@endforeach
</urlset>
