@props(['people', 'name' => 'people[]', 'selected' => [], 'multiple' => true, 'placeholder' => 'Type a name…'])
@php
    $options = collect($people)->map(fn ($p) => ['id' => $p->id, 'label' => trim($p->given_name.' '.$p->surname).($p->birth_date ? ' ('.substr($p->birth_date, 0, 4).')' : '')])->values();
@endphp
<div x-data="peoplePicker(@js($options), @js(array_values(array_map('intval', (array) $selected))), @js($name), @js($multiple))" class="relative">
    <template x-for="id in selected" :key="id"><input type="hidden" :name="name" :value="id"></template>
    <div class="flex flex-wrap gap-1.5" x-show="selected.length" x-cloak>
        <template x-for="id in selected" :key="id">
            <span class="badge !bg-accent-soft !text-accent">
                <span x-text="label(id)"></span>
                <button type="button" @click="remove(id)" class="ml-0.5" aria-label="Remove">×</button>
            </span>
        </template>
    </div>
    <input type="text" x-model="q" @focus="open = true" @click.outside="open = false" @keydown.escape="open = false"
           x-show="multiple || !selected.length" placeholder="{{ $placeholder }}" class="input mt-1.5">
    <div x-show="open && matches.length" x-cloak class="card absolute z-30 mt-1 max-h-64 w-full overflow-y-auto p-1">
        <template x-for="o in matches" :key="o.id">
            <button type="button" @click="add(o.id)" class="block w-full rounded-md px-2 py-1.5 text-left text-sm hover:bg-paper-2 dark:hover:bg-white/10" x-text="o.label"></button>
        </template>
    </div>
</div>
