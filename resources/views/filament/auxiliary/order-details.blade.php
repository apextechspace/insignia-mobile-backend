<div class="space-y-4">
    <p class="font-semibold">Order #{{ $record->id }} · {{ $record->user?->name }} · {{ str($record->status)->headline() }}</p>
    <p>Guest contact: {{ $record->user?->phone ?: $record->user?->email ?: 'Confirm with reception' }}</p>
    @if ($record instanceof \App\Models\FoodOrder)
        <p>{{ str($record->type)->headline() }}</p>
        <ul class="space-y-2">
            @foreach ($record->items()->with('menuItem')->get() as $item)
                <li>{{ $item->quantity }} × {{ $item->menuItem?->name ?? 'Menu item' }}</li>
            @endforeach
        </ul>
    @elseif ($record instanceof \App\Models\Order)
        <p>{{ $record->quantity }} × {{ $record->product?->name }}</p>
        <p>Delivery: {{ $record->delivery_location ?: 'Confirm with guest' }}</p>
    @else
        <p>{{ $record->service?->name }} · {{ $record->variant?->name }}</p>
        <p>Scheduled: {{ $record->scheduled_at?->timezone(config('property.timezone'))->format('j M Y, g:i A') ?? $record->date }} {{ $record->time }}</p>
    @endif
    @if ($record->notes)
        <p class="whitespace-pre-line">{{ $record->notes }}</p>
    @endif
    @foreach (($record->metadata ?? []) as $key => $value)
        <p>{{ str($key)->headline() }}: {{ is_scalar($value) ? $value : json_encode($value) }}</p>
    @endforeach
</div>
