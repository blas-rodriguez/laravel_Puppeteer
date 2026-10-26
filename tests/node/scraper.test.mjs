import test from "node:test";
import assert from "node:assert/strict";
import http from "node:http";
import { mkdtemp, readFile, rm, stat } from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { scrape } from "../../scripts/scrape.mjs";

test("Puppeteer waits for JS, paginates, validates redirects and saves Chrome evidence", async () => {
    let privateRequests = 0;
    let websocketRequests = 0;
    const privateServer = http.createServer((request, response) => {
        privateRequests++;
        response.end("<article>Private resource</article>");
    });
    privateServer.on("upgrade", (request, socket) => {
        websocketRequests++;
        socket.destroy();
    });
    await new Promise((resolve) =>
        privateServer.listen(4190, "127.0.0.1", resolve),
    );
    const server = http.createServer((request, response) => {
        response.setHeader("Content-Type", "text/html; charset=utf-8");
        if (request.url === "/api") {
            response.setHeader("Content-Type", "application/json");
            response.end('{"loaded":true}');
            return;
        }
        if (request.url === "/public-redirect") {
            response.writeHead(302, { Location: "/page/2" }).end();
            return;
        }
        if (request.url === "/too-many") {
            response.end(
                "<main>" +
                    "<article><h2>Item</h2></article>".repeat(101) +
                    "</main>",
            );
            return;
        }
        if (request.url === "/redirect") {
            response
                .writeHead(302, { Location: "http://127.0.0.1:4190/private" })
                .end();
            return;
        }
        if (request.url === "/missing") {
            response.writeHead(404).end();
            return;
        }
        const second = request.url === "/page/2";
        response.setHeader("Content-Type", "text/html; charset=utf-8");
        response.end(`<!doctype html><title>Fixture</title><main></main><script>
      new WebSocket('ws://127.0.0.1:4190/private');
      fetch('/api').then(response => response.json()).then(() => setTimeout(() => document.querySelector('main').innerHTML = '<article><h2>${second ? "Segundo" : "Primero"}</h2><a href="/book"> Detalle </a></article>${second ? "" : '<a class="next" href="/page/2">Next</a>'}', 200));
    </script>`);
    });
    await new Promise((resolve) => server.listen(4189, "127.0.0.1", resolve));
    const output = await mkdtemp(path.join(os.tmpdir(), "scrapelab-test-"));
    const recipe = {
        url: "http://127.0.0.1:4189",
        item_selector: "article",
        next_selector: ".next",
        max_pages: 2,
        fields: [
            { name: "title", selector: "h2" },
            { name: "link", selector: "a", attribute: "href" },
            { name: "missing", selector: ".missing" },
        ],
    };
    try {
        const result = await scrape(recipe, output, { allowFixture: true });
        assert.deepEqual(
            result.records.map((record) => record.data.title),
            ["Primero", "Segundo"],
        );
        assert.equal(result.records[0].data.link, "http://127.0.0.1:4189/book");
        assert.equal(result.records[0].data.missing, null);
        assert.equal(result.pages.length, 2);
        assert.ok((await stat(path.join(output, "screenshot.png"))).size > 0);
        assert.ok((await stat(path.join(output, "trace.json"))).size > 0);
        const trace = JSON.parse(
            await readFile(path.join(output, "trace.json"), "utf8"),
        );
        assert.ok(Array.isArray(trace.traceEvents));
        const network = JSON.parse(
            await readFile(path.join(output, "network.json"), "utf8"),
        );
        assert.ok(
            network.some(
                (entry) => entry.event === "response" && entry.status === 200,
            ),
        );
        assert.ok(
            network.some(
                (entry) =>
                    entry.event === "response" && entry.url.endsWith("/api"),
            ),
        );
        assert.ok(
            network.some((entry) => entry.event === "document_policy"),
            JSON.stringify(network),
        );
        assert.equal(websocketRequests, 0, JSON.stringify(network));
        const redirected = await scrape(
            { ...recipe, url: `${recipe.url}/public-redirect`, max_pages: 1 },
            output,
            { allowFixture: true },
        );
        assert.equal(redirected.records[0].data.title, "Segundo");
        assert.equal(redirected.records[0].source_url, `${recipe.url}/page/2`);
        await assert.rejects(
            scrape({ ...recipe, url: `${recipe.url}/too-many` }, output, {
                allowFixture: true,
            }),
            /100 filas/,
        );
        await assert.rejects(
            scrape({ ...recipe, url: `${recipe.url}/missing` }, output, {
                allowFixture: true,
            }),
            /HTTP 404/,
        );
        await assert.rejects(
            scrape({ ...recipe, url: `${recipe.url}/redirect` }, output, {
                allowFixture: true,
            }),
            /puertos públicos|red privada/,
        );
        assert.equal(
            privateRequests,
            0,
            "Redirect must be blocked before reaching a private server",
        );
    } finally {
        await new Promise((resolve) => server.close(resolve));
        await new Promise((resolve) => privateServer.close(resolve));
        await rm(output, { recursive: true, force: true });
    }
});
