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
        .table-standard > tbody:nth-of-type(odd) > tr {
            background-color: #f8fafc;
        }
        @media (min-width: 1024px) {
            #app-sidebar {
                width: 16rem;
                min-width: 16rem;
                max-width: 16rem;
                flex: 0 0 16rem;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800"
      x-data="{
          sidebarOpen: false,
          sidebarCollapsed: false,
          isDesktop: window.innerWidth >= 1024,
          navSections: {
              anagrafica: true,
              categorie: true,
              corrente: true,
              analisi: true,
              import_export: true,
              importa_dati: true,
              esporta_dati: true,
              preventivo: true,
              impostazioni: true,
          },
          init() {
              try { this.sidebarCollapsed = localStorage.getItem('bn_sidebar_collapsed') === '1'; } catch (e) {}
              try { this.sidebarOpen = localStorage.getItem('bn_sidebar_open') === '1'; } catch (e) {}
              try {
                  const raw = localStorage.getItem('bn_nav_sections');
                  if (raw) {
                      const parsed = JSON.parse(raw);
                      this.navSections.anagrafica = typeof parsed.anagrafica === 'boolean' ? parsed.anagrafica : this.navSections.anagrafica;
                      this.navSections.categorie = typeof parsed.categorie === 'boolean' ? parsed.categorie : this.navSections.categorie;
                      this.navSections.corrente = typeof parsed.corrente === 'boolean' ? parsed.corrente : this.navSections.corrente;
                      this.navSections.analisi = typeof parsed.analisi === 'boolean' ? parsed.analisi : this.navSections.analisi;
                      this.navSections.import_export = typeof parsed.import_export === 'boolean' ? parsed.import_export : this.navSections.import_export;
                      this.navSections.importa_dati = typeof parsed.importa_dati === 'boolean' ? parsed.importa_dati : this.navSections.importa_dati;
                      this.navSections.esporta_dati = typeof parsed.esporta_dati === 'boolean' ? parsed.esporta_dati : this.navSections.esporta_dati;
                      this.navSections.preventivo = typeof parsed.preventivo === 'boolean' ? parsed.preventivo : this.navSections.preventivo;
                      this.navSections.impostazioni = typeof parsed.impostazioni === 'boolean' ? parsed.impostazioni : this.navSections.impostazioni;
                  }
              } catch (e) {}
              window.addEventListener('resize', () => {
                  this.isDesktop = window.innerWidth >= 1024;
                  if (this.isDesktop) {
                      this.sidebarOpen = false;
                      try { localStorage.setItem('bn_sidebar_open', '0'); } catch (e) {}
                  }
              });
          },
          toggleSidebarCollapsed() {
              this.sidebarCollapsed = !this.sidebarCollapsed;
              try { localStorage.setItem('bn_sidebar_collapsed', this.sidebarCollapsed ? '1' : '0'); } catch (e) {}
          },
          toggleSidebarMobile() {
              this.sidebarOpen = !this.sidebarOpen;
              try { localStorage.setItem('bn_sidebar_open', this.sidebarOpen ? '1' : '0'); } catch (e) {}
          },
          toggleNavSection(section) {
              this.navSections[section] = !this.navSections[section];
              try { localStorage.setItem('bn_nav_sections', JSON.stringify(this.navSections)); } catch (e) {}
          }
      }">
