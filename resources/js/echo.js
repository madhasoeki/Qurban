import Echo from "laravel-echo";

import Pusher from "pusher-js";
window.Pusher = Pusher;

const pageHost = window.location.hostname;
const pageScheme = window.location.protocol === "https:" ? "https" : "http";

const configuredHost = import.meta.env.VITE_REVERB_HOST;
const configuredPort = import.meta.env.VITE_REVERB_PORT;
const configuredScheme = import.meta.env.VITE_REVERB_SCHEME;

const usePageHostFallback =
    (configuredHost === "localhost" || configuredHost === "127.0.0.1") &&
    pageHost !== "localhost" &&
    pageHost !== "127.0.0.1";

const wsHost = usePageHostFallback ? pageHost : (configuredHost ?? pageHost);
const scheme = configuredScheme ?? pageScheme;
const wsPort = usePageHostFallback
    ? scheme === "https"
        ? 443
        : 80
    : (configuredPort ?? (scheme === "https" ? 443 : 80));

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort,
    wssPort: wsPort,
    forceTLS: scheme === "https",
    enabledTransports: ["ws", "wss"],
});
