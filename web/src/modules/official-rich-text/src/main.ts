import { Editor } from '@tiptap/core';
import {
  HocuspocusProvider,
  HocuspocusProviderWebsocket,
} from '@hocuspocus/provider';
import * as Y from 'yjs';
import {
  DOCUMENT_VERSION,
  cleanPastedHtml,
  documentEnvelope,
  editorExtensions,
  initialContent,
  mapAnchor,
  stableJson,
  type CommentAnchor,
  type CurrentDocument,
  type LegacyDocument,
} from './document';
import './style.css';

const COLLABORATION_DOCUMENT = 'tenant:7/draft:article-42';
const COLLABORATION_URL = 'ws://127.0.0.1:20282';

const element = <T extends HTMLElement>(selector: string): T => {
  const match = document.querySelector<T>(selector);
  if (!match) throw new Error(`missing element: ${selector}`);
  return match;
};

const editorElement = element<HTMLDivElement>('#editor');
const jsonElement = element<HTMLElement>('#document-json');
const anchorStatus = element<HTMLOutputElement>('#anchor-status');
const checkStatus = element<HTMLElement>('#check-status');
const collaborationStatus = element<HTMLOutputElement>('#collaboration-status');
const collaborationRoleElement = element<HTMLElement>('#collaboration-role');
const legacyElement = element<HTMLDivElement>('#legacy-render');
let commentAnchor: CommentAnchor | null = null;
let readonlyEditor: Editor | null = null;
let editor: Editor;

const roleParameter = new URLSearchParams(location.search).get('role');
const collaborationRole = roleParameter === 'bob' ? 'bob' : 'alice';
const collaborationDocument = new Y.Doc();
let connectionState = 'connecting';
let authorizationScope = 'pending';
let unsyncedChanges = 0;
let collaborationError = '';
collaborationRoleElement.textContent = collaborationRole === 'alice' ? '甲编辑' : '乙编辑';

const requestControl = async <T>(path: string, method = 'GET'): Promise<T> => {
  const response = await fetch(`/__rich-text/${path}`, { method });
  const value = await response.json();
  if (!response.ok) throw new Error(value.error || `HTTP_${response.status}`);
  return value as T;
};

const refreshCollaborationStatus = () => {
  const labels: Record<string, string> = {
    connected: '已连接',
    connecting: '连接中',
    disconnected: '已断开',
  };
  collaborationStatus.value = collaborationError
    ? `协同拒绝：${collaborationError}`
    : `${labels[connectionState] || connectionState} · ${authorizationScope} · ${unsyncedChanges ? '有本地待同步' : '服务端已确认'}`;
};

const websocketProvider = new HocuspocusProviderWebsocket({
  url: COLLABORATION_URL,
  delay: 20,
  minDelay: 20,
  maxDelay: 20,
  jitter: false,
});

const provider = new HocuspocusProvider({
  websocketProvider,
  name: COLLABORATION_DOCUMENT,
  document: collaborationDocument,
  token: async () => (await requestControl<{ token: string }>(`session?role=${collaborationRole}`)).token,
  onStatus: ({ status }) => {
    connectionState = status;
    refreshCollaborationStatus();
  },
  onAuthenticated: ({ scope }) => {
    collaborationError = '';
    authorizationScope = scope;
    editor?.setEditable(scope === 'read-write');
    refreshCollaborationStatus();
  },
  onAuthenticationFailed: ({ reason }) => {
    collaborationError = reason;
    authorizationScope = 'denied';
    editor?.setEditable(false);
    refreshCollaborationStatus();
  },
  onUnsyncedChanges: ({ number }) => {
    unsyncedChanges = number;
    refreshCollaborationStatus();
  },
  onSynced: () => {
    void requestControl<{ through_server_sequence: number }>('snapshot').then((snapshot) => {
      if (collaborationRole === 'alice'
        && snapshot.through_server_sequence === 0
        && editor.isEmpty) {
        editor.commands.setContent(initialContent);
      }
    });
  },
});
refreshCollaborationStatus();

const renderAnchor = () => {
  anchorStatus.value = commentAnchor
    ? commentAnchor.status === 'active'
      ? `评论锚点有效：“${commentAnchor.quote}”`
      : `位置已失效：“${commentAnchor.quote}”`
    : '尚未创建锚点';
};