<div class="min-h-screen">
    <aside
        id="app-sidebar"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        :style="(isDesktop && sidebarCollapsed) ? 'display:none' : ''"
        class="fixed inset-y-0 left-0 z-40 w-64 transform bg-slate-900 text-slate-200 transition-all duration-200 overflow-y-auto"
    >
        <a href="{{ route('home', isset($selectedYear) ? ['year' => $selectedYear] : []) }}"
           class="flex h-16 items-center border-b border-slate-700 px-6 hover:bg-slate-800/60 transition-colors">
            <img src="{{ asset('images/logo/logo.svg') }}" alt="BudgetNow" class="h-8">
            <span class="ml-3 font-semibold tracking-wide">BudgetNow</span>
        </a>

        <nav class="p-4">
            @php
                $yearQuery = isset($selectedYear) ? ['year' => $selectedYear] : [];
            @endphp
            <a href="{{ route('home', isset($selectedYear) ? ['year' => $selectedYear] : []) }}"
               class="block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('home') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                Home
            </a>
            <a href="{{ route('journal.index', isset($selectedYear) ? ['year' => $selectedYear] : []) }}"
               class="mt-1 block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('journal.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                Libro Giornale
            </a>

            <div class="mt-1">
                <button type="button"
                        @click="toggleNavSection('categorie')"
                        class="mt-3 mb-1 flex w-full items-center justify-between rounded-lg px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-800/60">
                    <span>Categorie</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="navSections.categorie ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="navSections.categorie" x-cloak>
                    @foreach($navEntryTypes ?? [] as $navType)
                        @php $isActiveCategoria = request()->routeIs('categorie.*') && request()->route('tipo')?->id === $navType->id; @endphp
                        <a href="{{ route('categorie.index', $navType) }}"
                           class="ml-2 block rounded-lg px-4 py-2 text-sm font-medium {{ $isActiveCategoria ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                            {{ $navType->name }}
                        </a>
                    @endforeach
                </div>
            </div>

            <button type="button"
                    @click="toggleNavSection('corrente')"
                    class="mt-6 mb-1 flex w-full items-center justify-between rounded-lg px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-800/60">
                <span>Budget Corrente</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="navSections.corrente ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
            <div class="mt-1" x-show="navSections.corrente" x-cloak>
                <a href="{{ route('corrente.entrate', $yearQuery) }}"
                   class="ml-2 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('corrente.entrate') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                    Entrate
                </a>
                <a href="{{ route('corrente.uscite', $yearQuery) }}"
                   class="ml-2 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('corrente.uscite') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                    Uscite
                </a>
            </div>

            <button type="button"
                    @click="toggleNavSection('preventivo')"
                    class="mt-3 mb-1 flex w-full items-center justify-between rounded-lg px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-800/60">
                <span>Budget Preventivo</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="navSections.preventivo ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
            <div class="mt-1" x-show="navSections.preventivo" x-cloak>
                <a href="{{ route('preventivo.entrate', $yearQuery) }}"
                   class="ml-2 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('preventivo.entrate') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                    Entrate
                </a>
                <a href="{{ route('preventivo.uscite', $yearQuery) }}"
                   class="ml-2 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('preventivo.uscite') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                    Uscite
                </a>
            </div>

            <button type="button"
                    @click="toggleNavSection('analisi')"
                    class="mt-3 mb-1 flex w-full items-center justify-between rounded-lg px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-800/60">
                <span>Analisi</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="navSections.analisi ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
            <div class="mt-1" x-show="navSections.analisi" x-cloak>
                <a href="{{ route('analysis.flows', $yearQuery) }}"
                   class="ml-2 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('analysis.flows') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                    Flussi
                </a>
            </div>

            <hr class="my-4 border-slate-700/70">

            <button type="button"
                    @click="toggleNavSection('anagrafica')"
                    class="mt-1 mb-1 flex w-full items-center justify-between rounded-lg px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-800/60">
                <span>Anagrafiche</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="navSections.anagrafica ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
            <div x-show="navSections.anagrafica" x-cloak>
                <a href="{{ route('conti-riferimento.index') }}"
                   class="block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('conti-riferimento.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                    Conti di Riferimento
                </a>
                <a href="{{ route('sedi.index') }}"
                   class="mt-1 block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('sedi.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                    Sedi
                </a>

                <a href="{{ route('tipi.index') }}"
                   class="mt-1 block rounded-lg px-4 py-2.5 text-sm font-medium {{ request()->routeIs('tipi.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-200 hover:bg-slate-800' }}">
                    Tipi di Movimento
                </a>

            </div>

            <button type="button"
                    @click="toggleNavSection('impostazioni')"
                    class="mt-6 mb-1 flex w-full items-center justify-between rounded-lg px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-800/60">
                <span>Impostazioni</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="navSections.impostazioni ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
            <div x-show="navSections.impostazioni" x-cloak>
                <a href="{{ route('budget.index') }}"
                   class="ml-2 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('budget.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                    Gestione Budget
                </a>
                <a href="{{ route('opzioni.edit') }}"
                   class="ml-2 mt-1 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('opzioni.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                    Opzioni
                </a>
            </div>

            <button type="button"
                    @click="toggleNavSection('import_export')"
                    class="mt-6 mb-1 flex w-full items-center justify-between rounded-lg px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-800/60">
                <span>Import / Export</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="navSections.import_export ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
            <div class="mt-1" x-show="navSections.import_export" x-cloak>
                <button type="button"
                        @click="toggleNavSection('importa_dati')"
                        class="ml-2 mt-1 mb-1 flex w-full items-center justify-between rounded-lg px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-800/60">
                    <span>Importa Dati</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="navSections.importa_dati ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="navSections.importa_dati" x-cloak>
                    <a href="{{ route('journal.import.csv.create') }}"
                       class="ml-4 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('journal.import.csv.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                        CSV
                    </a>
                    <a href="{{ route('data-transfer.import.sql.form') }}"
                       class="ml-4 mt-1 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('data-transfer.import.sql.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                        SQL
                    </a>
                </div>

                <button type="button"
                        @click="toggleNavSection('esporta_dati')"
                        class="ml-2 mt-2 mb-1 flex w-full items-center justify-between rounded-lg px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-800/60">
                    <span>Esporta Dati</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="navSections.esporta_dati ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="navSections.esporta_dati" x-cloak>
                    <a href="{{ route('data-transfer.export.sql.form') }}"
                       class="ml-4 block rounded-lg px-4 py-2 text-sm font-medium {{ request()->routeIs('data-transfer.export.sql.*') ? 'bg-blue-500/20 text-blue-300' : 'text-slate-300 hover:bg-slate-800' }}">
                        SQL
                    </a>
                </div>
            </div>

        </nav>
    </aside>

    <div :class="(isDesktop && !sidebarCollapsed) ? 'lg:ml-64 lg:w-[calc(100%-16rem)]' : 'lg:ml-0 lg:w-full'"
         class="flex min-h-screen min-w-0 flex-col transition-[margin,width] duration-200">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
            <div class="flex h-16 items-center justify-between px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <button @click="toggleSidebarMobile()" class="rounded-md border border-slate-200 p-2 text-slate-600 lg:hidden">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h8a1 1 0 010 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 010 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <button @click="toggleSidebarCollapsed()"
                            x-show="isDesktop"
                            class="hidden lg:inline-flex rounded-md border border-slate-200 p-2 text-slate-600 hover:bg-slate-50"
                            :title="sidebarCollapsed ? 'Espandi menù laterale' : 'Comprimi menù laterale'">
                        <svg x-show="!sidebarCollapsed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 010 1.06L8.06 10l4.72 4.72a.75.75 0 11-1.06 1.06l-5.25-5.25a.75.75 0 010-1.06l5.25-5.25a.75.75 0 011.06 0z" clip-rule="evenodd" />
                        </svg>
                        <svg x-show="sidebarCollapsed" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.22 15.78a.75.75 0 010-1.06L11.94 10 7.22 5.28a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06 0z" clip-rule="evenodd" />
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
                            @foreach(request()->except('year') as $k => $v)
                                @if(is_array($v))
                                    @foreach($v as $vv)
                                        <input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endif
                            @endforeach
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
