<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.plain')] #[Title('Welcome')] class extends Component {
    public function with(): array
    {
        return [
            'stats' => [
                ['title' => 'Total revenue', 'value' => '$38,393.12'],
                ['title' => 'Total transactions', 'value' => '428'],
                ['title' => 'Total customers', 'value' => '376'],
                ['title' => 'Average order value', 'value' => '$87.12'],
            ],
            'rows' => [
                ['id' => '1001', 'date' => 'Oct 24, 2024', 'status' => 'Paid', 'status_color' => 'green', 'customer' => 'Jane Doe', 'purchase' => 'Basic Plan', 'amount' => '$29.00'],
                ['id' => '1002', 'date' => 'Oct 25, 2024', 'status' => 'Pending', 'status_color' => 'orange', 'customer' => 'John Smith', 'purchase' => 'Pro Plan', 'amount' => '$59.00'],
                ['id' => '1003', 'date' => 'Oct 26, 2024', 'status' => 'Refunded', 'status_color' => 'red', 'customer' => 'Alice Johnson', 'purchase' => 'Basic Plan', 'amount' => '$29.00'],
            ],
        ];
    }
}; ?>

<section>
    <div class="flex gap-6 mb-6">
        <div class="flex flex-col md:flex-row gap-4 w-full">
            @foreach ($stats as $stat)
                <div wire:key="welcome-stat-{{ \Illuminate\Support\Str::slug($stat['title']) }}" class="relative flex-1 rounded-lg px-6 py-4 bg-zinc-50 dark:bg-zinc-700">
                    <flux:subheading>{{ $stat['title'] }}</flux:subheading>
                    <flux:heading size="xl" class="mb-2">{{ $stat['value'] }}</flux:heading>
                </div>
            @endforeach
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column></flux:table.column>
            <flux:table.column class="max-md:hidden">ID</flux:table.column>
            <flux:table.column class="max-md:hidden">Date</flux:table.column>
            <flux:table.column class="max-md:hidden">Status</flux:table.column>
            <flux:table.column><span class="max-md:hidden">Customer</span><div class="md:hidden w-6"></div></flux:table.column>
            <flux:table.column>Purchase</flux:table.column>
            <flux:table.column>Revenue</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($rows as $row)
                <flux:table.row :key="'welcome-row-'.$row['id']">
                    <flux:table.cell class="pr-2"><flux:checkbox /></flux:table.cell>
                    <flux:table.cell class="max-md:hidden">#{{ $row['id'] }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $row['date'] }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden"><flux:badge :color="$row['status_color']" size="sm" inset="top bottom">{{ $row['status'] }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="min-w-6">
                        <div class="flex items-center gap-2">
                            <flux:avatar src="https://i.pravatar.cc/48?img={{ $loop->index }}" size="xs" />
                            <span class="max-md:hidden">{{ $row['customer'] }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-6 truncate">{{ $row['purchase'] }}</flux:table.cell>
                    <flux:table.cell variant="strong">{{ $row['amount'] }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown position="bottom" align="end" offset="-15">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                            <flux:menu>
                                <flux:menu.item icon="document-text">View invoice</flux:menu.item>
                                <flux:menu.item icon="receipt-refund">Refund</flux:menu.item>
                                <flux:menu.item icon="archive-box" variant="danger">Archive</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
