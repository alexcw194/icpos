<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    @php
        try {
            $usernamePolicy = \App\Models\Setting::get('mail.username_policy', 'default_email');
        } catch (\Throwable $e) {
            $usernamePolicy = 'default_email';
        }
        $me = auth()->user();
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Info dasar (nama, email) --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            {{-- Ganti password akun (bukan SMTP) --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- Hapus akun (opsional) --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

            {{-- ======================= --}}
            {{-- Email (SMTP) Pribadi    --}}
            {{-- ======================= --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <header>
                    <h2 class="text-lg font-medium text-gray-900">Email Pribadi</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Host/Port/Enkripsi disetel Admin di <em>Global Settings</em>.
                        Di sini Anda mengisi <strong>password</strong> email (dan username bila kebijakan mengharuskan).
                    </p>
                </header>

                <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
                    @csrf
                    @method('patch')

                    {{-- Hidden agar validasi name/email pada ProfileController tetap lolos --}}
                    <input type="hidden" name="name"  value="{{ old('name', $me->name) }}">
                    <input type="hidden" name="email" value="{{ old('email', $me->email) }}">

                    @if($usernamePolicy === 'force_email')
                        {{-- Policy: username = email (dipaksa) --}}
                        <div>
                            <x-input-label value="SMTP Username" />
                            <div class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 px-3 py-2 text-gray-600">
                                {{ $me->email }}
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Kebijakan global: username selalu sama dengan email Anda.</p>
                        </div>
                        {{-- agar key smtp_username selalu ada di payload --}}
                        <input type="hidden" name="smtp_username" value="">
                    @elseif($usernamePolicy === 'custom_only')
                        {{-- Policy: wajib custom username --}}
                        <div>
                            <x-input-label for="smtp_username" value="SMTP Username (wajib)" />
                            <x-text-input id="smtp_username" type="text" name="smtp_username"
                                          class="mt-1 block w-full"
                                          value="{{ old('smtp_username', $me->smtp_username) }}"
                                          autocomplete="username" required />
                            <x-input-error :messages="$errors->get('smtp_username')" class="mt-2" />
                        </div>
                    @else
                        {{-- Policy: default_email (boleh override) --}}
                        @php
                            $useEmail = old(
                                'use_email_as_username',
                                $me->smtp_username ? '' : '1'   // jika belum ada custom -> ON
                            );
                        @endphp

                        <div class="flex items-center gap-2">
                            <input id="use_email_as_username" type="checkbox" name="use_email_as_username"
                                   value="1" {{ $useEmail ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <label for="use_email_as_username" class="text-sm text-gray-700">
                                Gunakan email sebagai username
                            </label>
                        </div>

                        {{-- Hidden agar key selalu terkirim saat input disabled --}}
                        <input type="hidden" name="smtp_username" value="{{ $useEmail ? '' : old('smtp_username', $me->smtp_username) }}">

                        <div>
                            <x-input-label for="smtp_username_field" value="SMTP Username (opsional)" />
                            <x-text-input id="smtp_username_field" type="text"
                                          class="mt-1 block w-full"
                                          value="{{ old('smtp_username', $me->smtp_username) }}"
                                          autocomplete="username"
                                          {{ $useEmail ? 'readonly disabled' : '' }} />
                            <x-input-error :messages="$errors->get('smtp_username')" class="mt-2" />
                            <p id="smtp_username_hint" class="text-sm text-gray-500 mt-1">
                                {{ $useEmail ? 'Saat ini memakai email Anda sebagai username.' : 'Bila server tidak memakai email sebagai username, isi di sini.' }}
                            </p>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function(){
                                const cb    = document.getElementById('use_email_as_username');
                                const field = document.getElementById('smtp_username_field');
                                // hidden input yang benar-benar dipost
                                const hidden = document.querySelector('input[type="hidden"][name="smtp_username"]');
                                const hint  = document.getElementById('smtp_username_hint');

                                function syncState(){
                                    if (cb.checked) {
                                        field.setAttribute('disabled','disabled');
                                        field.setAttribute('readonly','readonly');
                                        hidden.value = ''; // pakai email sebagai username
                                        hint.textContent = 'Saat ini memakai email Anda sebagai username.';
                                    } else {
                                        field.removeAttribute('disabled');
                                        field.removeAttribute('readonly');
                                        hidden.value = field.value;
                                        hint.textContent = 'Bila server tidak memakai email sebagai username, isi di sini.';
                                    }
                                }
                                field && field.addEventListener('input', function(){
                                    if (!cb.checked) hidden.value = field.value;
                                });
                                cb.addEventListener('change', syncState);
                                syncState();
                            });
                        </script>
                    @endif

                    <div>
                        <x-input-label for="smtp_password" value="SMTP Password" />
                        <x-text-input id="smtp_password" type="password" name="smtp_password"
                                      class="mt-1 block w-full"
                                      autocomplete="new-password"
                                      placeholder="Kosongkan bila tidak ingin mengubah" />
                        <x-input-error :messages="$errors->get('smtp_password')" class="mt-2" />
                        <p class="text-sm text-gray-500 mt-1">
                            Kosongkan untuk tetap memakai password SMTP yang sudah tersimpan.
                        </p>
                    </div>

                    <div>
                        <x-input-label for="email_signature" value="Email Signature" />
                        <textarea id="email_signature" name="email_signature" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('email_signature', $me->email_signature) }}</textarea>
                        <x-input-error :messages="$errors->get('email_signature')" class="mt-2" />
                    </div>

                    <div class="pt-4 border-t">
                        <h3 class="text-sm font-semibold text-gray-700">Dokumen Signature</h3>
                        <p class="text-sm text-gray-500 mt-1">Upload tanda tangan untuk dokumen PDF.</p>

                        <div class="mt-3">
                            <x-input-label for="signature_position" value="Default Signature Position" />
                            <x-text-input id="signature_position" type="text" name="signature_position"
                                          class="mt-1 block w-full"
                                          value="{{ old('signature_position', $signature->default_position ?? '') }}"
                                          placeholder="Contoh: Sales Executive" />
                            <x-input-error :messages="$errors->get('signature_position')" class="mt-2" />
                        </div>

                        <div class="mt-3">
                            <x-input-label for="document_signature" value="Upload Signature (PNG/JPG)" />
                            <input id="document_signature" type="file" name="document_signature"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                   accept="image/png,image/jpeg">
                            <x-input-error :messages="$errors->get('document_signature')" class="mt-2" />
                            @if(!empty($signature?->image_path))
                                <div class="mt-2 text-sm text-gray-600">Signature tersimpan.</div>
                                <img src="{{ asset('storage/'.$signature->image_path) }}" alt="Signature preview"
                                     class="mt-2 max-w-xs border rounded">
                            @endif
                        </div>
                    </div>

                    {{-- NEW: CC ke diri sendiri --}}
                    <div class="mb-2 form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="email_cc_self" name="email_cc_self" value="1"
                            {{ old('email_cc_self', $user->email_cc_self) ? 'checked' : '' }}>
                    <label class="form-check-label" for="email_cc_self">
                        Kirim salinan ke saya (CC)
                    </label>
                    <div class="form-text">
                        Jika aktif, salinan akan dikirim ke email login Anda: <strong>{{ $user->email }}</strong>.
                    </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Simpan</x-primary-button>

                        @if (session('success') || session('status') === 'profile-updated')
                            <p class="text-sm text-gray-600">Tersimpan.</p>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