const updateJson = () => {
  jsonElement.textContent = stableJson(editor, commentAnchor);
};

editor = new Editor({
  element: editorElement,
  extensions: editorExtensions(collaborationDocument),
  editable: false,
  editorProps: {
    attributes: {
      'aria-label': '中文新闻稿正文',
      'aria-multiline': 'true',
      role: 'textbox',
      spellcheck: 'true',
    },
    transformPastedHTML: cleanPastedHtml,
  },
  onTransaction: ({ transaction }) => {
    if (commentAnchor) commentAnchor = mapAnchor(commentAnchor, transaction);
    renderAnchor();
  },
  onUpdate: updateJson,
});
editor.setEditable(authorizationScope === 'read-write');

const setStatus = (message: string, state: 'ok' | 'error' | 'idle' = 'idle') => {
  checkStatus.textContent = message;
  checkStatus.dataset.state = state;
};

document.querySelectorAll<HTMLButtonElement>('[data-command]').forEach((button) => {
  button.addEventListener('click', () => {
    const chain = editor.chain().focus();
    const commands: Record<string, () => void> = {
      heading: () => chain.toggleHeading({ level: 1 }).run(),
      paragraph: () => chain.setParagraph().run(),
      bullet: () => chain.toggleBulletList().run(),
      ordered: () => chain.toggleOrderedList().run(),
      undo: () => chain.undo().run(),
      redo: () => chain.redo().run(),
    };
    commands[button.dataset.command || '']?.();
  });
});

element<HTMLButtonElement>('#disconnect-collaboration').addEventListener('click', () => {
  provider.configuration.websocketProvider.disconnect();
});

element<HTMLButtonElement>('#reconnect-collaboration').addEventListener('click', () => {
  void provider.configuration.websocketProvider.connect();
});

element<HTMLButtonElement>('#set-link').addEventListener('click', () => {
  const value = element<HTMLInputElement>('#link-url').value.trim();
  try {
    const url = new URL(value);
    if (!['http:', 'https:', 'mailto:'].includes(url.protocol)) throw new Error('protocol');
    editor.chain().focus().extendMarkRange('link').setLink({ href: value }).run();
    setStatus('链接已应用', 'ok');
  } catch {
    setStatus('链接无效：仅允许 http、https 或 mailto', 'error');
  }
});

element<HTMLButtonElement>('#insert-media').addEventListener('click', () => {
  const ref = element<HTMLInputElement>('#media-ref').value.trim();
  const kind = element<HTMLSelectElement>('#media-kind').value;
  if (!/^[a-z][a-z0-9._:-]{2,80}$/.test(ref)) {
    setStatus('媒体引用格式无效', 'error');
    return;
  }
  editor.chain().focus().insertContent({
    type: 'mediaPlaceholder',
    attrs: { ref, kind, label: `待加载${kind === 'video' ? '视频' : kind === 'audio' ? '音频' : '图片'}` },
  }).run();
  setStatus('媒体占位已插入', 'ok');
});

element<HTMLButtonElement>('#add-comment').addEventListener('click', () => {
  const { from, to } = editor.state.selection;
  const quote = editor.state.doc.textBetween(from, to, ' ');
  if (from === to || !quote) {
    setStatus('请先选择正文文字', 'error');
    return;
  }
  commentAnchor = { id: 'comment-spike-1', from, to, quote, status: 'active' };
  renderAnchor();
  updateJson();
});

const renderReadOnly = (document: CurrentDocument | LegacyDocument | { schemaVersion: string }) => {
  readonlyEditor?.destroy();
  readonlyEditor = null;
  legacyElement.replaceChildren();
  if (document.schemaVersion === 'peanut.richtext/0' && 'html' in document) {
    legacyElement.innerHTML = cleanPastedHtml(document.html);
    return;
  }
  if (document.schemaVersion === DOCUMENT_VERSION && 'content' in document) {
    readonlyEditor = new Editor({
      element: legacyElement,
      extensions: editorExtensions(),
      content: document.content,
      editable: false,
    });
    return;
  }
  legacyElement.textContent = `不支持的文档版本：${document.schemaVersion}`;
};

renderReadOnly({
  schemaVersion: 'peanut.richtext/0',
  html: '<h2>历史稿件</h2><p>旧版 HTML 只读显示，<strong>不会写回</strong>新文档。</p>',
});

