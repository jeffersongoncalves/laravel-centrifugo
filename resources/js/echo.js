// Drop-in stand-in for the slice of Laravel Echo that Filament and Livewire
// call: Echo.private(channel).listen(event, cb) / .stopListening(event, cb),
// Echo.channel(...) (public), Echo.socketId() and the `EchoLoaded` event.
// Built to resources/dist/centrifugo-echo.js (`pnpm run build`).
// Presence channels and whispers are not implemented.
import { Centrifuge } from 'centrifuge'

let script = document.currentScript

if (script && !window.Echo) {
    let { csrf, authUrl, connectionTokenUrl } = script.dataset

    // A bare host (https://ws.example.com) gets wss:// + the default endpoint path.
    let url = new URL(script.dataset.url || '/connection/websocket', location.href)
    url.protocol = url.protocol.replace(/^http/, 'ws')
    if (url.pathname === '/') {
        url.pathname = '/connection/websocket'
    }

    let fetchToken = async (endpoint, body = {}) => {
        let response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body),
        })

        if (!response.ok) {
            throw new Error(`Centrifugo token request failed: ${response.status}`)
        }

        return (await response.json()).token
    }

    let centrifuge = new Centrifuge(url.toString(), {
        getToken: () => fetchToken(connectionTokenUrl),
    })

    let connected = false

    let channel = (name, options = {}) => {
        // Connect lazily: pages that never subscribe never open a socket.
        if (!connected) {
            connected = true
            centrifuge.connect()
        }

        let subscription = centrifuge.getSubscription(name) ?? centrifuge.newSubscription(name, options)
        subscription.subscribe()

        let handlers = new Map()

        return {
            listen(event, callback) {
                let handler = ({ data }) => {
                    if (data?.event === event.replace(/^\./, '')) {
                        callback(data.data)
                    }
                }

                handlers.set(callback, handler)
                subscription.on('publication', handler)

                return this
            },
            stopListening(event, callback) {
                if (handlers.has(callback)) {
                    subscription.removeListener('publication', handlers.get(callback))
                    handlers.delete(callback)
                }

                return this
            },
        }
    }

    window.Echo = {
        // Sent by Livewire as X-Socket-ID on every request; toOthers() is unsupported.
        socketId: () => '',
        channel: (name) => channel(name),
        private: (name) => channel(`private-${name}`, {
            getToken: () => fetchToken(authUrl, { channel_name: `private-${name}` }),
        }),
        leave: (name) => {
            for (let candidate of [name, `private-${name}`]) {
                let subscription = centrifuge.getSubscription(candidate)
                if (subscription) {
                    centrifuge.removeSubscription(subscription)
                }
            }
        },
        centrifuge,
    }

    window.dispatchEvent(new CustomEvent('EchoLoaded'))
}
