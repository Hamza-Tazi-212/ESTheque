@props(['book'])

@if($book->open_library_cover)
    <span class="catalogue-book-thumbnail relative block h-[4.6rem] w-[3.15rem] shrink-0">
        <x-book-spine :title="$book->title" />
        <img src="{{ $book->coverUrl('M') }}" alt="" width="50" height="74" loading="lazy" decoding="async"
             referrerpolicy="no-referrer" onerror="this.hidden = true"
             class="absolute inset-0 size-full rounded-sm bg-white object-contain">
    </span>
@else
    <x-book-spine :title="$book->title" />
@endif
