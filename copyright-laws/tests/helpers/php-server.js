import { spawn, spawnSync } from "node:child_process";
import { createServer } from "node:net";

export const hasPhp = spawnSync("php", ["-v"], { stdio: "ignore" }).status === 0;

function freePort() {
  return new Promise((resolve, reject) => {
    const probe = createServer();
    probe.on("error", reject);
    probe.listen(0, "127.0.0.1", () => {
      const { port } = probe.address();
      probe.close(() => resolve(port));
    });
  });
}

/** Starts `php -S` on a free port; resolves once it answers HTTP. */
export async function startPhpServer(docroot, { router, env = {} } = {}) {
  const port = await freePort();
  const args = ["-S", `127.0.0.1:${port}`, "-t", docroot, ...(router ? [router] : [])];
  const child = spawn("php", args, { stdio: "ignore", env: { ...process.env, ...env } });
  const url = `http://127.0.0.1:${port}`;

  for (let attempt = 0; attempt < 60; attempt++) {
    try {
      await fetch(`${url}/__ready`);
      break;
    } catch {
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
  }
  return {
    url,
    stop: () =>
      new Promise((resolve) => {
        child.once("exit", resolve);
        child.kill();
      }),
  };
}
