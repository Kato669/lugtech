{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc></url>
    <url><loc>{{ route('dictionary') }}</loc></url>
    <url><loc>{{ route('say.index') }}</loc></url>
@foreach ($words as $word)
    <url><loc>{{ route('dictionary.word', $word->slug) }}</loc>@if ($word->updated_at)<lastmod>{{ $word->updated_at->toDateString() }}</lastmod>@endif</url>
@endforeach
@foreach ($phrases as $phrase)
    <url><loc>{{ route('say.show', $phrase->slug) }}</loc>@if ($phrase->updated_at)<lastmod>{{ $phrase->updated_at->toDateString() }}</lastmod>@endif</url>
@endforeach
</urlset>
