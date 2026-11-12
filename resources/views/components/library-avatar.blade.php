@if(session('google_avatar'))
    <img src="{{ session('google_avatar') }}" alt="" width="44" height="44" referrerpolicy="no-referrer" style="width: 44px; height: 44px; flex-shrink: 0; border-radius: 50%; object-fit: cover;">
@else
    <span aria-hidden="true" style="display: inline-grid; place-items: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: 50%; background: #d1fae5; color: #064e3b;">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
@endif
