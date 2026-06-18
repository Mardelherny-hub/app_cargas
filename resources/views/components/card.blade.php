@props(['title' => null])

<div {{ $attributes->merge(['class' => 'bg-white shadow rounded-lg p-6']) }}>
    @isset($title)
        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $title }}</h3>
    @endisset

    {{ $slot }}
</div>
