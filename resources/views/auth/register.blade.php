<x-layouts.app title="Create account">
    <div class="mx-auto max-w-md card">
        <h1 class="text-2xl font-bold">Tukusanyukidde! <span class="block text-base font-normal text-bark-500">We're glad you're here.</span></h1>
        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">@csrf
            <x-input name="name" label="Your name" required autofocus />
            <x-input name="email" label="Email" type="email" required />
            <x-input name="password" label="Password" type="password" required help="At least 8 characters." />
            <x-input name="password_confirmation" label="Confirm password" type="password" required />
            <button class="btn-primary w-full">Create account</button>
        </form>
        <p class="mt-4 text-center text-sm">Already learning? <a href="{{ route('login') }}" class="font-semibold text-bark-600">Log in</a></p>
    </div>
</x-layouts.app>
