<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
    <style>
        /* Custom Sidebar Hover Logic - BASIC */
        @media (min-width: 1024px) {
            .sidebar-compact {
                width: 4rem;
                /* 64px */
                transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                overflow: hidden;
                z-index: 50;
                /* Ensure it's above chat container */
                position: relative;
            }

            .sidebar-compact:hover {
                width: 16rem;
                /* 256px */
            }

            .sidebar-compact .nav-text {
                opacity: 0;
                transition: opacity 0.2s ease;
                white-space: nowrap;
            }

            .sidebar-compact:hover .nav-text {
                opacity: 1;
                transition-delay: 0.1s;
            }

            /* Hide group headings when collapsed */
            .sidebar-compact .flux-sidebar-group-heading {
                opacity: 0;
                transition: opacity 0.2s ease;
            }

            .sidebar-compact:hover .flux-sidebar-group-heading {
                opacity: 1;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="sidebar-compact border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('chat.list') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group class="grid">
                {{-- <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    <span class="nav-text">{{ __('Dashboard') }}</span>
                </flux:sidebar.item> --}}
                <flux:sidebar.item icon="user-group" :href="route('chat.list')"
                    :current="request()->routeIs('chat.list')" wire:navigate>
                    <span class="nav-text">{{ __('Monitor de Chats') }}</span>
                </flux:sidebar.item>
                <flux:sidebar.item icon="cpu-chip" :href="route('bot.settings')"
                    :current="request()->routeIs('bot.settings')" wire:navigate>
                    <span class="nav-text">{{ __('Configuración Bot') }}</span>
                </flux:sidebar.item>
                <flux:sidebar.item icon="chat-bubble-left-right" :href="route('bot.rules')"
                    :current="request()->routeIs('bot.rules')" wire:navigate>
                    <span class="nav-text">{{ __('Reglas de Respuesta') }}</span>
                </flux:sidebar.item>

                <flux:sidebar.item icon="bolt" :href="route('automation.rules')"
                    :current="request()->routeIs('automation.rules')" wire:navigate>
                    <span class="nav-text">{{ __('Automatización') }}</span>
                </flux:sidebar.item>
                <flux:sidebar.item icon="clock" :href="route('contact.followups')"
                    :current="request()->routeIs('contact.followups')" wire:navigate>
                    <span class="nav-text">{{ __('Seguimientos Activos') }}</span>
                </flux:sidebar.item>
                <flux:sidebar.item icon="megaphone" :href="route('followup.campaigns')"
                    :current="request()->routeIs('followup.campaigns')" wire:navigate>
                    <span class="nav-text">{{ __('Campañas Seguimiento') }}</span>
                </flux:sidebar.item>
                <flux:sidebar.item icon="paper-airplane" :href="route('marketing')"
                    :current="request()->routeIs('marketing')" wire:navigate>
                    <span class="nav-text">{{ __('Marketing') }}</span>
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit"
                target="_blank">
                <span class="nav-text">{{ __('Repository') }}</span>
            </flux:sidebar.item>

            <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire"
                target="_blank">
                <span class="nav-text">{{ __('Documentation') }}</span>
            </flux:sidebar.item>
        </flux:sidebar.nav>

        <div class="mt-auto border-t border-zinc-200 dark:border-zinc-700 p-2">
            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </div>
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>
                <flux:menu.separator />
                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}
                </flux:menu.item>
                <flux:menu.separator />
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @fluxScripts
</body>

</html>
