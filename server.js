const http = require("node:http");
const fs = require("node:fs");
const path = require("node:path");

const PORT = process.env.PORT || 3000;

const server = http.createServer((req, res) => {
  if (req.url === "/" || req.url === "/index.html") {
    res.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
    res.end(fs.readFileSync(path.join(__dirname, "public", "index.html")));
    return;
  }
  res.writeHead(404, { "Content-Type": "text/plain" });
  res.end("Not found\n");
});

server.listen(PORT, () => console.log(`dumont-ws listening on http://localhost:${PORT}`));
