/**
 * Douyin a_bogus signing service
 *
 * Provides a persistent HTTP-based signing service to avoid
 * the overhead of starting a new Node.js process per request.
 */

const http = require("http");

// Reuse the existing algorithm (a_bogus.js exports { get_ab })
const { get_ab } = require("./a_bogus.js");

const PORT = parseInt(process.env.A_BOGUS_PORT || "9876", 10);

const server = http.createServer((req, res) => {
    if (req.method !== "POST") {
        res.writeHead(405, { "Content-Type": "text/plain" });
        res.end("Method Not Allowed");
        return;
    }

    let body = "";
    req.on("data", (chunk) => (body += chunk));
    req.on("end", () => {
        try {
            const { query, ua } = JSON.parse(body);
            if (!query || !ua) {
                res.writeHead(400, { "Content-Type": "application/json" });
                res.end(JSON.stringify({ error: "Missing query or ua" }));
                return;
            }

            const result = get_ab(query, ua);
            res.writeHead(200, {
                "Content-Type": "text/plain",
                "Cache-Control": "no-store",
            });
            res.end(result);
        } catch (e) {
            res.writeHead(500, { "Content-Type": "application/json" });
            res.end(JSON.stringify({ error: e.message }));
        }
    });
});

server.listen(PORT, "127.0.0.1", () => {
    console.error(`a_bogus signing server listening on port ${PORT}`);
});
