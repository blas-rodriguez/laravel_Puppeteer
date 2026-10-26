import { after, before, test } from "node:test";
import assert from "node:assert/strict";
import { mkdir } from "node:fs/promises";
import puppeteer from "puppeteer";
import { startTestApp } from "./server.mjs";

let app;
let browser;
before(async () => {
    app = await startTestApp();
    browser = await puppeteer.launch({ headless: true });
});
after(async () => {
    await browser?.close();
    app?.stop();
});

async function withPage(t, run) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    await page.setViewport({ width: 1440, height: 1000 });
    try {
        await run(page);
    } catch (error) {
        await mkdir("test-results", { recursive: true });
        await page
            .screenshot({
                path: `test-results/${t.name.replace(/[^a-z0-9]+/gi, "-")}.png`,
                fullPage: true,
            })
            .catch(() => {});
        throw error;
    } finally {
        await context.close();
    }
}

test("workspace loads and presets configure the recipe", async (t) =>
    withPage(t, async (page) => {
        await page.goto("http://127.0.0.1:8011");
        assert.match(
            await page.$eval("h1", (element) => element.textContent),
            /Datos web/,
        );
        await page.click('[data-preset="books"]');
        assert.equal(
            await page.$eval("#url", (input) => input.value),
            "https://books.toscrape.com/",
        );
        const fields = JSON.parse(
            await page.$eval("#fields", (input) => input.value),
        );
        assert.equal(fields[0].attribute, "title");
        await page.click('[data-preset="quotes"]');
        assert.equal(
            await page.$eval("#url", (input) => input.value),
            "https://quotes.toscrape.com/js/",
        );
    }));

test("invalid JSON is preserved with validation feedback", async (t) =>
    withPage(t, async (page) => {
        await page.goto("http://127.0.0.1:8011");
        await page.$eval("#fields", (input) => {
            input.value = "{ invalid";
        });
        await Promise.all([
            page.waitForNavigation(),
            page.click("#submit-button"),
        ]);
        assert.match(
            await page.$eval(
                '[role="alert"]',
                (element) => element.textContent,
            ),
            /Revisá la configuración/,
        );
        assert.equal(
            await page.$eval("#fields", (input) => input.value),
            "{ invalid",
        );
        assert.equal(
            await page.$eval("#submit-button", (button) => button.disabled),
            false,
        );
    }));

test("mobile layout has no horizontal page overflow", async (t) =>
    withPage(t, async (page) => {
        await page.setViewport({ width: 390, height: 844 });
        await page.goto("http://127.0.0.1:8011");
        assert.ok(await page.$("#submit-button"));
        assert.equal(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
            true,
        );
    }));

test(
    "live demo: UI to queue to Puppeteer to export",
    { skip: process.env.SCRAPELAB_LIVE_TESTS !== "1", timeout: 120000 },
    async (t) =>
        withPage(t, async (page) => {
            await page.goto("http://127.0.0.1:8011");
            await page.select("#max_pages", "1");
            await Promise.all([
                page.waitForNavigation(),
                page.click("#submit-button"),
            ]);
            assert.match(page.url(), /\/scrapes\/\d+$/);
            await page.waitForFunction(
                () =>
                    document
                        .querySelector("#run-status")
                        ?.textContent.includes("Completado"),
                { timeout: 90000 },
            );
            assert.equal(
                await page.$$eval(
                    ".results-table tbody tr",
                    (rows) => rows.length,
                ),
                10,
            );
            const links = await page.$$eval(".artifact-links a", (items) =>
                items.map((item) => item.href),
            );
            assert.ok(links.some((url) => url.endsWith("/trace")));
            assert.ok(links.some((url) => url.endsWith("/network")));
            const exported = await page.evaluate(async () => {
                const url = document.querySelector(
                    'a[href$="/export/json"]',
                ).href;
                return (await fetch(url)).json();
            });
            assert.equal(exported.records.length, 10);
        }),
);
