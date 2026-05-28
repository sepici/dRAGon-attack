/**
 * PM2 process config for the HTTP-mode MCP server.
 *
 * Usage on Cloudways (or any Linux host):
 *
 *   cd /path/to/dRAGon-attack/mcp
 *   npm install && npm run build
 *   pm2 start ecosystem.config.cjs
 *   pm2 save
 *   pm2 startup    # one-time: makes pm2 auto-start on reboot
 *
 * Then add an nginx location block routing /mcp -> http://127.0.0.1:3001.
 * See README "Deploy on Cloudways" for the snippet.
 */
module.exports = {
  apps: [
    {
      name: "dragonattack-mcp",
      script: "./dist/index.js",
      cwd: __dirname,
      instances: 1,
      exec_mode: "fork", // single Node process; sessions live in memory
      autorestart: true,
      max_memory_restart: "256M",
      env: {
        NODE_ENV: "production",
        MCP_TRANSPORT: "http",
        HOST: "127.0.0.1", // bind localhost; nginx handles TLS + public exposure
        PORT: "3001",
        // DRAGONATTACK_API_URL must be set in the shell that runs `pm2 start`
        // (or via `pm2 set DRAGONATTACK_API_URL ...`). Don't hard-code it here
        // so dev + staging hosts can share this file.
      },
    },
  ],
};
