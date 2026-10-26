@extends('layouts.guest')

@section('content')
    <h1>Design system</h1>
    <p class="lede">Mobile first. One accent. System fonts. The palette is original to WorxEvents: warm paper, near-black type, and a deep teal accent.</p>

    <h2>Color</h2>
    <ul class="swatches">
        <li><span class="swatch" style="background: var(--paper)"></span> Paper</li>
        <li><span class="swatch" style="background: var(--surface); border: 1px solid var(--line)"></span> Surface</li>
        <li><span class="swatch" style="background: var(--ink)"></span> Ink</li>
        <li><span class="swatch" style="background: var(--accent)"></span> Accent</li>
    </ul>

    <h2>Type</h2>
    <p>Body text uses the operating system font. Headings use the same family at a larger size and tighter measure.</p>

    <h2>Controls</h2>
    <p><button class="button" type="button">Primary action</button></p>
    <form class="stack">
        <label for="sample-email">Email</label>
        <input id="sample-email" name="sample-email" type="email" autocomplete="email" placeholder="you@example.com">
    </form>

    <h2>Motion</h2>
    <p>Transitions are short. When the visitor asks for reduced motion, transitions and smooth scrolling are removed.</p>
@endsection
