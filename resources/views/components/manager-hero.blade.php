@props(['titleId', 'title', 'emphasis', 'description', 'consultedAt' => null])
<style>
    .manager-hero { background:#e8f4eb; color:#104f3d; }
    .manager-hero-grid { max-width:85rem; margin:auto; padding:clamp(2rem,6vw,6rem) clamp(1.25rem,4vw,4rem); display:grid; align-items:center; gap:clamp(2rem,6vw,6rem); }
    .manager-hero h1 { font:500 clamp(2.5rem,4.5vw,4.4rem)/1.04 Georgia,serif; letter-spacing:-.035em; }
    .manager-hero h1 em { display:block; color:#34775f; }
    .manager-hero p { max-width:38rem; margin-top:1rem; color:#425d50; line-height:1.65; }
    .manager-hero img { width:100%; height:auto; justify-self:center; }
    @media(min-width:800px) { .manager-hero-grid { grid-template-columns:minmax(0,1fr) minmax(0,1.2fr); } .manager-hero img { justify-self:end; } }
</style>
<section class="manager-hero" aria-labelledby="{{ $titleId }}">
    <div class="manager-hero-grid">
        <div>
            <h1 id="{{ $titleId }}">{{ $title }}<em>{{ $emphasis }}</em></h1>
            <p>{{ $description }}</p>
            @if($consultedAt)
                <p style="margin-top:.4rem; font-size:.8rem;">Consulté le {{ $consultedAt }}.</p>
            @endif
        </div>
        <img src="{{ asset('estheque-hero-books.png') }}" alt="" width="1774" height="887">
    </div>
    <svg aria-hidden="true" viewBox="0 0 1440 72" preserveAspectRatio="none" style="display:block;width:100%;height:clamp(1.5rem,5vw,4.5rem);">
        <path d="M0 45C280 98 520 63 755 28S1160-10 1440 25V72H0Z" fill="#f8fafc"/>
        <path d="M0 45C280 98 520 63 755 28S1160-10 1440 25" fill="none" stroke="#bfd9c7" stroke-width="1.6" opacity=".8" vector-effect="non-scaling-stroke"/>
    </svg>
</section>