const assert = (condition: unknown, message: string) => {
  if (!condition) throw new Error(message);
};

const runFocusedCheck = () => {
  const checkHost = document.createElement('div');
  const checkEditor = new Editor({
    element: checkHost,
    extensions: editorExtensions(),
    content: initialContent,
    editorProps: { transformPastedHTML: cleanPastedHtml },
  });
  const historyEditor = new Editor({
    element: document.createElement('div'),
    extensions: editorExtensions(),
    content: '<p>起</p>',
  });
  let checkAnchor: CommentAnchor | null = null;
  checkEditor.on('transaction', ({ transaction }) => {
    if (checkAnchor) checkAnchor = mapAnchor(checkAnchor, transaction);
  });

  try {
    const clean = cleanPastedHtml(
      '<p class="MsoNormal" style="color:red">安全<script>坏</script><a href="javascript:alert(1)">链接</a></p>'
    );
    assert(!/script|style=|class=|javascript:/i.test(clean), '粘贴清洗失败');

    const serialized = stableJson(checkEditor, null);
    assert(serialized === stableJson(checkEditor, null), 'JSON 序列化不稳定');
    ['heading', 'paragraph', 'bulletList', 'orderedList', 'link', 'mediaPlaceholder'].forEach((type) =>
      assert(serialized.includes(`\"${type}\"`), `JSON 缺少 ${type}`)
    );

    checkEditor.commands.setContent('<p>甲乙丙</p>');
    checkEditor.commands.setTextSelection({ from: 1, to: 3 });
    checkAnchor = { id: 'check', from: 1, to: 3, quote: '甲乙', status: 'active' };
    checkEditor.commands.setTextSelection(1);
    checkEditor.commands.insertContent('前');
    assert(checkAnchor.status === 'active', '锚点未随前置文本移动');
    checkEditor.commands.setTextSelection({ from: 2, to: 4 });
    checkEditor.commands.insertContent('改');
    assert(checkAnchor.status === 'invalid', '锚点内容变化后未明确失效');

    historyEditor.commands.setTextSelection(2);
    historyEditor.commands.insertContent('点');
    historyEditor.commands.undo();
    assert(historyEditor.getText() === '起', '撤销失败');
    historyEditor.commands.redo();
    assert(historyEditor.getText() === '起点', '重做失败');

    const legacyHost = document.createElement('div');
    legacyHost.innerHTML = cleanPastedHtml('<h2>旧稿</h2><script>坏</script>');
    assert(legacyHost.textContent === '旧稿' && !legacyHost.querySelector('script'), '旧版只读渲染失败');
    assert(provider.isAuthenticated && provider.synced, '协同连接尚未服务端确认');
    setStatus('通过：粘贴清洗、结构 JSON、锚点失效、撤销/重做、旧版只读、协同连接', 'ok');
    return { ok: true, checks: 6 };
  } catch (error) {
    setStatus(`失败：${error instanceof Error ? error.message : String(error)}`, 'error');
    throw error;
  } finally {
    checkEditor.destroy();
    historyEditor.destroy();
  }
};

element<HTMLButtonElement>('#run-check').addEventListener('click', runFocusedCheck);
renderAnchor();
updateJson();

Object.assign(window, {
  __richTextModuleDemo: {
    editor,
    provider,
    collaborationDocument,
    cleanPastedHtml,
    documentEnvelope: () => documentEnvelope(editor, commentAnchor),
    runFocusedCheck,
    disconnect: () => provider.configuration.websocketProvider.disconnect(),
    reconnect: () => provider.configuration.websocketProvider.connect(),
    revokeCurrentRole: () => requestControl(`revoke?role=${collaborationRole}`, 'POST'),
    confirmedSnapshot: () => requestControl('snapshot'),
    beginFinalization: (sequence: number) => requestControl(`begin?sequence=${sequence}`, 'POST'),
    finishFinalization: (digest: string) => requestControl(`finish?digest=${digest}`, 'POST'),
    collaborationState: () => ({
      role: collaborationRole,
      connectionState,
      authorizationScope,
      unsyncedChanges,
      collaborationError,
      authenticated: provider.isAuthenticated,
      synced: provider.synced,
    }),
  },
});

window.addEventListener('beforeunload', () => {
  provider.destroy();
  websocketProvider.destroy();
  collaborationDocument.destroy();
  readonlyEditor?.destroy();
});
