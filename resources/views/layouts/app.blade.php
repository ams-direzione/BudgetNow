<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'BudgetNow')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    {{-- Persistenza preferenze tabella (sort, dir, per_page) via localStorage --}}
    <script>
    (function () {
        var KEY = 'bn_prefs_' + location.pathname;
        var P   = new URLSearchParams(location.search);
        var T   = ['sort', 'dir', 'per_page'];
        if (T.some(function(k){ return P.has(k); })) {
            // Almeno un parametro è in URL → salva in localStorage
            var s = {};
            T.forEach(function(k){ if (P.has(k)) s[k] = P.get(k); });
            try { localStorage.setItem(KEY, JSON.stringify(s)); } catch(e) {}
        } else {
            // Nessun parametro → applica quelli salvati (una sola redirect)
            try {
                var s = JSON.parse(localStorage.getItem(KEY) || '{}');
                var has = false;
                T.forEach(function(k){ if (s[k]) { P.set(k, s[k]); has = true; } });
                if (has) {
                    // Nascondi la pagina intermedia prima del redirect per evitare il flash bianco
                    document.documentElement.style.visibility = 'hidden';
                    location.replace(location.pathname + '?' + P.toString());
                }
            } catch(e) {}
        }
    })();
</script>
    <style>
        .table-standard > tbody > tr:nth-child(odd) {
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800" x-data="{ sidebarOpen: false }">
<div class="min-h-screen lg:flex">
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-40 w-72 transform bg-slate-900 text-slate-200 transition-transform duration-200 lg:static lg:inset-auto lg:translate-x-0 overflow-y-auto"
    >
        <div class="flex h-16 items-center border-b border-slate-700 px-6">
            <img src="{{ asset('images/logo/logo.svg') }}" alt="BudgetNow" class="h-8">
            <span class="ml-3 font-semibold tracking-wide">BudgetNow</span>
        </div>

        <nav class="p-4">
            <a href="{{ route('home', isset($selectedYear) ? ['year' => $selectedYear] : []) }}"
               class="block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('home') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                Home
            </a>
            <a href="{{ route('journal.index', isset($selectedYear) ? ['year' => $selectedYear] : []) }}"
               class="mt-1 block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('journal.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                Libro Giornale
            </a>

            <p class="mt-6 mb-2 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Anagrafiche</p>

            <a href="{{ route('tipi.index') }}"
               class="block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('tipi.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                Tipi di Movimento
            </a>

            {{-- Categorie: una sottovoce per ogni tipo di movimento --}}
            <div class="mt-1">
                <p class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Categorie</p>
                @foreach($navEntryTypes ?? [] as $navType)
                    @php $isActiveCategoria = request()->routeIs('categorie.*') && request()->route('tipo')?->id === $navType->id; @endphp
                    <a href="{{ route('categorie.index', $navType) }}"
                       class="ml-2 block rounded-lg px-4 py-2 text-sm font-medium {{ $isActiveCategoria ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                        {{ $navType->name }}
                    </a>
                @endforeach
            </div>

            <a href="{{ route('conti-riferimento.index') }}"
               class="mt-1 block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('conti-riferimento.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                Conti di Riferimento
            </a>

            <a href="{{ route('budget.index') }}"
               class="mt-1 block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('budget.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                Budget
            </a>
        </nav>
    </aside>

    <div class="flex min-h-screen flex-1 flex-col">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
            <div class="flex h-16 items-center justify-between px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = !sidebarOpen" class="rounded-md border border-slate-200 p-2 text-slate-600 lg:hidden">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h8a1 1 0 010 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 010 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <h1 class="text-lg font-semibold">@yield('page-title')</h1>
                </div>

                <div class="flex items-center gap-2">
                    @if(!empty($availableBudgets) && isset($activeBudget))
                        <form action="{{ route('budget.switch', $activeBudget) }}" method="POST" id="switchBudgetForm">
                            @csrf
                            <label for="budget_switch" class="sr-only">Budget</label>
                            <select id="budget_switch"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                    onchange="document.getElementById('switchBudgetTarget').value=this.value; document.getElementById('switchBudgetForm').action='/budget/'+this.value+'/switch'; document.getElementById('switchBudgetForm').submit();">
                                @foreach($availableBudgets as $budget)
                                    <option value="{{ $budget->id }}" @selected($activeBudget->id === $budget->id)>{{ $budget->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" id="switchBudgetTarget" value="{{ $activeBudget->id }}">
                        </form>
                    @endif

                    @if(isset($yearRoute) && isset($availableYears))
                        <form action="{{ $yearRoute }}" method="GET" class="flex items-center gap-2">
                            <label for="year" class="text-sm text-slate-500">Anno</label>
                            <select id="year" name="year" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
                                @foreach($availableYears as $year)
                                    <option value="{{ $year }}" @selected($year === $selectedYear)>{{ $year }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>
            </div>
            @hasSection('header-filters')
                <div class="border-t border-slate-100 bg-slate-50/60 px-4 sm:px-6 py-2.5 flex flex-wrap items-center gap-2">
                    @yield('header-filters')
                </div>
            @endif
        </header>

        <main class="flex-1 p-4 sm:p-6">
            @if(session('success'))
                <div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 border border-emerald-200">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700 border border-rose-200">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

{{-- Loader globale: si attiva su submit form e click link interno --}}
<div id="page-loader"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(255,255,255,.55);backdrop-filter:blur(2px);align-items:center;justify-content:center;">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
    </svg>
</div>
<script>
(function () {
    var loader = document.getElementById('page-loader');
    function show() { loader.style.display = 'flex'; }
    document.addEventListener('submit', function (e) {
        // Non mostrare il loader per form che fanno fetch (Alpine inline edit)
        if (e.target.dataset.noLoader) return;
        show();
    });
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a) return;
        var href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript') || href.startsWith('mailto')) return;
        if (a.target === '_blank') return;
        show();
    });
    // Nascondi se si torna con il tasto indietro
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { loader.style.display = 'none'; }
    });
})();
</script>

{{-- Funzione Alpine.js globale per righe editabili (usata da tutte le tabelle) --}}
<script>
function editableRow(data, updateUrl, deleteUrl) {
    return {
        editing:  false,
        saving:   false,
        errors:   {},
        form:     {},
        orig:     {},

        init() {
            this.form = Object.assign({}, data);
            this.orig = Object.assign({}, data);
        },

        startEdit() {
            this.errors  = {};
            this.form    = Object.assign({}, this.orig);
            this.editing = true;
        },

        cancelEdit() {
            this.form    = Object.assign({}, this.orig);
            this.errors  = {};
            this.editing = false;
        },

        async save() {
            this.saving = true;
            this.errors = {};
            try {
                const resp = await fetch(updateUrl, {
                    method:  'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.form),
                });
                const json = await resp.json();
                if (resp.ok) {
                    this.orig    = Object.assign({}, json.row ?? this.form);
                    this.form    = Object.assign({}, this.orig);
                    this.editing = false;
                } else if (resp.status === 422) {
                    this.errors = json.errors || {};
                }
            } catch (err) { console.error(err); }
            this.saving = false;
        },

        async del() {
            if (!confirm('Eliminare questo elemento?')) return;
            const resp = await fetch(deleteUrl, {
                method:  'DELETE',
                headers: {
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            if (resp.ok) { this.$el.remove(); }
        },
    };
}
</script>
<style>[x-cloak] { display: none !important; }</style>
</body>
</html>
