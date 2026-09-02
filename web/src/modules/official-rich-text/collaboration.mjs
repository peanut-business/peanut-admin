import { createHash, randomUUID } from 'node:crypto';
import { Server } from '@hocuspocus/server';
import * as Y from 'yjs';

const copy = (value) => new Uint8Array(value);
const digestState = (schemaVersion, encodedState) =>
  createHash('sha256').update(schemaVersion).update('\0').update(encodedState).digest('hex');

export class MemoryCollaborationAdapter {
  #documents = new Map();
  #sessions = new Map();

  createDocument(documentName, editorSchemaVersion) {
    if (!/^tenant:[1-9][0-9]*\/draft:[a-z0-9-]+$/.test(documentName)) {
      throw new Error('DOCUMENT_NAME_INVALID');
    }
    if (this.#documents.has(documentName)) throw new Error('DOCUMENT_EXISTS');
    const encodedState = new Uint8Array();
    this.#documents.set(documentName, {
      editorSchemaVersion,
      state: 'active',
      latestSequence: 0,
      updateDigests: new Set(),
      encodedState,
      contentDigest: digestState(editorSchemaVersion, encodedState),
      pinnedSnapshot: null,
    });
  }

  issueSession({ documentName, tenantId, memberId, canEdit }) {
    const document = this.#document(documentName);
    if (!documentName.startsWith(`tenant:${tenantId}/`) || memberId < 1) {
      throw new Error('SESSION_SCOPE_INVALID');
    }
    if (document.state !== 'active') throw new Error('DOCUMENT_NOT_ACTIVE');
    const token = randomUUID();
    this.#sessions.set(token, { token, documentName, tenantId, memberId, canEdit });
    return token;
  }

  revokeEdit(token) {
    this.#session(token).canEdit = false;
  }

  authorize(token, documentName) {
    const session = this.#session(token);
    const document = this.#document(documentName);
    if (session.documentName !== documentName || !documentName.startsWith(`tenant:${session.tenantId}/`)) {
      throw Object.assign(new Error('DOCUMENT_SCOPE_DENIED'), { code: 4403, reason: 'document-scope-denied' });
    }
    if (document.state !== 'active') {
      throw Object.assign(new Error('DOCUMENT_NOT_ACTIVE'), { code: 4409, reason: 'document-not-active' });
    }
    return { ...session };
  }

  loadState(documentName) {
    return copy(this.#document(documentName).encodedState);
  }

  acceptUpdate({ documentName, payload, encodedState }) {
    const document = this.#document(documentName);
    if (document.state !== 'active') throw new Error('DOCUMENT_NOT_ACTIVE');
    if (!(payload instanceof Uint8Array) || payload.byteLength === 0 || payload.byteLength > 262_144) {
      throw new Error('UPDATE_INVALID');
    }
    if (!(encodedState instanceof Uint8Array) || encodedState.byteLength > 8_388_608) {
      throw new Error('DOCUMENT_STATE_INVALID');
    }
    const updateDigest = createHash('sha256').update(payload).digest('hex');
    if (document.updateDigests.has(updateDigest)) {
      return { sequence: document.latestSequence, duplicate: true };
    }
    document.updateDigests.add(updateDigest);
    document.latestSequence += 1;
    document.encodedState = copy(encodedState);
    document.contentDigest = digestState(document.editorSchemaVersion, document.encodedState);
    return { sequence: document.latestSequence, duplicate: false };
  }

  confirmedSnapshot(documentName) {
    const document = this.#document(documentName);
    return Object.freeze({
      through_server_sequence: document.latestSequence,
      content_digest: document.contentDigest,
      editor_schema_version: document.editorSchemaVersion,
      convergence_state: 'confirmed',
      encoded_state_base64: Buffer.from(document.encodedState).toString('base64'),
    });
  }

  beginFinalization(documentName, expectedSequence) {
    const document = this.#document(documentName);
    if (document.state !== 'active' || document.latestSequence !== expectedSequence) {
      throw new Error('FINALIZATION_SEQUENCE_CONFLICT');
    }
    document.state = 'finalizing';
    document.pinnedSnapshot = this.confirmedSnapshot(documentName);
    return document.pinnedSnapshot;
  }

  finalize(documentName, expectedDigest) {
    const document = this.#document(documentName);
    if (document.state !== 'finalizing'
      || !document.pinnedSnapshot
      || document.pinnedSnapshot.content_digest !== expectedDigest) {
      throw new Error('FINALIZATION_SNAPSHOT_CONFLICT');
    }
    document.state = 'finalized';
    return document.pinnedSnapshot;
  }

  #document(documentName) {
    const document = this.#documents.get(documentName);
    if (!document) throw new Error('DOCUMENT_NOT_FOUND');
    return document;
  }

  #session(token) {
    const session = this.#sessions.get(token);
    if (!session) throw Object.assign(new Error('SESSION_DENIED'), { code: 4403, reason: 'session-denied' });
    return session;
  }
}

export function createCollaborationRuntime({ adapter, host, port }) {
  const server = new Server({
    address: host,
    port,
    quiet: true,
    stopOnSignals: false,
    debounce: 0,
    maxDebounce: 0,
    async onAuthenticate({ token, documentName, connectionConfig }) {
      const session = adapter.authorize(token, documentName);
      connectionConfig.readOnly = !session.canEdit;
      return session;
    },
    async beforeSync({ context, documentName, connection }) {
      const session = adapter.authorize(context.token, documentName);
      connection.context = session;
      connection.readOnly = !session.canEdit;
    },
    async onLoadDocument({ documentName }) {
      const document = new Y.Doc();
      const state = adapter.loadState(documentName);
      if (state.byteLength) Y.applyUpdate(document, state);
      return document;
    },
    onChange({ documentName, update, document }) {
      adapter.acceptUpdate({
        documentName,
        payload: update,
        encodedState: Y.encodeStateAsUpdate(document),
      });
    },
  });

  return {
    server,
    async listen() {
      await server.listen();
      return `ws://${host}:${port}`;
    },
    beginFinalization(documentName, expectedSequence) {
      const snapshot = adapter.beginFinalization(documentName, expectedSequence);
      server.hocuspocus.closeConnections(documentName);
      return snapshot;
    },
    finalize(documentName, expectedDigest) {
      return adapter.finalize(documentName, expectedDigest);
    },
    destroy() {
      return server.destroy();
    },
  };
}
