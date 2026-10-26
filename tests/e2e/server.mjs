import { spawn, spawnSync } from "node:child_process";
import { mkdirSync, writeFileSync } from "node:fs";
import { setTimeout as delay } from "node:timers/promises";
import path from "node:path";

export async function startTestApp() {
    const directory = path.resolve("storage/framework/testing");
    mkdirSync(directory, { recursive: true });
    const database = path.join(directory, "e2e.sqlite");
    writeFileSync(database, "");
    const env = {
        ...process.env,
        APP_ENV: "testing",
        APP_DEBUG: "false",
        APP_KEY: "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=",
        APP_URL: "http://127.0.0.1:8011",
        DB_CONNECTION: "sqlite",
        DB_DATABASE: database,
        DB_URL: "",
        CACHE_STORE: "file",
        SESSION_DRIVER: "file",
        QUEUE_CONNECTION: "database",
    };
    const migrated = spawnSync(
        "php",
        ["artisan", "migrate", "--force", "--no-interaction"],
        { env, encoding: "utf8", windowsHide: true },
    );
    if (migrated.status !== 0)
        throw new Error(
            `No se pudo preparar E2E: ${migrated.stdout}\n${migrated.stderr}`,
        );
    const children = [];
    const stop = () => {
        for (const child of children) {
            if (!child.pid || child.exitCode !== null) continue;
            if (process.platform === "win32") {
                spawnSync("taskkill", ["/pid", String(child.pid), "/t", "/f"], {
                    windowsHide: true,
                    stdio: "ignore",
                });
            } else {
                try {
                    process.kill(-child.pid, "SIGTERM");
                } catch {
                    /* already stopped */
                }
            }
        }
    };
    process.once("exit", stop);
    for (const args of [
        ["artisan", "serve", "--host=127.0.0.1", "--port=8011"],
        ["artisan", "queue:work", "--tries=1", "--timeout=210", "--sleep=1"],
    ]) {
        const child = spawn("php", args, {
            env,
            windowsHide: true,
            detached: process.platform !== "win32",
            stdio: "ignore",
        });
        child.on("error", (error) => {
            child.startError = error;
        });
        children.push(child);
    }
    try {
        const deadline = Date.now() + 30000;
        while (Date.now() < deadline) {
            const failed = children.find(
                (child) => child.startError || child.exitCode !== null,
            );
            if (failed)
                throw (
                    failed.startError ??
                    new Error(
                        `El proceso PHP terminó con código ${failed.exitCode}.`,
                    )
                );
            try {
                const response = await fetch("http://127.0.0.1:8011/up", {
                    signal: AbortSignal.timeout(1000),
                });
                if (response.ok)
                    return {
                        stop: () => {
                            process.removeListener("exit", stop);
                            stop();
                        },
                    };
            } catch {
                /* server is starting */
            }
            await delay(250);
        }
        throw new Error("El servidor E2E no inició a tiempo.");
    } catch (error) {
        process.removeListener("exit", stop);
        stop();
        throw error;
    }
}
