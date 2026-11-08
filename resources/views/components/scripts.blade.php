@props(['guard' => null])
@php($nonce = \Illuminate\Support\Facades\Vite::cspNonce())
<script
    src="{{ route('centrifugo.script', ['id' => md5_file(\JeffersonGoncalves\Centrifugo\CentrifugoServiceProvider::SCRIPT)]) }}"
    data-url="{{ config('centrifugo.url') }}"
    data-csrf="{{ csrf_token() }}"
    data-auth-url="{{ url(config('centrifugo.auth_endpoint')) }}"
    data-connection-token-url="{{ route('centrifugo.connection-token', array_filter(['guard' => $guard])) }}"
    data-cfasync="false"
    @if ($nonce) nonce="{{ $nonce }}" @endif
></script>
