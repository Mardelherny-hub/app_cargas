@php($gradientId = 'app-mark-' . \Illuminate\Support\Str::random(6))
<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ config('app.name') }}" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="48" y2="48" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#2563EB"/>
            <stop offset="0.5" stop-color="#1D4ED8"/>
            <stop offset="1" stop-color="#0D9488"/>
        </linearGradient>
    </defs>
    <circle cx="24" cy="24" r="24" fill="url(#{{ $gradientId }})"/>
    <path transform="translate(9 9) scale(1.25)" d="M3 17h18l-2-4H5l-2 4zM12 2L8 6h8l-4-4zm-9 7h18v2H3v-2z" fill="#FFFFFF"/>
</svg>
