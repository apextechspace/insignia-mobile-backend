<x-filament-widgets::widget>
    <div class="space-y-6">
        <div class="rounded-2xl p-6 text-white" style="background:linear-gradient(115deg,#34091f,#7d2753)">
            <p class="text-sm">Insignia Court · Auxiliary</p>
            <h2 class="mt-2 text-2xl font-semibold">Guest orders, ready for your attention</h2>
            <p class="mt-2 text-sm">{{ now(config('property.timezone'))->format('l, j F · g:i A T') }}</p>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($queues as [$title, $description, $count, $url, $icon])
                <a href="{{ $url }}" class="block rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                    @svg($icon, 'h-7 w-7 text-primary-600')
                    <h3 class="mt-4 text-lg font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-gray-500">{{ $description }}</p>
                    <p class="mt-5 text-3xl font-semibold">{{ $count }}</p>
                    <p class="text-sm text-gray-500">Awaiting completion</p>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
