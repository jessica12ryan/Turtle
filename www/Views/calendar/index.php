<h1 class="text-2xl font-bold text-gray-800 mb-6">Calendar</h1>

<div class="bg-white rounded-lg shadow p-6" x-data="calendar()">
    <div class="flex items-center justify-between mb-6">
        <button @click="prevMonth()" class="p-2 rounded-lg hover:bg-gray-100">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h2 class="text-lg font-semibold text-gray-800" x-text="monthYear"></h2>
        <button @click="nextMonth()" class="p-2 rounded-lg hover:bg-gray-100">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    <div class="grid grid-cols-7 gap-px bg-gray-200 rounded-lg overflow-hidden">
        <template x-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
            <div class="bg-gray-50 px-3 py-2 text-xs font-medium text-gray-500 text-center uppercase" x-text="day"></div>
        </template>
        <template x-for="(cell, idx) in grid" :key="idx">
            <div class="bg-white min-h-[80px] px-2 py-1 text-sm"
                 :class="{
                     'bg-blue-50': cell.today,
                     'text-gray-400': !cell.currentMonth,
                     'cursor-pointer hover:bg-gray-50': cell.currentMonth && cell.events.length
                 }"
                 @click="cell.currentMonth && cell.events.length && (selectedDate = cell.date)">
                <div class="font-medium mb-1" x-text="cell.day"></div>
                <template x-for="ev in cell.events.slice(0, 2)" :key="ev.id">
                    <div class="text-xs px-1 py-0.5 rounded mb-0.5 truncate"
                         :class="ev.className"
                         x-text="ev.title"></div>
                </template>
                <div x-show="cell.events.length > 2" class="text-xs text-blue-600 font-medium">+<span x-text="cell.events.length - 2"></span> more</div>
            </div>
        </template>
    </div>

    <!-- Event details panel -->
    <div x-show="selectedDate" class="mt-6 p-4 bg-gray-50 rounded-lg border">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold text-gray-800" x-text="'Events for ' + selectedDate"></h3>
            <button @click="selectedDate = null" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <template x-for="ev in selectedEvents" :key="ev.id">
            <div class="flex items-center space-x-2 mb-2">
                <span class="w-3 h-3 rounded-full inline-block" :class="ev.className.split(' ')[0]"></span>
                <span class="text-sm text-gray-700" x-text="ev.title"></span>
            </div>
        </template>
        <p x-show="!selectedEvents.length" class="text-sm text-gray-500">No events on this day.</p>
    </div>
</div>

<div class="mt-4 flex items-center space-x-6 text-sm text-gray-600">
    <div class="flex items-center space-x-1"><span class="w-3 h-3 rounded-full bg-green-100 border border-green-300 inline-block"></span><span>Move In</span></div>
    <div class="flex items-center space-x-1"><span class="w-3 h-3 rounded-full bg-red-100 border border-red-300 inline-block"></span><span>Move Out</span></div>
    <div class="flex items-center space-x-1"><span class="w-3 h-3 rounded-full bg-yellow-100 border border-yellow-300 inline-block"></span><span>Lease Ends</span></div>
</div>

<script>
function calendar() {
    return {
        events: [],
        currentDate: new Date(),
        selectedDate: null,

        get monthYear() {
            return this.currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },

        get grid() {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startPad = firstDay.getDay();
            const daysInMonth = lastDay.getDate();
            const today = new Date();
            const todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

            const cells = [];
            for (let i = 0; i < startPad; i++) {
                const d = new Date(year, month, -startPad + i + 1);
                cells.push(this.makeCell(d, false));
            }
            for (let d = 1; d <= daysInMonth; d++) {
                const date = new Date(year, month, d);
                cells.push(this.makeCell(date, true, date.toISOString().slice(0, 10) === todayStr));
            }
            const remaining = 42 - cells.length;
            for (let i = 1; i <= remaining; i++) {
                const d = new Date(year, month + 1, i);
                cells.push(this.makeCell(d, false));
            }
            return cells;
        },

        makeCell(date, currentMonth, today) {
            const dateStr = date.toISOString().slice(0, 10);
            const day = date.getDate();
            const events = this.events.filter(e => e.start === dateStr);

            return {
                date: dateStr,
                day,
                currentMonth,
                today: !!today,
                events,
            };
        },

        get selectedEvents() {
            if (!this.selectedDate) return [];
            return this.events.filter(e => e.start === this.selectedDate);
        },

        prevMonth() {
            this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1);
        },

        nextMonth() {
            this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1);
        },

        async init() {
            try {
                const resp = await fetch('/calendar/events');
                this.events = await resp.json();
            } catch (e) {
                console.error('Failed to load calendar events:', e);
            }
        }
    };
}
</script>
