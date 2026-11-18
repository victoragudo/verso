{{-- @font-face rules for the fonts in the theme folder, printed inside the main style tag.
     Browsers without font-size-adjust get local fallback fonts sized like the theme fonts, so text does not jump when they load. --}}
@if ($newsreaderRoman)
@font-face { font-family: Newsreader; src: url("{{ $newsreaderRoman }}") format("woff2"); font-weight: 200 800; font-style: normal; font-display: swap; }
@endif
@if ($newsreaderItalic)
@font-face { font-family: Newsreader; src: url("{{ $newsreaderItalic }}") format("woff2"); font-weight: 200 800; font-style: italic; font-display: swap; }
@endif
@if ($instrumentSans)
@font-face { font-family: "Instrument Sans"; src: url("{{ $instrumentSans }}") format("woff2"); font-weight: 400 700; font-style: normal; font-display: swap; }
@endif
@supports not (font-size-adjust: from-font) {
@font-face { font-family: "Newsreader Fallback"; src: local("Georgia"), local("Gelasio"); size-adjust: 96.84%; ascent-override: 75.9%; descent-override: 27.36%; line-gap-override: 0%; font-display: swap; }
@font-face { font-family: "Instrument Sans Fallback"; src: local("Arial"), local("Liberation Sans"), local("Arimo"); size-adjust: 103.15%; ascent-override: 94.04%; descent-override: 24.24%; line-gap-override: 0%; font-display: swap; }
}
