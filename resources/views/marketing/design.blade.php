@extends('layouts.guest')

@section('content')
    <h1>Design system</h1>
    <p class="lede">WorxEvents uses the BrandWorx staff style shared with ReserveWorx: Inter, a grey page, white cards, brand green actions, and an amber highlight.</p>

    <h2>Color</h2>
    <ul class="swatches">
        <li><span class="swatch" style="background: var(--page)"></span> Page</li>
        <li><span class="swatch" style="background: var(--surface); border: 1px solid var(--line)"></span> Surface</li>
        <li><span class="swatch" style="background: var(--ink)"></span> Ink</li>
        <li><span class="swatch" style="background: var(--brand)"></span> Brand</li>
        <li><span class="swatch" style="background: var(--accent)"></span> Amber</li>
    </ul>

    <h2>Type</h2>
    <p>Interface text uses Inter, with the system font stack when that file is unavailable. Secondary text is darker than the shared grey so it stays readable on the page background.</p>

    <h2>Controls</h2>
    <p><button class="button" type="button">Primary action</button></p>
    <form class="stack panel">
        <label for="sample-email">Email</label>
        <input id="sample-email" name="sample-email" type="email" autocomplete="email" placeholder="you@example.com">
    </form>

    <h2>Motion</h2>
    <p>Transitions are short. When the visitor asks for reduced motion, transitions and animations are removed.</p>
@endsection
