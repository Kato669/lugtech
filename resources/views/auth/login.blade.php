<x-layouts.app title="Log in">
    <div class="mx-auto max-w-md card">
        <h1 class="text-2xl font-bold">Kaale, welcome back</h1>
        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">@csrf
            <x-input name="email" label="Email" type="email" required autofocus />
            <x-input name="password" label="Password" type="password" required />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" class="rounded"> Remember me</label>
            <button class="btn-primary w-full">Log in</button>
        </form>
        <p class="mt-4 text-center text-sm">New here? <a href="{{ route('register') }}" class="font-semibold text-bark-600">Create an account</a></p>
    </div>
</x-layouts.app>
