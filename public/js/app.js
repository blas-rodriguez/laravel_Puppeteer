document.addEventListener("DOMContentLoaded", () => {
    const presetData = document.getElementById("presets-data");
    if (presetData) {
        const presets = JSON.parse(presetData.textContent);
        document.querySelectorAll("[data-preset]").forEach((button) => {
            button.addEventListener("click", () => {
                const preset = presets[button.dataset.preset];
                for (const [name, value] of Object.entries(preset)) {
                    const input = document.getElementById(name);
                    if (input)
                        input.value =
                            name === "fields"
                                ? JSON.stringify(value, null, 2)
                                : value;
                }
                document
                    .querySelectorAll("[data-preset]")
                    .forEach((item) =>
                        item.classList.toggle("selected", item === button),
                    );
            });
        });
    }
    document.getElementById("scrape-form")?.addEventListener("submit", () => {
        const button = document.getElementById("submit-button");
        button.disabled = true;
        button.textContent = "Enviando a la cola…";
    });
    const pending = document.querySelector("[data-poll-url]");
    if (pending) {
        const feedback = document.getElementById("poll-feedback");
        let failures = 0;
        const poll = async () => {
            try {
                const response = await fetch(pending.dataset.pollUrl, {
                    headers: { Accept: "application/json" },
                });
                if (!response.ok) throw new Error("status unavailable");
                const result = await response.json();
                failures = 0;
                feedback.textContent = "";
                if (["completed", "failed"].includes(result.status)) {
                    window.location.reload();
                    return;
                }
                const status = document.getElementById("run-status");
                status.className = `badge ${result.status}`;
                status.textContent = result.label;
            } catch {
                failures++;
                feedback.textContent =
                    "No se pudo actualizar el estado. Reintentando…";
            }
            window.setTimeout(poll, Math.min(1500 * (failures + 1), 15000));
        };
        window.setTimeout(poll, 1500);
    }
});
