{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('app.name') }} blog</title>
        <link>{{ route('blog.index') }}</link>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
        <description>Tips for learning Luganda, Ganda culture, and news from the app.</description>
        <language>en</language>
@foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('blog.show', $post) }}</link>
            <guid isPermaLink="true">{{ route('blog.show', $post) }}</guid>
            <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
            <category>{{ $post->topicLabel() }}</category>
            <description>{{ $post->summary() }}</description>
        </item>
@endforeach
    </channel>
</rss>
