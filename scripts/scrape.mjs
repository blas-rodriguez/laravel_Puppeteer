import puppeteer from "puppeteer";
import { mkdir, writeFile } from "node:fs/promises";
import { setTimeout as delay } from "node:timers/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";
import { assertPublicUrl } from "./lib/network-policy.mjs";

export async function scrape(recipe, outputDir, { allowFixture = false } = {}) {
    const started = Date.now();
    const policy = (url) => assertPublicUrl(url, { allowFixture });
    await policy(recipe.url);
    if (
        !recipe.item_selector ||
        !Array.isArray(recipe.fields) ||
        !recipe.fields.length
    ) {
        throw new Error("La receta necesita un selector de filas y campos.");
    }
    const maxPages = Math.min(5, Math.max(1, Number(recipe.max_pages) || 1));
    await mkdir(outputDir, { recursive: true });
    const browser = await puppeteer.launch({
        headless: true,
        protocol: "cdp",
        ignoreDefaultArgs: ["--disable-popup-blocking"],
    });
    let page;
    let traceStarted = false;
    let navigationError = null;
    const network = [];
    const log = (entry) => {
        if (network.length < 2000)
            network.push({ ...entry, elapsed_ms: Date.now() - started });
    };
    try {
        const context = await browser.createBrowserContext({
            downloadBehavior: { policy: "deny" },
        });
        page = await context.newPage();
        await page.setViewport({ width: 1440, height: 960 });
        await page.setUserAgent(
            "ScrapeLab-Puppeteer/1.0 (portfolio demo; public pages only)",
        );
        await page.setBypassServiceWorker(true);
        await page.setCacheEnabled(false);
        page.setDefaultTimeout(12000);
        page.setDefaultNavigationTimeout(25000);
        const session = await page.createCDPSession();
        await page.tracing.start({
            path: path.join(outputDir, "trace.json"),
            screenshots: true,
        });
        traceStarted = true;
        // One CDP interception pipeline handles request and document-response stages.
        session.on("Fetch.requestPaused", async (event) => {
            try {
                if (event.responseStatusCode !== undefined) {
                    if (
                        event.responseStatusCode >= 300 &&
                        event.responseStatusCode < 400
                    ) {
                        await session.send("Fetch.continueRequest", {
                            requestId: event.requestId,
                        });
                        return;
                    }
                    log({ event: "document_policy", url: event.request.url });
                    const responseBody = await session.send(
                        "Fetch.getResponseBody",
                        { requestId: event.requestId },
                    );
                    const body = Buffer.from(
                        responseBody.body,
                        responseBody.base64Encoded ? "base64" : "utf8",
                    );
                    if (body.length > 10 * 1024 * 1024)
                        throw new Error(
                            "El documento supera el límite de 10 MB.",
                        );
                    const headers = (event.responseHeaders ?? []).filter(
                        (header) =>
                            ![
                                "content-length",
                                "content-encoding",
                                "transfer-encoding",
                            ].includes(header.name.toLowerCase()),
                    );
                    if (!responseBody.base64Encoded) {
                        const type = headers.find(
                            (header) =>
                                header.name.toLowerCase() === "content-type",
                        );
                        if (type)
                            type.value = `${type.value.split(";")[0]}; charset=utf-8`;
                    }
                    await session.send("Fetch.fulfillRequest", {
                        requestId: event.requestId,
                        responseCode: event.responseStatusCode,
                        body: body.toString("base64"),
                        responseHeaders: [
                            ...headers,
                            {
                                name: "Content-Security-Policy",
                                value: "connect-src http: https:; worker-src 'none'; frame-src 'none'; object-src 'none'",
                            },
                        ],
                    });
                    return;
                }
                await policy(event.request.url);
                if (["Media", "Font"].includes(event.resourceType)) {
                    await session.send("Fetch.failRequest", {
                        requestId: event.requestId,
                        errorReason: "BlockedByClient",
                    });
                    return;
                }
                await session.send("Fetch.continueRequest", {
                    requestId: event.requestId,
                    interceptResponse: event.resourceType === "Document",
                });
            } catch (error) {
                log({
                    event: "blocked",
                    url: event.request.url,
                    reason: error.message,
                });
                if (event.resourceType === "Document") {
                    navigationError = error;
                }
                await session
                    .send("Fetch.failRequest", {
                        requestId: event.requestId,
                        errorReason: "BlockedByClient",
                    })
                    .catch(() => {});
            }
        });
        await session.send("Fetch.enable", {
            patterns: [{ urlPattern: "*", requestStage: "Request" }],
        });
        page.on("response", (response) =>
            log({
                event: "response",
                url: response.url(),
                status: response.status(),
                policy: response.headers()["content-security-policy"],
            }),
        );
        page.on("requestfailed", (request) =>
            log({
                event: "failed",
                url: request.url(),
                reason: request.failure()?.errorText,
            }),
        );
        page.on("popup", (popup) => popup?.close().catch(() => {}));

        const records = [];
        const pages = [];
        const visited = new Set();
        let next = recipe.url;
        for (let index = 0; index < maxPages && next; index++) {
            await policy(next);
            if (visited.has(next)) break;
            visited.add(next);
            navigationError = null;
            const response = await page.goto(next, {
                waitUntil: "domcontentloaded",
            });
            if (!response || response.status() >= 400) {
                throw new Error(
                    `El sitio respondió HTTP ${response?.status() ?? "sin respuesta"}.`,
                );
            }
            const firstItem = await page.waitForSelector(recipe.item_selector, {
                visible: true,
            });
            await firstItem?.dispose();
            const rows = await page.$$eval(
                recipe.item_selector,
                (items, fields) => {
                    if (items.length > 100)
                        throw new Error(
                            "El selector devolvió más de 100 filas por página. Usá uno más específico.",
                        );
                    return items.map((item) =>
                        Object.fromEntries(
                            fields.map((field) => {
                                const element =
                                    field.selector === ":scope"
                                        ? item
                                        : item.querySelector(field.selector);
                                let value = element
                                    ? field.attribute
                                        ? element.getAttribute(field.attribute)
                                        : element.textContent
                                    : null;
                                if (
                                    value &&
                                    ["href", "src"].includes(field.attribute)
                                ) {
                                    try {
                                        value = new URL(value, document.baseURI)
                                            .href;
                                    } catch {
                                        /* preserve original */
                                    }
                                }
                                return [
                                    field.name,
                                    value === null
                                        ? null
                                        : value
                                              .replace(/\s+/g, " ")
                                              .trim()
                                              .slice(0, 5000),
                                ];
                            }),
                        ),
                    );
                },
                recipe.fields,
            );
            records.push(
                ...rows.map((data) => ({
                    data,
                    source_url: page.url(),
                    page_number: index + 1,
                })),
            );
            pages.push({
                url: page.url(),
                title: await page.title(),
                count: rows.length,
            });
            visited.add(page.url());
            await page.screenshot({
                path: path.join(outputDir, "screenshot.png"),
                fullPage: false,
            });
            next = null;
            if (recipe.next_selector && index + 1 < maxPages) {
                const link = await page.$(recipe.next_selector);
                if (link) {
                    const href = await link.evaluate((element) =>
                        element.getAttribute("href"),
                    );
                    await link.dispose();
                    if (href) {
                        const candidate = new URL(href, page.url());
                        if (candidate.origin !== new URL(page.url()).origin) {
                            throw new Error(
                                "La paginación debe permanecer en el mismo origen.",
                            );
                        }
                        next = candidate.href;
                        await delay(700);
                    }
                }
            }
        }
        return { records, pages, duration_ms: Date.now() - started };
    } catch (error) {
        throw new Error(
            `No se pudo completar la extracción: ${(navigationError ?? error).message}`,
            { cause: error },
        );
    } finally {
        if (traceStarted) await page.tracing.stop().catch(() => {});
        await writeFile(
            path.join(outputDir, "network.json"),
            JSON.stringify(network, null, 2),
        ).catch(() => {});
        await browser.close();
    }
}

if (
    process.argv[1] &&
    import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href
) {
    try {
        let input = "";
        process.stdin.setEncoding("utf8");
        for await (const chunk of process.stdin) {
            input += chunk;
            if (input.length > 100000)
                throw new Error("Configuración demasiado grande.");
        }
        process.stdout.write(
            JSON.stringify(await scrape(JSON.parse(input), process.argv[2])),
        );
    } catch (error) {
        process.stderr.write(error.message.slice(0, 4000));
        process.exitCode = 1;
    }
}
