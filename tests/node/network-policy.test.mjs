import test from "node:test";
import assert from "node:assert/strict";
import {
    assertPublicUrl,
    isPublicAddress,
} from "../../scripts/lib/network-policy.mjs";

test("blocks private, loopback, mapped IPv6 and reserved addresses", () => {
    for (const address of [
        "127.0.0.1",
        "10.0.0.5",
        "169.254.169.254",
        "192.168.1.1",
        "::1",
        "::ffff:127.0.0.1",
        "fc00::1",
        "0.0.0.0",
        "100.64.0.1",
    ]) {
        assert.equal(isPublicAddress(address), false, address);
    }
    assert.equal(isPublicAddress("93.184.216.34"), true);
});

test("validates every resolved address, protocol, credentials and port", async () => {
    const resolve = async () => [
        { address: "93.184.216.34" },
        { address: "10.0.0.1" },
    ];
    await assert.rejects(
        assertPublicUrl("https://example.com", { resolve }),
        /privada/,
    );
    for (const url of [
        "file:///etc/passwd",
        "http://user:pass@example.com",
        "http://example.com:3306",
        "http://2130706433",
    ]) {
        await assert.rejects(assertPublicUrl(url));
    }
});
