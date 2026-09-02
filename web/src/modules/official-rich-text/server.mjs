import { createServer } from 'vite';
import { createCollaborationRuntime, MemoryCollaborationAdapter } from './collaboration.mjs';

const host = process.env.COLLABORATION_SPIKE_HOST;
const webPort = Number(process.env.EDITOR_SPIKE_PORT);
const collaborationPort = Number(process.env.COLLABORATION_SPIKE_PORT);
if (host !== '127.0.0.1' || webPort !== 20181 || collaborationPort !== 20282) {
  throw new Error('USE_REGISTERED_SPIKE_ADDRESSES');
}

const documentName = 'tenant:7/draft:article-42';
const adapter = new MemoryCollaborationAdapter();
adapter.createDocument(documentName, 'peanut.richtext/1');
const sessions = Object.fromEntries([
  ['alice', { memberId: 11 }],
  ['bob', { memberId: 12 }],
].map(([role, session]) => [role, {
  ...session,
  token: adapter.issueSession({
    documentName,
    tenantId: 7,
    memberId: session.memberId,
    canEdit: true,
  }),
}]));
const collaboration = createCollaborationRuntime({ adapter, host, port: collaborationPort });

const sendJson = (response, status, value) => {
  response.statusCode = status;
  response.setHeader('content-type', 'application/json; charset=utf-8');
  response.end(JSON.stringify(value));
};

const controls = {
  session(url) {
    const role = url.searchParams.get('role');
    const session = sessions[role];
    if (!session) throw new Error('ROLE_INVALID');
    return { documentName, role, memberId: session.memberId, token: session.token };
  },
  snapshot() {
    return adapter.confirmedSnapshot(documentName);
  },
  revoke(url) {
    const role = url.searchParams.get('role');
    const session = sessions[role];
    if (!session) throw new Error('ROLE_INVALID');
    adapter.revokeEdit(session.token);
    return { role, scope: 'readonly' };
  },
  begin(url) {
    const sequence = Number(url.searchParams.get('sequence'));
    if (!Number.isSafeInteger(sequence) || sequence < 0) throw new Error('SEQUENCE_INVALID');
    return collaboration.beginFinalization(documentName, sequence);
  },
  finish(url) {
    const digest = url.searchParams.get('digest') || '';
    if (!/^[0-9a-f]{64}$/.test(digest)) throw new Error('DIGEST_INVALID');
    return collaboration.finalize(documentName, digest);
  },
};

const vite = await createServer({
  root: import.meta.dirname,
  server: { host, port: webPort, strictPort: true },
  plugins: [{
    name: 'rich-text-collaboration-spike-controls',
    configureServer(server) {
      server.middlewares.use((request, response, next) => {
        const url = new URL(request.url || '/', `http://${host}:${webPort}`);
        const route = url.pathname.replace('/__spike/', '');
        const handler = controls[route];
        if (!url.pathname.startsWith('/__spike/') || !handler) return next();
        const expectedMethod = route === 'session' || route === 'snapshot' ? 'GET' : 'POST';
        if (request.method !== expectedMethod) return sendJson(response, 405, { error: 'METHOD_NOT_ALLOWED' });
        try {
          return sendJson(response, 200, handler(url));
        } catch (error) {
          return sendJson(response, 409, { error: error instanceof Error ? error.message : String(error) });
        }
      });
    },
  }],
});

let stopping = false;
const stop = async () => {
  if (stopping) return;
  stopping = true;
  await vite.close();
  await collaboration.destroy();
};
process.once('SIGINT', () => void stop().then(() => process.exit(0)));
process.once('SIGTERM', () => void stop().then(() => process.exit(0)));

try {
  const collaborationAddress = await collaboration.listen();
  await vite.listen();
  console.log(JSON.stringify({
    status: 'ready',
    editor: `http://${host}:${webPort}/?role=alice`,
    collaboration: collaborationAddress,
  }));
} catch (error) {
  await stop();
  throw error;
}
