@component('mail::message')
# Your barangay account email was changed

Your login email was changed from **{{ $oldEmail }}** to **{{ $newEmail }}**.

If you did not make this change, contact the barangay office immediately and reset your password.

@component('mail::button', ['url' => route('password.request')])
Reset password
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
