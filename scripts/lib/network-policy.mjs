import { lookup } from "node:dns/promises";
import ipaddr from "ipaddr.js";

export function isPublicAddress(address) {
    try {
        return ipaddr.process(address).range() === "unicast";
    } catch {
        return false;
    }
}

export async function assertPublicUrl(
    input,
    { resolve = lookup, allowFixture = false } = {},
) {
    const url = new URL(input);
    if (
        !["http:", "https:"].includes(url.protocol) ||
        url.username ||
        url.password
    ) {
        throw new Error("Solo se permiten URLs HTTP(S) sin credenciales.");
    }
    const hostname = url.hostname.replace(/^\[|\]$/g, "");
    // Only the isolated test process enables this; never a submitted recipe.
    if (allowFixture && hostname === "127.0.0.1" && url.port === "4189")
        return url;
    if (url.port && !["80", "443"].includes(url.port))
        throw new Error("Solo se permiten los puertos públicos 80 y 443.");
    const addresses = ipaddr.isValid(hostname)
        ? [{ address: hostname }]
        : await resolve(hostname, { all: true, verbatim: true });
    if (
        !addresses.length ||
        addresses.some(({ address }) => !isPublicAddress(address))
    ) {
        throw new Error("La URL apunta a una red privada, local o reservada.");
    }
    return url;
}
