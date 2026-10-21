@php
    $tz = config('luganda.timezone');
    $status = old('status', $post->status() === 'published' ? 'publish' : ($post->status() === 'scheduled' ? 'schedule' : 'draft'));
    $publishAt = old('publish_at', $post->published_at?->timezone($tz)->format('Y-m-d\TH:i') ?? now($tz)->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'));
@endphp
<x-admin :title="$post->exists ? 'Edit post' : 'New post'">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-bold">{{ $post->exists ? 'Edit post' : 'New post' }}</h1>
        @if ($post->exists)
            <span @class(['badge', 'bg-leaf-100 text-leaf-600' => $post->status() === 'published', 'bg-gold-300/50 text-bark-800' => $post->status() !== 'published'])>{{ ucfirst($post->status()) }}</span>
            <a href="{{ route('blog.show', $post) }}" class="text-sm font-semibold text-bark-600 underline" target="_blank" rel="noopener">{{ $post->isPublished() ? 'View' : 'Preview' }} the page ↗</a>
        @endif
    </div>

    <form method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('admin.blog.update', $post->id) : route('admin.blog.store') }}" class="mt-6 grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($post->exists) @method('PUT') @endif

        <div class="space-y-4 lg:col-span-2">
            <x-input name="title" label="Title" :value="$post->title" required maxlength="160" />

            <div x-data="blogEditor(@js(['preview' => route('admin.blog.preview'), 'image' => route('admin.blog.image')]))" class="card !p-0">
                <div class="flex flex-wrap items-center gap-1 border-b-2 border-bark-100 px-3 py-2" role="toolbar" aria-label="Formatting">
                    <button type="button" class="rounded-lg px-2.5 py-1 text-sm font-semibold" :class="tab === 'write' ? 'bg-bark-600 text-white' : 'hover:bg-bark-50'" @click="tab = 'write'" :aria-pressed="(tab === 'write').toString()">Write</button>
                    <button type="button" class="rounded-lg px-2.5 py-1 text-sm font-semibold" :class="tab === 'preview' ? 'bg-bark-600 text-white' : 'hover:bg-bark-50'" @click="showPreview()" :aria-pressed="(tab === 'preview').toString()">Preview</button>
                    <span class="mx-1 h-5 w-px bg-bark-100" aria-hidden="true"></span>
                    <template x-if="tab === 'write'">
                        <span class="flex flex-wrap gap-1">
                            <button type="button" class="ed-btn" @click="lines('## ')" title="Heading">H2</button>
                            <button type="button" class="ed-btn" @click="lines('### ')" title="Smaller heading">H3</button>
                            <button type="button" class="ed-btn font-bold" @click="wrap('**')" title="Bold">B</button>
                            <button type="button" class="ed-btn italic" @click="wrap('*')" title="Italic">I</button>
                            <button type="button" class="ed-btn" @click="lines('- ')" title="List">• List</button>
                            <button type="button" class="ed-btn" @click="lines('> ')" title="Quote">“ Quote</button>
                            <button type="button" class="ed-btn" @click="link()" title="Link">🔗 Link</button>
                            <button type="button" class="ed-btn text-leaf-600" @click="wrap('[[', ']]', 'Oli otya?')" title="Luganda readers can tap">[[Luganda]]</button>
                            <label class="ed-btn cursor-pointer" :class="uploading && 'opacity-60'">🖼️ <span x-text="uploading ? 'Uploading…' : 'Picture'"></span>
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="upload($event)" :disabled="uploading"></label>
                        </span>
                    </template>
                </div>
                <div x-show="tab === 'write'">
                    <label for="body" class="sr-only">Post (Markdown)</label>
                    <textarea id="body" name="body" x-ref="body" @input="count()" rows="22" required
                              class="block w-full resize-y rounded-b-3xl border-0 bg-white px-4 py-3 font-mono text-[15px] leading-relaxed focus:outline-none focus:ring-2 focus:ring-gold-300">{{ old('body', $post->body) }}</textarea>
                </div>
                <div x-show="tab === 'preview'" x-cloak class="px-5 py-4">
                    <p x-show="loading" class="text-bark-500">Loading the preview…</p>
                    <div x-show="!loading" class="blog-prose" x-html="preview"></div>
                </div>
                <div class="flex flex-wrap justify-between gap-2 border-t-2 border-bark-100 px-4 py-2 text-xs text-bark-500">
                    <span><span x-text="words"></span> words · <span x-text="minutes"></span> min read</span>
                    <span x-show="error" x-text="error" class="font-semibold text-red-600" role="alert"></span>
                </div>
            </div>
            @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <details class="rounded-2xl bg-white px-4 py-3 text-sm text-bark-800">
                <summary class="cursor-pointer font-semibold">How to write a post</summary>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li><code>## Heading</code> for a section, <code>**bold**</code>, <code>*italic*</code>, <code>- </code> for a list, <code>&gt; </code> for a quote, <code>[text](https://…)</code> for a link.</li>
                    <li>Put Luganda in double brackets, like <code>[[Webale nnyo]]</code>: readers can tap each word for its meaning and recording. Words not in the dictionary yet say so; nothing is guessed.</li>
                    <li>Pictures: use the 🖼️ button (JPG, PNG or WebP, up to 4 MB). Change the words in <code>![…]</code> to describe the picture for people who can't see it.</li>
                    <li>HTML is shown as plain text, so nothing can break the page.</li>
                </ul>
            </details>
        </div>

        <div class="space-y-4">
            <fieldset class="card space-y-3 !p-4">
                <legend class="sr-only">Publishing</legend>
                <p class="font-display text-lg font-bold">Publishing</p>
                @foreach (['draft' => 'Draft (only admins can see it)', 'publish' => 'Published', 'schedule' => 'Schedule for later'] as $value => $label)
                    <label class="flex items-center gap-2"><input type="radio" name="status" value="{{ $value }}" @checked($status === $value)> {{ $label }}</label>
                @endforeach
                <div>
                    <label for="publish_at" class="field-label">Date and time (Kampala time)</label>
                    <input id="publish_at" name="publish_at" type="datetime-local" value="{{ $publishAt }}" class="field">
                    <p class="mt-1 text-xs text-bark-500">Used when “Schedule for later” is chosen.</p>
                    @error('publish_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary w-full">Save</button>
            </fieldset>

            <div class="card space-y-3 !p-4">
                <div>
                    <label for="topic" class="field-label">Topic</label>
                    <select id="topic" name="topic" class="field">
                        @foreach (\App\Models\Post::TOPICS as $key => $label)<option value="{{ $key }}" @selected(old('topic', $post->topic) === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <x-input name="excerpt" label="Summary" type="textarea" :value="$post->excerpt" maxlength="300" help="One or two sentences for the post list and for search engines. Leave empty to use the start of the post." />
                <x-input name="slug" label="Web address" :value="$post->slug" maxlength="80" help="/blog/… Leave empty to make it from the title. Changing it after publishing breaks links people have shared." />
            </div>

            <div class="card space-y-3 !p-4">
                <p class="font-display text-lg font-bold">Cover picture</p>
                @if ($post->coverUrl())
                    <img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt }}" class="aspect-[16/9] w-full rounded-2xl object-cover">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_cover" value="1"> Remove the cover</label>
                @endif
                <div>
                    <label for="cover" class="field-label">{{ $post->coverUrl() ? 'Replace it' : 'Choose a picture' }}</label>
                    <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
                    <p class="mt-1 text-xs text-bark-500">JPG, PNG or WebP, up to 4 MB. Wide pictures (16:9) look best.</p>
                    @error('cover')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <x-input name="cover_alt" label="Describe the picture" :value="$post->cover_alt" maxlength="160" help="For people who can't see it, e.g. “A market stall with tomatoes”." />
            </div>
        </div>
    </form>

    @if ($post->exists)
        <div class="mt-6"><x-delete-button :action="route('admin.blog.destroy', $post->id)" /></div>
    @endif
</x-admin>
